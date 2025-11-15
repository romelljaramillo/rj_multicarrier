-- Active: 1762970070909@@localhost@3306@gcroma
-- Reglas de validación generadas dinámicamente con transportistas nativos de PrestaShop
INSERT INTO `ps_rj_multicarrier_validation_rule`
	(`name`, `priority`, `active`, `shop_id`, `shop_group_id`, `product_ids`, `category_ids`, `zone_ids`, `country_ids`, `min_weight`, `max_weight`, `allow_ids`, `deny_ids`, `add_ids`, `prefer_ids`, `created_at`, `updated_at`)
VALUES
	(
		'Regla - Moda ligera Europa',
		100,
		1,
		NULL,
		NULL,
		NULL,
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(DISTINCT c.id_category ORDER BY c.id_category SEPARATOR ','), ']')
			FROM ps_category c
			INNER JOIN ps_category_lang cl ON cl.id_category = c.id_category AND cl.id_lang = 1
			WHERE c.active = 1 AND cl.name IN ('Men', 'Women')
		), '[]'),
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_zone ORDER BY id_zone SEPARATOR ','), ']')
			FROM ps_zone
			WHERE active = 1 AND name IN ('Europe')
		), '[]'),
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_country ORDER BY id_country SEPARATOR ','), ']')
			FROM ps_country
			WHERE active = 1 AND iso_code IN ('ES')
		), '[]'),
		NULL,
		3.0,
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_carrier ORDER BY id_carrier SEPARATOR ','), ']')
			FROM ps_carrier
			WHERE deleted = 0 AND name IN ('My carrier', 'My cheap carrier')
		), '[]'),
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_carrier ORDER BY id_carrier SEPARATOR ','), ']')
			FROM ps_carrier
			WHERE deleted = 0 AND name IN ('Click and collect')
		), '[]'),
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_carrier ORDER BY id_carrier SEPARATOR ','), ']')
			FROM ps_carrier
			WHERE deleted = 0 AND name IN ('My cheap carrier')
		), '[]'),
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_carrier ORDER BY id_carrier SEPARATOR ','), ']')
			FROM ps_carrier
			WHERE deleted = 0 AND name IN ('My cheap carrier')
		), '[]'),
		NOW(),
		NOW()
	),
	(
		'Regla - Posters recogida local',
		110,
		1,
		NULL,
		NULL,
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(DISTINCT p.id_product ORDER BY p.id_product SEPARATOR ','), ']')
			FROM ps_product p
			WHERE p.active = 1 AND p.reference IN ('demo_6', 'demo_5', 'demo_7')
		), '[]'),
		NULL,
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_zone ORDER BY id_zone SEPARATOR ','), ']')
			FROM ps_zone
			WHERE active = 1 AND name IN ('Europe')
		), '[]'),
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_country ORDER BY id_country SEPARATOR ','), ']')
			FROM ps_country
			WHERE active = 1 AND iso_code IN ('ES')
		), '[]'),
		NULL,
		NULL,
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_carrier ORDER BY id_carrier SEPARATOR ','), ']')
			FROM ps_carrier
			WHERE deleted = 0 AND name IN ('Click and collect', 'My carrier')
		), '[]'),
		NULL,
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_carrier ORDER BY id_carrier SEPARATOR ','), ']')
			FROM ps_carrier
			WHERE deleted = 0 AND name IN ('Click and collect')
		), '[]'),
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_carrier ORDER BY id_carrier SEPARATOR ','), ']')
			FROM ps_carrier
			WHERE deleted = 0 AND name IN ('Click and collect')
		), '[]'),
		NOW(),
		NOW()
	),
	(
		'Regla - Envíos USA solo estándar',
		120,
		1,
		NULL,
		NULL,
		NULL,
		NULL,
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_zone ORDER BY id_zone SEPARATOR ','), ']')
			FROM ps_zone
			WHERE active = 1 AND name IN ('North America')
		), '[]'),
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_country ORDER BY id_country SEPARATOR ','), ']')
			FROM ps_country
			WHERE active = 1 AND iso_code IN ('US')
		), '[]'),
		NULL,
		NULL,
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_carrier ORDER BY id_carrier SEPARATOR ','), ']')
			FROM ps_carrier
			WHERE deleted = 0 AND name IN ('My carrier')
		), '[]'),
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_carrier ORDER BY id_carrier SEPARATOR ','), ']')
			FROM ps_carrier
			WHERE deleted = 0 AND name IN ('Click and collect', 'My cheap carrier')
		), '[]'),
		NULL,
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_carrier ORDER BY id_carrier SEPARATOR ','), ']')
			FROM ps_carrier
			WHERE deleted = 0 AND name IN ('My carrier')
		), '[]'),
		NOW(),
		NOW()
	),
	(
		'Regla - Mugs personalizados premium',
		130,
		1,
		NULL,
		NULL,
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(DISTINCT p.id_product ORDER BY p.id_product SEPARATOR ','), ']')
			FROM ps_product p
			WHERE p.active = 1 AND p.reference IN ('demo_14')
		), '[]'),
		NULL,
		NULL,
		NULL,
		NULL,
		NULL,
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_carrier ORDER BY id_carrier SEPARATOR ','), ']')
			FROM ps_carrier
			WHERE deleted = 0 AND name IN ('My carrier')
		), '[]'),
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_carrier ORDER BY id_carrier SEPARATOR ','), ']')
			FROM ps_carrier
			WHERE deleted = 0 AND name IN ('My cheap carrier')
		), '[]'),
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_carrier ORDER BY id_carrier SEPARATOR ','), ']')
			FROM ps_carrier
			WHERE deleted = 0 AND name IN ('My carrier')
		), '[]'),
		COALESCE((
			SELECT CONCAT('[', GROUP_CONCAT(id_carrier ORDER BY id_carrier SEPARATOR ','), ']')
			FROM ps_carrier
			WHERE deleted = 0 AND name IN ('My carrier')
		), '[]'),
		NOW(),
		NOW()
	);
