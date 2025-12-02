<?php
/**
 * Provides the list of payload variables available for request enrichment and response mapping.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Carrier;

final class CarrierPayloadVariableCatalog
{
    /**
     * @return array<int, array{label: string, description?: string, items: array<string, string>}>
     */
    public function getRequestVariables(): array
    {
        return [
            [
                'label' => 'Pedido',
                'description' => 'Campos básicos del pedido y su contexto económico.',
                'items' => [
                    'order.id' => 'ID del pedido',
                    'order.reference' => 'Referencia del pedido',
                    'order.total_paid' => 'Total pagado',
                    'order.currency_iso' => 'Moneda ISO',
                ],
            ],
            [
                'label' => 'Cliente',
                'description' => 'Datos del comprador asociados al pedido.',
                'items' => [
                    'customer.email' => 'Email del cliente',
                    'customer.phone' => 'Teléfono del cliente',
                    'customer.company' => 'Empresa del cliente',
                ],
            ],
            [
                'label' => 'Dirección destino',
                'description' => 'Información logística del destinatario final.',
                'items' => [
                    'address.city' => 'Ciudad destino',
                    'address.postcode' => 'Código postal destino',
                    'address.country_iso' => 'País destino ISO',
                ],
            ],
            [
                'label' => 'Paquete',
                'description' => 'Dimensiones y cantidades declaradas.',
                'items' => [
                    'package.weight' => 'Peso declarado',
                    'package.length' => 'Largo declarado',
                    'package.width' => 'Ancho declarado',
                    'package.height' => 'Alto declarado',
                    'package.quantity' => 'Número de bultos',
                ],
            ],
            [
                'label' => 'Información del envío (registro)',
                'description' => 'Campos guardados en ps_rj_multicarrier_info_shipment que pueden reutilizarse en las peticiones.',
                'items' => [
                    'shipment.id' => 'ID del registro de envío',
                    'shipment.order_id' => 'ID de pedido asociado',
                    'shipment.reference_carrier_id' => 'ID reference carrier (Prestashop)',
                    'shipment.type_shipment_id' => 'ID del tipo de envío vinculado',
                    'shipment.employee_id' => 'Empleado creador',
                    'shipment.logged_employee_id' => 'Empleado autenticado',
                    'shipment.quantity' => 'Número de paquetes registrado',
                    'shipment.weight' => 'Peso total registrado',
                    'shipment.length' => 'Largo total registrado',
                    'shipment.width' => 'Ancho total registrado',
                    'shipment.height' => 'Alto total registrado',
                    'shipment.cash_on_delivery' => 'Importe de contrareembolso',
                    'shipment.message' => 'Mensaje al transportista',
                    'shipment.hour_from' => 'Franja horaria desde',
                    'shipment.hour_until' => 'Franja horaria hasta',
                    'shipment.retour' => 'Retornos/recogidas solicitadas (retorno)',
                    'shipment.rcs' => 'Seguro RCS activado',
                    'shipment.vsec' => 'Valor asegurado (VSEC)',
                    'shipment.origin_code' => 'Código de origen (dorig)',
                    'shipment.created_at' => 'Fecha de creación del envío',
                    'shipment.updated_at' => 'Fecha de actualización',
                ],
            ],
            [
                'label' => 'Tienda',
                'description' => 'Identificadores y contactos de la tienda que envía.',
                'items' => [
                    'shop.name' => 'Nombre de la tienda',
                    'shop.email' => 'Email de la tienda',
                    'shop.phone' => 'Teléfono de la tienda',
                ],
            ],
            [
                'label' => 'Remitente configurado',
                'description' => 'Datos almacenados en ps_rj_multicarrier_configuration para generar las etiquetas.',
                'items' => [
                    'config.firstname' => 'Nombre del remitente',
                    'config.lastname' => 'Apellidos del remitente',
                    'config.company' => 'Compañía remitente',
                    'config.additionalname' => 'Departamento / contacto adicional',
                    'config.id_country' => 'ID de país remitente',
                    'config.state' => 'Provincia / estado remitente',
                    'config.city' => 'Ciudad remitente',
                    'config.street' => 'Calle remitente',
                    'config.number' => 'Número de puerta remitente',
                    'config.postcode' => 'Código postal remitente',
                    'config.additionaladdress' => 'Información adicional de dirección',
                    'config.isbusiness' => '¿Es dirección empresarial?',
                    'config.email' => 'Email remitente',
                    'config.phone' => 'Teléfono remitente',
                    'config.vatnumber' => 'VAT/NIF remitente',
                    'config.label_prefix' => 'Prefijo de etiqueta configurado',
                    'config.cod_module' => 'Módulo de contrareembolso configurado',
                ],
            ],
            [
                'label' => 'Configuraciones',
                'description' => 'Variables provenientes de configuraciones del módulo.',
                'items' => [
                    'config.RJ_ETIQUETA_TRANSP_PREFIX' => 'Prefijo etiqueta configurado',
                    'config.RJ_MODULE_CONTRAREEMBOLSO' => 'Módulo contrareembolso',
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getResponseVariables(): array
    {
        return [
            'tracking_number' => 'Número de seguimiento',
            'tracking_url' => 'URL de seguimiento',
            'label_pdf' => 'Etiqueta PDF codificada',
            'label_zpl' => 'Etiqueta ZPL',
            'pickup_code' => 'Código de recogida',
            'depot_code' => 'Código de delegación',
            'api.raw' => 'Respuesta completa de la API',
        ];
    }
}
