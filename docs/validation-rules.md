# Reglas de validación de transportistas

Este README documenta el motor de reglas que utiliza `rj_multicarrier` para decidir qué transportistas se muestran en el checkout de PrestaShop. Las reglas se gestionan desde **Logística → Multi-carrier → Validaciones de transportista** y se apoyan en servicios Symfony + Doctrine, pero pueden evaluarse también en contexto legacy gracias al `RuntimeValidationRuleProvider`.

## Flujo de ejecución

1. El módulo engancha en `actionCarrierProcess` y `actionDeliveryOptionListModifier` para interceptar los paquetes devueltos por `Cart::getPackageList()`.
2. Se obtienen las reglas activas de la tienda actual (según el selector multitienda) ordenadas por prioridad ascendente.
3. Para cada paquete (agrupado por dirección):
   - Se construye el **contexto** con los productos, categorías, peso acumulado, zona y país del destinatario.
   - Cada regla se evalúa; si todas las condiciones se cumplen, se aplican las acciones sobre la lista de carriers del paquete.
4. Al finalizar, las opciones resultantes se vuelcan en caché y PrestaShop solo mostrará los transportistas permitidos. Si alguna regla deja la lista vacía, se marca el paquete con el sentinel `[0 => 0]` para que PrestaShop muestre el mensaje estándar de "sin transportistas" y el módulo añada una alerta explicando qué condiciones provocaron el bloqueo.

## Condiciones disponibles

| Campo            | Comportamiento |
| ---------------- | -------------- |
| **Productos**    | La regla solo se activa si **al menos un** producto del carrito está en la lista. Se introduce mediante buscador múltiple o pegando IDs separados por coma/espacios. |
| **Categorías**   | Funciona igual que productos, pero con la categoría por defecto de cada línea de carrito. |
| **Zonas**        | Requiere que la dirección de entrega pertenezca a alguna de las zonas del selector. |
| **Países**       | Igual que zonas, pero evaluando `id_country`. |
| **Peso mínimo/máximo** | El peso total del paquete debe estar dentro del intervalo (ambos inclusive). Puedes dejar cualquiera de los dos valores vacío para que no limite. |

> Todas las condiciones se evalúan con lógica **AND**. Si no configuras un campo, se considera siempre cumplido.

### Contexto multitienda

- El formulario solo permite crear o editar reglas cuando seleccionas una tienda concreta en el selector multitienda de PrestaShop. El campo "Ámbito" desaparece porque la regla queda ligada automáticamente a esa tienda (`shop_id`).
- En el listado del módulo se muestran únicamente las reglas de la tienda activa. Si entras en modo "Todas las tiendas" o "Grupo", el botón de creación se deshabilita y se te pide que elijas una tienda antes de continuar.
- En tiempo de ejecución solo se evalúan las reglas cuyo `shop_id` coincide con el del carrito. Esto garantiza que las configuraciones de una tienda no afectan al resto.

## Tabla de acciones

Las acciones se ejecutan en este orden: `allow` → `add` → `deny`.

| Acción     | Efecto sobre la lista de carriers | Caso de uso típico |
| ---------- | --------------------------------- | ------------------ |
| **allow_ids** | Mantiene solo las referencias indicadas. Si la lista queda vacía, el paquete quedará sin carriers y se informará en checkout del motivo. | Forzar un único transportista para un producto frágil o una tienda concreta. |
| **add_ids**   | Inserta carriers adicionales (por referencia) aunque no estuvieran en la lista original, siempre que existan en PrestaShop. | Ofrecer un transportista experimental para una categoría concreta sin tocar las tarifas base. |
| **deny_ids**  | Elimina las referencias listadas del resultado actual. | Bloquear envíos por una zona o país donde el carrier no opera. |

### Carriers por referencia

Todos los campos de acciones trabajan con **`id_reference`** del transportista, no con `id_carrier`. El formulario acepta IDs numéricos y el servicio `ValidationRuleReferenceNormalizer` los normaliza automáticamente para que sigan siendo válidos aunque regeneres un carrier.

