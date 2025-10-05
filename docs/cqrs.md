# Capa CQRS de `rj_multicarrier`

La modernización del módulo separa explícitamente las lecturas y escrituras de los envíos mediante consultas y comandos dedicados.

La migración de octubre de 2025 completó la eliminación de la carpeta `src/Legacy`. Los adaptadores que continúan dialogando con componentes heredados de PrestaShop viven ahora en namespaces dedicados (`Carrier\CarrierCompany`, `Pdf\RjPDF*`, `Support\Common`), manteniendo la compatibilidad sin etiquetar nada como legacy dentro del árbol PSR-4 del módulo.

## Lecturas

- **Consulta:** `GetShipmentByOrderId`
- **Handler:** `GetShipmentByOrderIdHandler`
- **Salida:** `ShipmentView`

Este flujo compone la información relevante de Doctrine (`Shipment`, `Label`, `InfoPackage`, `Company`) y la expone a Twig a través del `OrderViewPresenter`. Se utiliza en el panel de pedidos del back-office para mostrar el resumen de etiquetas y paquetes sin depender de `ObjectModel` legacy.

## Escrituras

### Paquetería

- **Comando:** `UpsertInfoPackage`
- **Handler:** `UpsertInfoPackageHandler`
- **Origen legacy:** `CarrierCompany::saveInfoPackage()`

Gestiona la ficha del paquete (dimensiones, horarios, contrareembolso) y sincroniza la tabla `_shop` para entornos multitienda utilizando Doctrine + DBAL.

Desde la versión 2.0.5 del módulo original, también cubre los campos adicionales introducidos para integraciones específicas (`retorno`, `rcs`, `vsec`, `dorig`), manteniendo intacto el contrato legacy que espera esos valores en los formularios de pedido.

Hasta la migración del 3 de octubre de 2025 existía la fachada heredada `RjmulticarrierInfoPackage`; se eliminó definitivamente y cualquier flujo que necesite datos de paquetes debe consumir `InfoPackageRepository` (o los *query handlers* que lo envuelven). Si alguna vista legacy requiere arrays, conviene aplicar un normalizador cercano a la presentación, reutilizando `OrderViewPresenter` cuando sea posible.

### Envíos y etiquetas

- **Comandos:** `GenerateShipment`, `CreateShipment`
- **Handlers:** `GenerateShipmentHandler`, `CreateShipmentHandler`
- **Comando adicional:** `DeleteShipment`
- **Handler adicional:** `DeleteShipmentHandler`
- **Origen legacy:** `CarrierCompany::createShipment()`

Cuando se genera un envío desde los flujos heredados:

1. `CarrierCompany` crea el comando `GenerateShipment`, inyectando el shortname del carrier, los datos del pedido y las opciones de etiqueta.
2. `GenerateShipmentHandler` resuelve el adaptador correspondiente mediante el `CarrierRegistry` y delega la generación de etiquetas PDF (`RjPDF`, `Common::getUUID`).
3. El resultado alimenta al comando `CreateShipment`, que persiste el envío y las etiquetas con Doctrine guardando los PDF en `RJ_MULTICARRIER_LABEL_DIR`.

`CreateShipmentHandler` sigue dando soporte a `CarrierCompany::saveShipment()` y `CarrierCompany::saveLabels()`, garantizando que todos los caminos legacy terminen en la nueva capa de persistencia.

Desde octubre de 2025 el listado de envíos se gestiona exclusivamente con Symfony (`ShipmentController`) y un grid dedicado (`ShipmentGridDefinitionFactory`). Las acciones de impresión consumen `ShipmentLabelPrinter`, que reconstruye etiquetas en disco cuando es necesario, y `DeleteShipmentHandler` se encarga del borrado lógico del envío limpiando también las etiquetas y los ficheros PDF asociados.

#### Adaptadores activos

- **DefaultCarrierAdapter**: genera etiquetas locales mediante `RjPDF` cuando el carrier no dispone de integración propia.
- **CexCarrierAdapter**: dialoga con la API REST de Correos Express, resuelve credenciales por tienda y normaliza la traza de petición/respuesta.
- **GoiCarrierAdapter** (octubre 2025): integra la logística de GOI autenticando via OAuth, creando el envío remoto y descargando las etiquetas PDF con los servicios (`TypeShipment`) configurados en el back-office.
- **DhlCarrierAdapter** (octubre 2025): gestiona autenticación por API key, crea envíos y descarga etiquetas por pieza, reutilizando la información de remitente y paquetes configurada en el legacy.
- **GlsCarrierAdapter** (octubre 2025): reconstruye el flujo XML de ASM/GLS generando el sobre `<Servicios>` con incoterms, retorno y seguros, solicita etiquetas PDF en la misma petición y normaliza la respuesta para almacenarla junto con las etiquetas decodificadas.

