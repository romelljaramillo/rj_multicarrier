# Esquema de entidades (solo rj_*)

Inventario actualizado de las tablas `rj_multicarrier_*` que usaremos para modelar la nueva arquitectura modular de carriers. Se excluyen por completo tablas legacy del core.

## Shipment (`rj_multicarrier_shipment`)
- **id_shipment** (`INT`, PK, autoincrement)
- **id_order** (`INT`, referencia a `orders`)
- **reference_order** (`VARCHAR(100)`)
- **num_shipment** (`VARCHAR(100)`)
- **id_carrier** (`INT`, FK a `rj_multicarrier_carrier`)
- **id_info_shipment** (`INT`, FK a `rj_multicarrier_info_shipment`)
- **account** (`VARCHAR(100)`)
- **product** (`VARCHAR(100)`)
- **request** (`LONGTEXT`)
- **response** (`LONGTEXT`)
- **delete** (`TINYINT(1)`, soft delete)
- **date_add**, **date_upd** (`DATETIME`)

## InfoPackage (`rj_multicarrier_info_shipment`)
- **id_info_shipment** (`INT`, PK)
- **id_order**, **id_reference_carrier**, **id_type_shipment**, **quantity**, **weight** (campos obligatorios)
- Dimensiones: **length**, **width**, **height** (`DECIMAL`/`FLOAT`)
- **cash_ondelivery** (`DECIMAL`)
- **message** (`VARCHAR(255)`)
- **hour_from**, **hour_until** (`TIME`)
- **date_add**, **date_upd**

## Label (`rj_multicarrier_label`)
- **id_label** (`INT`, PK)
- **id_shipment** (`INT`, FK a Shipment)
- **package_id** (`VARCHAR(50)`)
- **tracker_code**, **label_type** (`VARCHAR`)
- **pdf** (`TEXT`), **print** (`TINYINT(1)`)
- **date_add**, **date_upd**

## Carrier (`rj_multicarrier_carrier`)
- **id_carrier** (`INT`, PK)
- **name**, **shortname**, **icon** (`VARCHAR`)
- **is_active** (`TINYINT(1)`) indica si el submódulo está disponible
- **supports_api** (`TINYINT(1)`) habilita el modo "API" para generar credenciales/campos específicos
- **adapter_service** (`VARCHAR(191)`) FQCN del adaptador Symfony que implementa la lógica del carrier
- **configuration_schema** (`LONGTEXT`, JSON) definición de grupos/campos dinámicos visibles en la UI
- **default_payload** (`LONGTEXT`, JSON) plantilla base para request/response
- **date_add**, **date_upd**

### `configuration_schema` (convención)
```json
{
	"tabs": [
		{
			"code": "general",
			"label": "General",
			"fields": [
				{"name": "api_key", "type": "text", "required": true, "group": "credentials"}
			]
		}
	]
}
```
La UI leera esta estructura para renderizar formularios, permitir activar/desactivar campos y asociar metadatos a cada carrier sin tocar código.

## TypeShipment (`rj_multicarrier_type_shipment`)
- **id_type_shipment** (`INT`, PK)
- **id_carrier** (`INT`, FK a Carrier)
- **name**, **id_bc** (`VARCHAR`)
- **id_reference_carrier** (`INT`, FK opcional a carrier nativo para mapear tarifas)
- **active** (`TINYINT(1)`)
- **settings** (`JSON`) permite guardar reglas o parámetros específicos del servicio
- **date_add**, **date_upd**

## Configuration (`rj_multicarrier_configuration`)
- **id_configuration** (`INT`, PK)
- Datos de remitente (firstname/lastname/company...) que seguirán siendo la base para etiquetas manuales
- Campos de localización/contacto idénticos a la versión previa
- **active**, **date_add**, **date_upd**

## CarrierConfiguration (`rj_multicarrier_carrier_configuration`)
- **id_carrier_configuration** (`INT`, PK)
- **id_carrier**, **id_type_shipment**, **id_shop_group**, **id_shop** para delimitar el ámbito
- **name** / **value** definen cada par clave-valor configurable
- **is_required** controla validaciones runtime
- **field_group**, **field_type** (por defecto `text`) describen cómo debe mostrarse el campo
- **payload_direction** (`request`/`response`/`none`) indica hacia dónde viaja el dato
- **payload_key** y **payload_source** ayudan a mapear el valor cuando el adaptador construye automáticamente el JSON
- **position** y **is_dynamic** permiten ordenar y distinguir campos añadidos desde UI
- **date_add**, **date_upd**

## Log (`rj_multicarrier_log`)
- **id_carrier_log** (`INT`, PK)
- **id_order**, **name** (`VARCHAR`)
- **request**, **response** (`LONGTEXT`)
- **date_add**, **date_upd**

### Consideraciones
- No se utilizarán tablas legacy: todos los datos de configuración y dinámica provienen de `rj_multicarrier_*`.
- Las tablas con sufijo `_shop` siguen aportando alcance multi-tienda y se mapearán mediante repositorios específicos del módulo.
- Doctrine usará `\DateTimeImmutable` para fechas y convertidores nativos para JSON/booleanos.
- El `adapter_service` se registrará como servicio Symfony etiquetado y se resolverá dinámicamente según el carrier activo.
