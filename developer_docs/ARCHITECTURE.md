# Arquitectura del Módulo RjMulticarrier

Este módulo está diseñado siguiendo una arquitectura modular basada en el patrón **Adapter**, permitiendo la integración de múltiples transportistas (carriers) de manera uniforme y extensible.

## Conceptos Clave

### 1. Carrier Adapter Interface
Todos los conectores de transportistas deben implementar la interfaz `Roanja\Module\RjMulticarrier\Carrier\Adapter\CarrierAdapterInterface`. Esta interfaz define el contrato que asegura que el módulo pueda interactuar con cualquier transportista sin conocer sus detalles de implementación específicos.

Ubicación: `src/Carrier/Adapter/CarrierAdapterInterface.php`

Métodos principales:
- `getCode()`: Retorna el código único del transportista (ej. 'DHL', 'GLS').
- `getDescriptor()`: Retorna metadatos del adaptador (nombre, descripción).
- `generateShipment(CarrierContext $context)`: El método principal que recibe el contexto del envío y retorna el resultado (`CarrierGenerationResult`).
- `getDefaultConfiguration()`: Define los campos de configuración necesarios para este transportista en el Backoffice.

### 2. Carrier Registry
La clase `Roanja\Module\RjMulticarrier\Carrier\Adapter\CarrierRegistry` es responsable de registrar y proporcionar la instancia correcta del adaptador basado en el código del transportista seleccionado.

Ubicación: `src/Carrier/Adapter/CarrierRegistry.php`

### 3. Carrier Context
El objeto `CarrierContext` encapsula toda la información necesaria para generar un envío. Actúa como un DTO (Data Transfer Object) estandarizado que se pasa al adaptador.

Contiene:
- `order_id`: ID del pedido de PrestaShop.
- `payload`: Un array normalizado con información del receptor, remitente, paquetes, etc.
- `options`: Opciones adicionales para la generación.

### 4. Carrier Generation Result
El objeto `CarrierGenerationResult` normaliza la respuesta después de generar un envío.

Contiene:
- `shipment_number`: Número de seguimiento principal.
- `request_payload`: Snapshot de los datos enviados a la API (útil para logs/debugging).
- `response_payload`: Respuesta cruda de la API.
- `labels`: Array de etiquetas generadas (PDF, ZPL, etc).

## Estructura de Directorios

- `src/Carrier/Adapter/`: Contiene las implementaciones concretas de cada transportista (ej. `DhlCarrierAdapter.php`, `GlsCarrierAdapter.php`).
- `src/Entity/`: Entidades de Doctrine que persisten la información (Shipment, Label, etc.).
- `src/Service/`: Servicios de dominio (Generación de etiquetas, gestión de pedidos).
- `src/Repository/`: Repositorios para acceso a datos.

## Flujo de Datos

1. **Entrada**: El sistema recibe una solicitud para crear un envío (desde un Hook o Controller).
2. **Contexto**: Se crea un `CarrierContext` con los datos del pedido y configuración.
3. **Resolución**: El `CarrierRegistry` entrega el adaptador correspondiente (ej. CEX, DHL).
4. **Ejecución**: Se llama a `generateShipment` en el adaptador.
5. **API Call**: El adaptador transforma el contexto al formato específico de la API del transportista y realiza la petición HTTP.
6. **Normalización**: La respuesta de la API se convierte en un `CarrierGenerationResult`.
7. **Persistencia**: El módulo guarda los resultados en la base de datos (tablas `ps_rj_multicarrier_shipment`, `ps_rj_multicarrier_label`).
