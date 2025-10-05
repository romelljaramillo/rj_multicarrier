# Guía de instalación

Esta guía resume los pasos necesarios para desplegar correctamente el módulo **RJ Multicarrier** en un entorno PrestaShop 8.

## Requisitos previos

- PrestaShop 8.0 o superior.
- PHP 8.1 o superior con extensiones comunes (curl, json, mbstring, openssl, pdo_mysql).
- Acceso SSH o a la consola del servidor para ejecutar Composer.
- Permisos de escritura sobre el directorio del módulo (`modules/rj_multicarrier`) y sobre `var/` y `labels/`.

## Pasos de instalación

1. **Copiar el módulo**
   - Suba la carpeta `rj_multicarrier/` al directorio `modules/` de su tienda.

2. **Instalar dependencias PHP**
   ```bash
   cd modules/rj_multicarrier
   composer install --no-dev --optimize-autoloader
   ```
   > Si su entorno no dispone de Composer, ejecútelo en local y suba la carpeta `vendor/` resultante.

3. **Dar permisos a los directorios de trabajo**
   - Asegúrese de que los directorios generados por el instalador (`var/`, `var/icons/`, `labels/`) poseen permisos de escritura para PHP.

4. **Instalar desde el back-office**
   - Entre en el panel de PrestaShop como administrador.
   - Navegue a *Módulos > Gestor de Módulos*, busque "Roanja Multi-carrier" y pulse *Instalar*.
   - El instalador ejecutará:
     - El script `sql/install.php`, creando todas las tablas (`rj_multicarrier_*`).
     - La generación de directorios (`var/`, `labels/`).
     - El alta de pestañas administrativas:
       - Multi-carrier (padre)
       - Configuración
       - Tipos de envío
       - Envíos
       - Generar envío (paquetes pendientes)
       - Logs
       - Ajax bridge (oculta)
     - La limpieza de la caché de rutas de Symfony.

5. **Verificación rápida**
   - Compruebe que aparece el nuevo menú en *Transporte > Multi-carrier*.
   - Acceda a *Módulos > Roanja Multi-carrier* para comprobar que la pantalla de configuración redirige correctamente a la interfaz Symfony.
   - Revise que puede crear tipos de envío y generar envíos sin errores de permisos.

## Desinstalación

- Desde el gestor de módulos, al desinstalar se eliminarán las pestañas y se ejecutará `sql/uninstall.php`, que borra todas las tablas creadas.
- Los directorios `var/` y `labels/` no se eliminan automáticamente (conserve copias de seguridad si es necesario).

## Solución de problemas

- **Hooks no registrados**: si el menú o los widgets no aparecen, reinstale el módulo o ejecute `bin/console prestashop:module install rj_multicarrier`. A partir de la versión 3.0.0 los hooks se registran individualmente durante la instalación.
- **Errores de caché de rutas**: si la configuración no abre la pantalla Symfony, limpie la caché con `bin/console cache:clear --env=prod` o usando *Parámetros Avanzados > Rendimiento*.
- **Permisos**: asegúrese de que PHP puede escribir en `modules/rj_multicarrier/var/` y `modules/rj_multicarrier/labels/`.

Con estos pasos la instalación del módulo queda confirmada y lista para su configuración.
