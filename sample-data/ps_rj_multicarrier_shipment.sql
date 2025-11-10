-- Active: generate@local
-- Fake shipments linked to the sample info packages
INSERT INTO `ps_rj_multicarrier_shipment`
  (`id_shipment`, `id_order`, `reference_order`, `num_shipment`, `id_carrier`, `id_info_shipment`,
   `account`, `product`, `request`, `response`, `delete`, `date_add`, `date_upd`)
VALUES
  (6001, 108001, 'WEB-108001', 'CEX-108001-01', 1, 5001,
   'CEX-ACC-001', 'Premium 14h', '{"action":"create","packages":2,"weight":1.8}', '{"status":"ok","tracking":"CEX-108001-01"}', 0, NOW() - INTERVAL 14 DAY + INTERVAL 3 HOUR, NOW() - INTERVAL 13 DAY),
  (6002, 108002, 'WEB-108002', 'CEX-108002-01', 1, 5002,
   'CEX-ACC-001', 'Express 24h', '{"action":"create","packages":1,"weight":0.95}', '{"status":"ok","tracking":"CEX-108002-01"}', 0, NOW() - INTERVAL 13 DAY + INTERVAL 6 HOUR, NOW() - INTERVAL 12 DAY + INTERVAL 23 HOUR),
  (6003, 108003, 'B2B-108003', 'DHL-108003-01', 2, 5003,
   'DHL-ACC-002', 'Economy', '{"action":"create","packages":3,"weight":4.2}', '{"status":"ok","tracking":"DHL-108003-01"}', 0, NOW() - INTERVAL 12 DAY + INTERVAL 8 HOUR, NOW() - INTERVAL 11 DAY + INTERVAL 10 HOUR),
  (6004, 108004, 'B2B-108004', 'DHL-108004-01', 2, 5004,
   'DHL-ACC-002', 'Express', '{"action":"create","packages":1,"insurance":150}', '{"status":"warning","message":"manual_review"}', 0, NOW() - INTERVAL 11 DAY + INTERVAL 2 HOUR, NOW() - INTERVAL 9 DAY + INTERVAL 18 HOUR),
  (6005, 108005, 'ERP-108005', 'GOI-108005-01', 3, 5005,
   'GOI-ACC-003', 'Standard', '{"action":"create","packages":4,"weight":7.4}', '{"status":"ok","tracking":"GOI-108005-01"}', 0, NOW() - INTERVAL 10 DAY + INTERVAL 5 HOUR, NOW() - INTERVAL 8 DAY + INTERVAL 22 HOUR),
  (6006, 108006, 'ERP-108006', 'GOI-108006-01', 3, 5006,
   'GOI-ACC-003', 'Secure', '{"action":"create","packages":2,"insurance":320}', '{"status":"ok","tracking":"GOI-108006-01"}', 0, NOW() - INTERVAL 9 DAY + INTERVAL 4 HOUR, NOW() - INTERVAL 7 DAY + INTERVAL 21 HOUR),
  (6007, 108007, 'POS-108007', 'CEX-108007-01', 1, 5007,
   'CEX-ACC-004', 'Citysame', '{"action":"create","packages":1,"note":"reception"}', '{"status":"ok","tracking":"CEX-108007-01"}', 0, NOW() - INTERVAL 8 DAY + INTERVAL 7 HOUR, NOW() - INTERVAL 6 DAY + INTERVAL 16 HOUR),
  (6008, 108008, 'POS-108008', 'CEX-108008-01', 1, 5008,
   'CEX-ACC-004', 'Electro secure', '{"action":"create","packages":2,"contents":"electronics"}', '{"status":"ok","tracking":"CEX-108008-01"}', 0, NOW() - INTERVAL 7 DAY + INTERVAL 3 HOUR, NOW() - INTERVAL 5 DAY + INTERVAL 18 HOUR),
  (6009, 108009, 'WEB-108009', 'CEX-108009-01', 1, 5009,
   'CEX-ACC-001', 'Sample', '{"action":"create","packages":1,"weight":0.65}', '{"status":"ok","tracking":"CEX-108009-01"}', 0, NOW() - INTERVAL 6 DAY + INTERVAL 10 HOUR, NOW() - INTERVAL 4 DAY + INTERVAL 22 HOUR),
  (6010, 108010, 'WEB-108010', 'DHL-108010-01', 2, 5010,
   'DHL-ACC-002', 'Expo', '{"action":"create","packages":5,"insurance":480}', '{"status":"ok","tracking":"DHL-108010-01"}', 0, NOW() - INTERVAL 5 DAY + INTERVAL 2 HOUR, NOW() - INTERVAL 3 DAY + INTERVAL 19 HOUR);
