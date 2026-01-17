# Ejemplos para Postman

A continuación se presentan los detalles para configurar peticiones en Postman para probar directamente las integraciones con los transportistas.

> **Nota**: Reemplace los valores en mayúsculas (ej. `{TOKEN}`) con sus credenciales reales.

## 1. DHL Parcel Iberia

Esta integración requiere dos pasos: Login y Creación de Envío.

### Paso 1: Autenticación (Login)
*   **Método**: `POST`
*   **URL**: `https://api-gw.dhlparcel.es/authenticate/api-key` (Prod) o URL de desarrollo.
*   **Headers**:
    *   `Content-Type`: `application/json`
    *   `Accept`: `application/json`
*   **Body (JSON)**:
    ```json
    {
        "userId": "{USER_ID}",
        "key": "{API_KEY}"
    }
    ```
*   **Respuesta esperada**: Guardar el valor de `accessToken` para el siguiente paso.

### Paso 2: Crear Envío
*   **Método**: `POST`
*   **URL**: `https://api-gw.dhlparcel.es/shipments`
*   **Headers**:
    *   `Content-Type`: `application/json`
    *   `Authorization`: `Bearer {ACCESS_TOKEN_DEL_PASO_1}`
*   **Body (JSON)**:
    Ver ejemplo en `CARRIERS_API.md` -> Seccion DHL.

---

## 2. GOI Logistics

Requiere autenticación OAuth2.

### Paso 1: Obtener Token
*   **Método**: `POST`
*   **URL**: `https://api.letsgoi.com/oauth/token` (o URL de desarrollo)
*   **Headers**: `Content-Type: application/json`
*   **Body (JSON)**:
    ```json
    {
        "client_id": "{USER_ID}",
        "client_secret": "{KEY}",
        "grant_type": "client_credentials"
    }
    ```

### Paso 2: Crear Envío
*   **Método**: `POST`
*   **URL**: `https://api.letsgoi.com/integrations/import`
*   **Headers**:
    *   `Content-Type`: `application/json`
    *   `Authorization`: `Bearer {ACCESS_TOKEN}`
*   **Body (JSON)**:
    Ver ejemplo en `CARRIERS_API.md` -> Seccion GOI.

---

## 3. Correos Express (CEX)

No requiere login previo (las credenciales van en el body).

*   **Método**: `POST`
*   **URL**: `{URL_ENDPOINT_CEX}` (Consultar documentación oficial o config del módulo)
*   **Headers**: `Content-Type: application/json`
*   **Body (JSON)**:
    Ver ejemplo en `CARRIERS_API.md` -> Seccion CEX. Asegúrese de que `solicitante` y `codRte` sean correctos.

---

## 4. GLS / ASMRed

Usa XML sobre HTTP.

*   **Método**: `POST`
*   **URL**: `http://www.asmred.com/b2b/xml_grabar_envios.asmx/GrabarEnvio` (o similar, verificar WSDL)
*   **Headers**: `Content-Type: application/xml` o `text/xml`
*   **Body (XML)**:
    Ver ejemplo en `CARRIERS_API.md` -> Seccion GLS.
    *   **Importante**: El XML debe estar bien formado y el atributo `uidcliente` es su GUID.
