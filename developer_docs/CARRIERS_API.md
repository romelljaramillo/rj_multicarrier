# Documentación de APIs de Transportistas

Este documento detalla los formatos de datos (Payloads) que se envían a cada transportista integrado. Esta información es útil para depurar errores y entender qué datos espera cada API.

## 1. Correos Express (CEX)

*   **Tipo**: REST (JSON)
*   **Autenticación**: Credenciales incluidas en el payload (`solicitante`).
*   **Método**: POST

### Ejemplo de Payload (Request)

```json
{
  "solicitante": "P000000",
  "canalEntrada": "",
  "numEnvio": "",
  "ref": "ORD-123",
  "refCliente": "ORD-123",
  "fecha": "01012024",
  "codRte": "000000",
  "nomRte": "Mi Tienda",
  "nifRte": "B12345678",
  "dirRte": "Calle Falsa 123",
  "pobRte": "Madrid",
  "codPosNacRte": "28001",
  "paisISORte": "ES",
  "codPosIntRte": "",
  "contacRte": "Juan Perez",
  "telefRte": "600000000",
  "emailRte": "tienda@example.com",
  "codDest": "",
  "nomDest": "Cliente Final",
  "dirDest": "Av. Principal 456",
  "pobDest": "Barcelona",
  "codPosNacDest": "08001",
  "paisISODest": "ES",
  "telefDest": "600123456",
  "emailDest": "cliente@example.com",
  "observac": "Entregar por la tarde",
  "numBultos": "1",
  "kilos": "1.5",
  "alto": "10",
  "largo": "20",
  "ancho": "15",
  "producto": "63", 
  "portes": "P",
  "reembolso": "",
  "listaBultos": [
    {
      "codBultoCli": 1,
      "orden": 1
    }
  ]
}
```

---

## 2. GLS / ASMRed

*   **Tipo**: SOAP (XML)
*   **Autenticación**: `uidcliente` (GUID) en el XML.
*   **Método**: HTTP POST (Body contains XML string)

### Estructura XML (Request)

```xml
<Servicios uidcliente="{GUID-DE-CLIENTE}">
  <Envio codbarras="">
    <Fecha>01/01/2024</Fecha>
    <Portes>P</Portes>
    <Servicio>1</Servicio>
    <Horario>3</Horario>
    <Bultos>1</Bultos>
    <Peso>1.5</Peso>
    <Retorno>0</Retorno>
    <Pod>N</Pod>
    <Remite>
       <Nombre>Mi Tienda</Nombre>
       <Direccion>Calle Falsa 123</Direccion>
       <Poblacion>Madrid</Poblacion>
       <CP>28001</CP>
       <Pais>ES</Pais>
    </Remite>
    <Destinatario>
       <Nombre>Cliente Final</Nombre>
       <Direccion>Av. Principal 456</Direccion>
       <Poblacion>Barcelona</Poblacion>
       <CP>08001</CP>
       <Pais>ES</Pais>
       <Movil>600123456</Movil>
       <Email>cliente@example.com</Email>
       <Observaciones>Entregar tarde</Observaciones>
    </Destinatario>
    <Referencias>
       <Referencia tipo="C">ORD-123</Referencia>
    </Referencias>
  </Envio>
</Servicios>
```

---

## 3. DHL Parcel Iberia

*   **Tipo**: REST (JSON)
*   **Autenticación**: Bearer Token. Requiere llamada previa a `/authenticate/api-key`.
*   **Método**: POST `/shipments`

### Estructura JSON (Shipment Request)

```json
{
  "shipmentId": "UUID-GENERADO",
  "orderReference": "ORD-123",
  "receiver": {
    "name": {
      "firstName": "Cliente",
      "lastName": "Final"
    },
    "address": {
      "countryCode": "ES",
      "postalCode": "08001",
      "city": "Barcelona",
      "street": "Av. Principal",
      "number": "456",
      "isBusiness": false
    },
    "email": "cliente@example.com",
    "phoneNumber": "600123456"
  },
  "shipper": {
    "name": {
      "firstName": "Mi",
      "lastName": "Tienda",
      "companyName": "Mi Empresa SL"
    },
    "address": {
      "countryCode": "ES",
      "postalCode": "28001",
      "city": "Madrid",
      "street": "Calle Falsa",
      "number": "123",
      "isBusiness": true
    },
    "email": "tienda@example.com"
  },
  "accountId": "DHL_ACCOUNT_ID",
  "options": [
    {
      "key": "REFERENCE",
      "input": "ORD-123"
    }
  ],
  "pieces": [
    {
      "parcelType": "SMALL",
      "quantity": 1,
      "weight": 1.5,
      "dimensions": {
        "length": 20,
        "width": 15,
        "height": 10
      }
    }
  ]
}
```

---

## 4. GOI Logistics

*   **Tipo**: REST (JSON)
*   **Autenticación**: OAuth2 (Client Credentials). Requiere token Bearer.
*   **Método**: POST `/integrations/import`

### Estructura JSON (Shipment Request)

```json
{
  "order_id": "123",
  "store_id": "STORE_ID_GOI",
  "metadata": "{\"id_order\":\"123\"}",
  "services": [],
  "customer_firstname": "Cliente",
  "customer_lastname": "Final",
  "customer_phone": "600123456",
  "customer_email": "cliente@example.com",
  "address": "Av. Principal 456",
  "city": "Barcelona",
  "zip": "08001",
  "country_code": "ES",
  "notes": "Entregar tarde",
  "weight": 1.5,
  "volume": 0.003,
  "packages": 1,
  "retail_price": 50.00,
  "articles": [
    {
      "id": "10",
      "name": "Producto A",
      "quantity": 1,
      "volume": 0.003,
      "weight": 1.5
    }
  ]
}
```
