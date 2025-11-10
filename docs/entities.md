# Esquema de entidades Doctrine

Resumen de tablas legacy y campos relevantes para la migración a Doctrine ORM.

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
- **date_add**, **date_upd**

## TypeShipment (`rj_multicarrier_type_shipment`)
- **id_type_shipment** (`INT`, PK)
- **id_carrier** (`INT`, FK a Carrier)
- **name**, **id_bc** (`VARCHAR`)
- **id_reference_carrier** (`INT`, FK a tabla `carrier` core)
- **active** (`TINYINT(1)`)
- **date_add**, **date_upd**

## Configuration (`rj_multicarrier_configuration`)
- **id_carrier_configuration** (`INT`, PK)
- Datos de remitente: **firstname**, **lastname**, **company**, **additionalname**
- Localización: **id_country**, **state**, **city**, **street**, **number**, **postcode**, **additionaladdress**
- Contacto: **isbusiness**, **email**, **phone**, **vatnumber**
- **date_add**, **date_upd**

## CarrierConfiguration (`rj_multicarrier_carrier_configuration`)
- **id_carrier_configuration** (`INT`, PK)
- **id_carrier**, **id_type_shipment** (`INT`, FK a Carrier y TypeShipment)
- **id_shop_group**, **id_shop** (`INT`, nullables)
- **name** (`VARCHAR(254)`), **value** (`TEXT`)
- **date_add**, **date_upd**

## Log (`rj_multicarrier_log`)
- **id_carrier_log** (`INT`, PK)
- **id_order**, **name** (`VARCHAR`)
- **request**, **response** (`LONGTEXT`)
- **date_add**, **date_upd**

### Consideraciones
- Las tablas con sufijo `_shop` gestionan contexto multi-tienda; inicialmente se mapearán via repositorios legacy o servicios específicos.
- Para fechas se usará `\DateTimeImmutable` en Doctrine.
- Los flags booleanos se mapearán como `bool` con conversión automática.
- Los campos `request`/`response` se guardarán como `text`.
