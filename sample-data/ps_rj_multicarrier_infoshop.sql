-- Active: generate@local
-- Sender (shop) metadata for multicarrier module
INSERT INTO `ps_rj_multicarrier_infoshop`
  (`id_infoshop`, `firstname`, `lastname`, `company`, `additionalname`, `id_country`, `state`, `city`, `street`, `number`,
   `postcode`, `additionaladdress`, `isbusiness`, `email`, `phone`, `vatnumber`, `date_add`, `date_upd`)
VALUES
  (7001, 'Ana', 'López', 'GCromo S.L.', 'Dpto. Logística', 6, 'Madrid', 'Madrid', 'Calle Mayor', '15',
   '28013', '3ºB', '1', 'ana.logistica@gcromo.es', '+34 910 000 001', 'ESB12345678', NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 5 DAY),
  (7002, 'Bruno', 'Silva', 'GCromo Portugal Lda.', NULL, 173, 'Lisboa', 'Lisbon', 'Rua Augusta', '102',
   '1100-048', NULL, '1', 'porto@gcromo.pt', '+351 210 000 002', 'PT500123456', NOW() - INTERVAL 18 DAY, NOW() - INTERVAL 3 DAY);