Las antiguas fachadas `RjmulticarrierShipment` y `RjmulticarrierLabel` también se retiraron; sus responsabilidades recaen ahora en `ShipmentRepository`, `LabelRepository` y los handlers CQRS (`CreateShipment`, `DeleteShipment`, etc.). Los controladores legacy reutilizan directamente esos servicios mediante el contenedor Symfony.

### Logs de transportistas

- **Comando:** `CreateLogEntry`
- **Handler:** `CreateLogEntryHandler`
- **Origen legacy:** `CarrierCompany::saveLog()`

El flujo legacy delega ahora directamente en el handler Doctrine sin pasar por un `ObjectModel`. Esto facilita añadir metadatos al registro y mantiene la política de ignorar errores de log para no interrumpir la generación de etiquetas.

### Ficha de remitente

- **Comando:** `UpsertInfoShop`
- **Handler:** `UpsertInfoShopHandler`
- **Origen legacy:** `CarrierCompany::saveInfoShop()`

El flujo legacy del formulario de remitente delega en el comando `UpsertInfoShop`, que encapsula la validación mínima y persiste los datos mediante Doctrine. El handler se encarga de:

1. Crear o actualizar la entidad `InfoShop` según corresponda.
2. Normalizar valores opcionales (empresa adicional, notas, contacto) y la bandera `is_business`.
3. Sincronizar la relación multi-tienda insertando en `rj_multicarrier_infoshop_shop` mediante DBAL.

Las lecturas se apoyan directamente en `InfoShopRepository` (por ejemplo dentro de `ConfigurationController`) para hidratar los formularios legacy con arrays compatibles.

### Configuración adicional del módulo

- **Formulario:** `ExtraConfigType`
- **Controlador:** `ConfigurationController::handleExtraConfigSubmit()`
- **Claves de configuración:** `RJ_ETIQUETA_TRANSP_PREFIX`, `RJ_MODULE_CONTRAREEMBOLSO`

La vista Symfony de configuración (`templates/admin/configuration/index.html.twig`) renderiza un formulario dedicado para la configuración extra del transportista. El controlador persiste los valores mediante el servicio legacy `Configuration`, respetando el contexto de tienda y grupo. Esto sustituye al formulario heredado de `CarrierCompany`, mantiene la compatibilidad multitienda y permite añadir nuevas opciones sin depender de `HelperForm`.

### Relaciones de tipos de envío

- **Comandos:**
	- `UpsertTypeShipment`
	- `DeleteTypeShipment`
	- `ToggleTypeShipmentStatus`
- **Handlers:** `UpsertTypeShipmentHandler`, `DeleteTypeShipmentHandler`, `ToggleTypeShipmentStatusHandler`
- **Origen legacy:** `CarrierCompany::_postProcessTypeShipment()`, `CarrierCompany::renderListTypeShipment()`

El panel heredado basado en `HelperForm` para relacionar transportistas ha sido reemplazado por un backend Symfony dedicado (`TypeShipmentController`). El formulario `TypeShipmentType` crea y edita relaciones usando Doctrine y valida conflictos de transportistas activos mediante `TypeShipmentRepository`. Los toggles y borrados despachan los comandos correspondientes, que a su vez se reutilizan desde el legacy (`CarrierCompany`) para garantizar que cualquier flujo antiguo quede cubierto por la nueva capa CQRS.

El adaptador `Carrier\CarrierCompany` y las plantillas de `Pdf\TemplateLabel*` utilizan helpers internos basados en `TypeShipmentRepository`, eliminando la necesidad de fachadas antiguas.

## Beneficios

- Centralización de reglas de persistencia y validación.
- Eliminación progresiva de `ObjectModel` en favor de entidades Doctrine.
- Consistencia entre datos en base de datos y ficheros PDF generados.
- Reutilización desde controladores Symfony, presenters o servicios legacy mediante el `SymfonyContainer`.
- Registro de adaptadores por carrier (`CarrierRegistry`) que permite extender o reemplazar integraciones sin tocar la capa legacy.
- Las fachadas legacy `RjmulticarrierCompany`, `RjmulticarrierTypeShipment`, `RjmulticarrierInfoshop`, `RjmulticarrierLog`, `RjmulticarrierInfoPackage`, `RjmulticarrierLabel` y `RjmulticarrierShipment` fueron retiradas: cualquier flujo debe apoyarse en repositorios o handlers CQRS.
- La estructura PSR-4 está limpia de carpetas `Legacy`; los adaptadores se encuentran en `src/Carrier`, `src/Pdf` y `src/Support`, alineados con el resto de servicios del módulo.

## Próximos pasos sugeridos

- Añadir comandos específicos para actualizar etiquetas (reimpresión) y soft-delete.
- Cubrir la capa con pruebas funcionales que validen el pipeline end-to-end.
- Sustituir accesos directos a `SymfonyContainer` por inyección explícita en nuevos servicios Symfony.