## Prioridad y evaluación

- Las reglas se ordenan **ascendentemente por prioridad** (0 aparece antes que 10). Si dos reglas modifican los mismos carriers, el resultado de la primera es la base para la siguiente.
- Puedes combinar varias acciones en la misma regla. Por ejemplo, permitir solo dos referencias (`allow`) y, a continuación, añadir una tercera (`add`) que no estaba disponible originalmente antes de aplicar un `deny` final para un carrier concreto si reaparece por otra regla.
- Si tras aplicar todas las acciones la lista queda vacía, el módulo mantiene `[0 => 0]` para ese paquete. Así se bloquea la compra conscientemente y se muestra una alerta visible indicando qué reglas/condiciones lo provocaron.

## Ejemplos prácticos

| Escenario | Configuración | Resultado |
| --------- | ------------- | --------- |
| **Ocultar GLS para Alemania** | Contexto: Tienda principal. Países: Alemania. Acción: `deny_ids = [ref_gls]`. | Cada paquete con dirección alemana eliminará GLS pero mantiene el resto para esa tienda. |
| **Solo transporte refrigerado para la categoría Frescos** | Contexto: Tienda 3. Categorías: *Frescos*. Acción: `allow_ids = [ref_coldexpress]`. | En la tienda 3 únicamente aparecerá ColdExpress cuando el carrito contenga productos de Frescos. |
| **Añadir pickup gratuito para pedidos ligeros** | Peso máx: `2kg`. Acción: `add_ids = [ref_store_pickup]`. | Si el paquete pesa ≤2 kg se añade Click & Collect como opción adicional, pero los carriers originales siguen disponibles. |
| **Forzar DHL exprés y bloquear alternativas** | Zonas: Europa 1. Acción: `allow_ids = [ref_dhl_express]`. | DHL será la única opción para paquetes con destino en la zona Europa 1, garantizando que el cliente no pueda elegir otro transportista. |
| **Muebles pequeños con transportistas estándar** | Contexto: Tienda 2. Categorías: *Muebles pequeños*. Acción: `deny_ids = [ref_armamaster]`. | Los artículos pequeños se envían únicamente con `ref_fastship`, `ref_premiumhome` o `ref_econoline`, dejando fuera al carrier especializado en armado. |
| **Kits para armar solo con técnico especializado** | Contexto: Tienda 2. Categorías: *Muebles para armar*. Acción: `allow_ids = [ref_armamaster]`. | Cuando el carrito contiene un mueble para armar, el sistema limita la lista al transportista `ref_armamaster`, que incluye servicio de montaje. |
| **Añadir montaje opcional para combos mixtos** | Contexto: Tienda 2. Categorías: *Muebles para armar*. Acción: `add_ids = [ref_armamaster]`, `deny_ids = []`. | Si un carrito mezcla piezas pequeñas y kits, se añade `ref_armamaster` aunque no estuviera disponible inicialmente, permitiendo que el cliente elija entre montaje especial o los transportistas estándar. |

## Consejos y depuración

- Tras crear o editar reglas, limpia la caché del back office (`php bin/console cache:clear`) si has modificado servicios relacionados.
- Usa el modo debug (`_PS_MODE_DEV_ = true`) para ver excepciones detalladas cuando una regla referencia un carrier inexistente.
- Si no ves cambios en el checkout, verifica que estés operando sobre la tienda correcta y que el transportista tenga tarifas configuradas para la zona/país. Las reglas no sustituyen a la configuración base de PrestaShop.
- Para pruebas rápidas, carga datos ficticios desde `sample-data/validation_rules.sample.json` mediante el botón de importación en la página de reglas.
- Cuando todas las reglas eliminan los carriers disponibles, el checkout mostrará una alerta amarilla antes del listado indicando si el bloqueo se debe a productos, categorías, países, zonas o peso para que el cliente entienda la restricción.

Con esta referencia puedes diseñar políticas de transporte complejas sin tocar el código: combina condiciones y acciones para adaptar la oferta de carriers a cada contexto de negocio.
