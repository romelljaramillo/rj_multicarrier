-- Active: generate@local
-- Carrier-level configuration key/value pairs
INSERT INTO `ps_rj_multicarrier_carrier_configuration`
  (`id_carrier_configuration`, `id_shop_group`, `id_shop`, `id_carrier`, `id_type_shipment`,
   `name`, `value`, `date_add`, `date_upd`)
VALUES
  (9001, NULL, 1, 3, NULL, 'ACCOUNT_NUMBER', 'CEX-ACC-001', NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 5 DAY),
  (9002, NULL, 1, 3, 3, 'DEFAULT_SERVICE', 'PAQ 24', NOW() - INTERVAL 18 DAY, NOW() - INTERVAL 3 DAY),
  (9003, NULL, 1, 3, NULL, 'LABEL_FORMAT', 'PDF_A4', NOW() - INTERVAL 17 DAY, NOW() - INTERVAL 2 DAY),
  (9004, NULL, 2, 2, 18, 'ACCOUNT_NUMBER', 'DHL-ACC-002', NOW() - INTERVAL 15 DAY, NOW() - INTERVAL 2 DAY),
  (9005, NULL, 2, 2, 18, 'DEFAULT_PRODUCT', 'DHL PARCEL IBERIA', NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 1 DAY),
  (9006, NULL, 2, 4, 19, 'ACCOUNT_NUMBER', 'GOI-ACC-003', NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 1 DAY);

