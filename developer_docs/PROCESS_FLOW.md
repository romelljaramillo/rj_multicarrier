# Flujo de Procesos: Generación de Envíos

Este documento describe el flujo paso a paso que ocurre cuando se genera una etiqueta o envío a través del módulo.

## Diagrama de Flujo Lógico

1.  **Inicio**: Usuario hace clic en "Generar Envío" en el Backoffice o se dispara un Hook automático.
2.  **Preparación de Datos**:
    *   Se carga la información del pedido de PrestaShop (`Order`).
    *   Se carga la configuración del transportista asociado.
    *   Se normalizan los datos de dirección (Remitente y Destinatario).
    *   Se calculan pesos y dimensiones de los paquetes.
3.  **Selección de Adaptador**:
    *   El sistema identifica el transportista (ej. DHL).
    *   `CarrierRegistry` instancia `DhlCarrierAdapter`.
4.  **Generación (Adapter Layer)**:
    *   `CarrierAdapter::generateShipment($context)` es invocado.
    *   **Autenticación**: Si es necesario (ej. DHL, GOI), el adaptador solicita un token de acceso.
    *   **Mapping**: Los datos del contexto se transforman al JSON/XML específico del transportista.
    *   **API Request**: Se envía la petición al endpoint del transportista (ej. `POST /shipments`).
    *   **API Response**: Se recibe la respuesta. Si hay error, se lanza una Excepción.
    *   **Recuperación de Etiquetas**: Si el transportista lo requiere, se hace una segunda llamada para descargar el PDF de la etiqueta (`GET /labels`).
5.  **Procesamiento de Respuesta**:
    *   Se extrae el número de seguimiento (`tracking_number`).
    *   Se decodifican las etiquetas (Base64 a binario si es necesario).
6.  **Persistencia (Base de Datos)**:
    *   Se crea un registro en `ps_rj_multicarrier_shipment` con el tracking, estado y respuesta JSON.
    *   Se guardan las etiquetas en `ps_rj_multicarrier_label`.
7.  **Finalización**:
    *   Se actualiza el estado del pedido en PrestaShop (si está configurado).
    *   Se envía email al cliente (si está configurado).

## Manejo de Errores

*   **Validación Local**: Si faltan datos obligatorios (ej. DNI, Teléfono), el proceso se detiene antes de llamar a la API.
*   **Error de API**: Si la API responde con error (ej. código postal inválido), el adaptador lanza una excepción con el mensaje del transportista. Este mensaje se muestra al usuario en el Backoffice.
*   **Error de Conexión**: Fallos de red (Timeout, DNS) lanzan excepciones genéricas que deben ser reintentadas.

## Hooks Importantes

*   `actionAdminOrdersListingFieldsModifier`: Añade columnas a la lista de pedidos.
*   `displayAdminOrder`: Muestra el bloque de gestión de envíos en la ficha del pedido.
