# Arquitectura modular de carriers

## Objetivos
- Permitir que cada carrier se comporte como un submodulo activable/desactivable sin tocar tablas legacy.
- Centralizar la configuracion en las tablas `rj_multicarrier_carrier` y `rj_multicarrier_carrier_configuration`, con soporte de campos dinamicos y plantillas JSON.
- Facilitar la incorporacion de nuevos carriers mediante esquemas declarativos y adaptadores registrados como servicios Symfony.

## Modelo de datos
1. **Carrier**: almacena metadatos (nombre, shortname, icono) y banderas (`is_active`, `supports_api`). Los campos `adapter_service`, `configuration_schema` y `default_payload` determinan el comportamiento del submodulo.
2. **CarrierConfiguration**: contiene pares clave-valor por ambito (shop group/shop/type shipment). Los campos extra (`field_group`, `field_type`, `payload_*`, `is_dynamic`) describen como se renderiza y utiliza cada valor.
3. **TypeShipment**: amplia con `settings` JSON para reglas especificas por servicio.

## Esquema declarativo (`configuration_schema`)
- Definido como JSON validado con esquema propio.
- Describe pestanas, grupos y campos con atributos: `name`, `label`, `type`, `required`, `visibility`, `default`, `help`, `constraints`.
- Permite marcar fuentes de datos (manual, API, sensores) y condicionar la UI dependiendo de flags (`supports_api`).

## Adaptadores
- Cada carrier registra un servicio (`adapter_service`) que implementa una interfaz comun (crear etiqueta, cotizar, validar configuracion).
- El servicio recibe:
  - Configuracion resuelta (union de defaults + registros `CarrierConfiguration`).
  - Schema para conocer campos opcionales.
- Responde con estructuras normalizadas (por ejemplo `ShipmentResponseDTO`).

## Flujo en back-office
1. Administrador habilita/deshabilita carriers desde un listado que lee `is_active`.
2. Al editar un carrier, la UI carga `configuration_schema` para pintar formularios dinamicos.
3. Cada guardado actualiza los registros en `rj_multicarrier_carrier_configuration`, manteniendo trazabilidad por `position` e `is_dynamic`.
4. Los servicios de envio leen la configuracion contextual (priorizando shop > shop group > global) antes de invocar al adaptador.

## Roadmap tecnico
- [ ] Crear validador de `configuration_schema` + conversor Symfony.
- [ ] Actualizar entidades/DTOs para nuevos campos.
- [ ] Implementar repositorio de configuraciones por ambito.
- [ ] Refactorizar controladores/plantillas del BO para consumir el esquema.
- [ ] Ajustar servicios de shipping para usar `adapter_service` y `default_payload`.

## Consideraciones
- No se dependera de datos legacy ni de tablas core `carrier` mas alla del `id_reference_carrier` opcional.
- Se podran definir carriers 100% manuales (sin API) manteniendo compatibilidad con flujos existentes.
- Los cambios en esquema se realizan via migraciones en `upgrade/` para instalaciones existentes.
