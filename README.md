# Roanja Multi-carrier (rj_multicarrier)

Modern shipping orchestration module for PrestaShop 8 that centralises multiple carrier integrations under a single back-office experience. It includes a Symfony-based administration UI, Doctrine entities for persistent data, and tooling to monitor carrier interactions.

## ✨ Características principales

- **Integración multi-transportista** mediante un registro de adaptadores extensible.
- **Gestión de envíos** y etiquetas con flujos de generación automáticos.
- **Logs detallados** de peticiones/respuestas a las APIs de transporte, con filtros y exportación.
- **Compatibilidad multitienda**: los datos se segmentan por tienda y el grid de logs permite filtrar por contexto.
- **Interfaz Symfony & PrestaShop Grid** con acciones masivas, búsqueda y exportación CSV.
- **Base de código moderna** preparada para PHP 8.1+, Composer, PHPUnit y patrones CQRS.

## 📦 Requisitos

| Componente                | Versión mínima |
| ------------------------- | -------------- |
| PrestaShop                | 8.0.0          |
| PHP                       | 8.1            |
| Extensiones PHP           | `curl`, `json`, `mbstring`, `pdo_mysql`, `zip` |
| Base de datos             | MySQL 5.7 / MariaDB 10.4 |
| Composer (entorno dev)    | 2.5+           |

> **Nota:** si trabajas en multitienda, asegúrate de haber creado las tiendas antes de instalar el módulo para que los datos iniciales se creen en el contexto correcto.

## 🚀 Instalación

1. **Descarga** o clona este repositorio dentro de `modules/rj_multicarrier`.
2. En el entorno de desarrollo, instala las dependencias de Composer:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. Conecta al back office de PrestaShop y ve a **Módulos → Gestor de Módulos**. Busca "Roanja Multi-carrier" e instala.
4. Tras la instalación, el módulo crea las tablas necesarias y añade los menús de administración bajo **Logística → Multi-carrier** (o la pestaña personalizada del proyecto).

### Actualizaciones

- Si ya existe el módulo instalado, sube los archivos actualizados y ejecuta el script de actualización desde el panel de módulos.
- En entorno de desarrollo, limpia la caché de Symfony tras desplegar cambios estructurales:

  ```bash
  php bin/console cache:clear --no-warmup
  ```

## ⚙️ Configuración inicial

1. **Credenciales de transportistas:** accede a **Multi-carrier → Configuración** y rellena los datos de las integraciones activas.
2. **Tipos de envío:** define las reglas en **Multi-carrier → Tipos de envío** (tarifas, servicios, etc.).
3. **Contexto multitienda:** el módulo detecta la tienda activa; usa el selector de tienda de PrestaShop para revisar o actualizar datos por contexto.

## 📊 Registro de logs

- Los logs viven en `Multi-carrier → Logs`.
- Los filtros permiten buscar por ID de log, nombre, pedido, tienda y rango de fechas.
- La columna "Acciones" ofrece vista detallada y eliminación individual; las acciones masivas permiten borrado en lote.
- El botón **Exportar CSV** recopila los registros visibles aplicando los filtros activos.

## 🧪 Desarrollo

- Ejecutar pruebas unitarias:

  ```bash
  ./vendor/bin/phpunit
  ```

- Regenerar autoload optimizado:

  ```bash
  composer dump-autoload -o
  ```

- Limpieza rápida de caché del módulo (recomendado tras tocar servicios o entidades):

  ```bash
  php bin/console cache:clear --env=prod
  ```

## 🛠️ Solución de problemas

- **Doctrine no encuentra la entidad `LogEntry`:** limpia la caché Symfony/Doctrine y asegúrate de que el servicio `roanja.module.rj_multicarrier.doctrine.mapping_configurator` está activo (se carga desde `config/services_overrides.yml`).
- **Los filtros de la grid no funcionan:** verifica que el contexto de tienda es correcto y que la base de datos contiene el campo `id_shop` en `ps_rj_multicarrier_log`. El `LogQueryBuilder` normaliza valores vacíos para evitar filtros inconsistentes.
- **Errores de permisos al ejecutar Composer:** ajusta los permisos de escritura sobre `modules/rj_multicarrier/vendor` antes de lanzar `composer install`.

## 📄 Licencia

Distribuido bajo [Academic Free License 3.0](./LICENSE). Consulta los encabezados de los archivos PHP para más detalles.

## 🤝 Contribuir

1. Crea un fork y una rama descriptiva (`feature/nueva-funcionalidad`).
2. Asegúrate de que pasan las pruebas (`./vendor/bin/phpunit`).
3. Abre un pull request describiendo los cambios y pasos de prueba manual.

---

¿Dudas o nuevas necesidades de transporte? Contacta con el equipo de Roanja para soporte especializado y planes de evolución.
