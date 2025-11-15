<?php
class Cart extends CartCore
{


	public function getPackageList($flush = false)
    {
        $cache_key = (int) $this->id . '_' . (int) $this->id_address_delivery;
        if (isset(static::$cachePackageList[$cache_key]) && static::$cachePackageList[$cache_key] !== false && !$flush) {
            return static::$cachePackageList[$cache_key];
        }
        $product_list = $this->getProducts($flush);
        $warehouse_count_by_address = [];
        $stock_management_active = Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT');
        foreach ($product_list as &$product) {
            if ((int) $product['id_address_delivery'] == 0) {
                $product['id_address_delivery'] = (int) $this->id_address_delivery;
            }
            if (!isset($warehouse_count_by_address[$product['id_address_delivery']])) {
                $warehouse_count_by_address[$product['id_address_delivery']] = [];
            }
            $product['warehouse_list'] = [];
            if ($stock_management_active &&
                (int) $product['advanced_stock_management'] == 1) {
                $warehouse_list = Warehouse::getProductWarehouseList($product['id_product'], $product['id_product_attribute'], $this->id_shop);
                if (count($warehouse_list) == 0) {
                    $warehouse_list = Warehouse::getProductWarehouseList($product['id_product'], $product['id_product_attribute']);
                }
                $warehouse_in_stock = [];
                $manager = StockManagerFactory::getManager();
                foreach ($warehouse_list as $key => $warehouse) {
                    $product_real_quantities = $manager->getProductRealQuantities(
                        $product['id_product'],
                        $product['id_product_attribute'],
                        [$warehouse['id_warehouse']],
                        true
                    );
                    if ($product_real_quantities > 0 || Pack::isPack((int) $product['id_product'])) {
                        $warehouse_in_stock[] = $warehouse;
                    }
                }
                if (!empty($warehouse_in_stock)) {
                    $warehouse_list = $warehouse_in_stock;
                    $product['in_stock'] = true;
                } else {
                    $product['in_stock'] = false;
                }
            } else {
                $warehouse_list = [0 => ['id_warehouse' => 0]];
                $product['in_stock'] = StockAvailable::getQuantityAvailableByProduct($product['id_product'], $product['id_product_attribute']) > 0;
            }
            foreach ($warehouse_list as $warehouse) {
                $product['warehouse_list'][$warehouse['id_warehouse']] = $warehouse['id_warehouse'];
                if (!isset($warehouse_count_by_address[$product['id_address_delivery']][$warehouse['id_warehouse']])) {
                    $warehouse_count_by_address[$product['id_address_delivery']][$warehouse['id_warehouse']] = 0;
                }
                ++$warehouse_count_by_address[$product['id_address_delivery']][$warehouse['id_warehouse']];
            }
        }
        unset($product);
        arsort($warehouse_count_by_address);
        $grouped_by_warehouse = [];
        foreach ($product_list as &$product) {
            if (!isset($grouped_by_warehouse[$product['id_address_delivery']])) {
                $grouped_by_warehouse[$product['id_address_delivery']] = [
                    'in_stock' => [],
                    'out_of_stock' => [],
                ];
            }
            $product['carrier_list'] = [];
            $id_warehouse = 0;
            foreach ($warehouse_count_by_address[$product['id_address_delivery']] as $id_war => $val) {
                if (array_key_exists((int) $id_war, $product['warehouse_list'])) {
                    $product['carrier_list'] = array_replace($product['carrier_list'], Carrier::getAvailableCarrierList(new Product($product['id_product']), $id_war, $product['id_address_delivery'], null, $this));
                    if (!$id_warehouse) {
                        $id_warehouse = (int) $id_war;
                    }
                }
            }
            if (!isset($grouped_by_warehouse[$product['id_address_delivery']]['in_stock'][$id_warehouse])) {
                $grouped_by_warehouse[$product['id_address_delivery']]['in_stock'][$id_warehouse] = [];
                $grouped_by_warehouse[$product['id_address_delivery']]['out_of_stock'][$id_warehouse] = [];
            }
            if (!$this->allow_seperated_package) {
                $key = 'in_stock';
            } else {
                $key = $product['in_stock'] ? 'in_stock' : 'out_of_stock';
                $product_quantity_in_stock = StockAvailable::getQuantityAvailableByProduct($product['id_product'], $product['id_product_attribute']);
                if ($product['in_stock'] && $product['cart_quantity'] > $product_quantity_in_stock) {
                    $out_stock_part = $product['cart_quantity'] - $product_quantity_in_stock;
                    $product_bis = $product;
                    $product_bis['cart_quantity'] = $out_stock_part;
                    $product_bis['in_stock'] = 0;
                    $product['cart_quantity'] -= $out_stock_part;
                    $grouped_by_warehouse[$product['id_address_delivery']]['out_of_stock'][$id_warehouse][] = $product_bis;
                }
            }
            if (empty($product['carrier_list'])) {
                $product['carrier_list'] = [0 => 0];
            }
            $grouped_by_warehouse[$product['id_address_delivery']][$key][$id_warehouse][] = $product;
        }
        unset($product);
		$array_product=$product_list;
        $grouped_by_carriers = [];
        foreach ($grouped_by_warehouse as $id_address_delivery => $products_in_stock_list) {
            if (!isset($grouped_by_carriers[$id_address_delivery])) {
                $grouped_by_carriers[$id_address_delivery] = [
                    'in_stock' => [],
                    'out_of_stock' => [],
                ];
            }
            foreach ($products_in_stock_list as $key => $warehouse_list) {
                if (!isset($grouped_by_carriers[$id_address_delivery][$key])) {
                    $grouped_by_carriers[$id_address_delivery][$key] = [];
                }
                foreach ($warehouse_list as $id_warehouse => $product_list) {
                    if (!isset($grouped_by_carriers[$id_address_delivery][$key][$id_warehouse])) {
                        $grouped_by_carriers[$id_address_delivery][$key][$id_warehouse] = [];
                    }
                    foreach ($product_list as $product) {
                        $package_carriers_key = implode(',', $product['carrier_list']);
                        if (!isset($grouped_by_carriers[$id_address_delivery][$key][$id_warehouse][$package_carriers_key])) {
                            $grouped_by_carriers[$id_address_delivery][$key][$id_warehouse][$package_carriers_key] = [
                                'product_list' => [],
                                'carrier_list' => $product['carrier_list'],
                                'warehouse_list' => $product['warehouse_list'],
                            ];
                        }
                        $grouped_by_carriers[$id_address_delivery][$key][$id_warehouse][$package_carriers_key]['product_list'][] = $product;
                    }
                }
            }
        }
		$encontrado = false;
		$encontradoamedida=false;
		$encontradotapizado=false;
		$encontradosofa=false;
        $encontradocamas=false;
		$encontradomontaje = false;
        $encontradorecogida = false;
		$trans1=' ';
		$trans2=' ';
		$trans3=' ';
		$trans4=' ';
		$trans5=' ';
		$trans6=' ';
		$trans7=' ';
		$trans8=' ';
        $package_list = [];
        foreach ($grouped_by_carriers as $id_address_delivery => $products_in_stock_list) {
            if (!isset($package_list[$id_address_delivery])) {
                $package_list[$id_address_delivery] = [
                    'in_stock' => [],
                    'out_of_stock' => [],
                ];
            }
            foreach ($products_in_stock_list as $key => $warehouse_list) {
                if (!isset($package_list[$id_address_delivery][$key])) {
                    $package_list[$id_address_delivery][$key] = [];
                }
                $carrier_count = [];
                foreach ($warehouse_list as $id_warehouse => $products_grouped_by_carriers) {
                    foreach ($products_grouped_by_carriers as $data) {
                        foreach ($data['carrier_list'] as $id_carrier) {
                            if (!isset($carrier_count[$id_carrier])) {
                                $carrier_count[$id_carrier] = 0;
                            }
                            ++$carrier_count[$id_carrier];
                        }
                    }
                }
                arsort($carrier_count);
                foreach ($warehouse_list as $id_warehouse => $products_grouped_by_carriers) {
                    if (!isset($package_list[$id_address_delivery][$key][$id_warehouse])) {
                        $package_list[$id_address_delivery][$key][$id_warehouse] = [];
                    }
                    foreach ($products_grouped_by_carriers as $data) {
                        foreach ($carrier_count as $id_carrier => $rate) {
                            if (array_key_exists($id_carrier, $data['carrier_list'])) {
                                if (!isset($package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier])) {
                                    $package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier] = [
                                        'carrier_list' => $data['carrier_list'],
                                        'warehouse_list' => $data['warehouse_list'],
                                        'product_list' => [],
                                    ];
                                }
                                $package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier]['carrier_list'] =
                                    array_intersect($package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier]['carrier_list'], $data['carrier_list']);
                                $package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier]['product_list'] =
                                    array_merge($package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier]['product_list'], $data['product_list']);
								$trans1=$id_address_delivery;
								$trans2=$key;
								$trans3=$id_warehouse;
								$trans4=$id_carrier;
                                break;
                            }
                        }
                    }
                }
            }
			$prodamedida=array(356,359,361,365,366,371,372,373,374,375,465,484,485,500,509,532,549,550,554,566,638,843,4140,5787,5856,5903,6016,6067,6160,9968,9981,10000,10002,10010,10028,10306,16071,16198);


            $res_oferton_tapizados = Db::getInstance()->executeS('Select id_product from `' . _DB_PREFIX_ . 'product` where id_category_default IN (185,186) AND active =1');
            // Extraer solo los id_product de las categorias Outlet Bases Tapizadas y Somieres y Outlet Cabeceros
            $ids_from_tapizados_oferton = array_column($res_oferton_tapizados, 'id_product');
			$prodtapizado=array(77,79,80,81,368,375,377,385,387,498,539,540,612,634,3234,3849,5328,10203,10301,10302,10303,10326,10327,14316,14341);
            // Unir los 2 arrays
            $prodtapizado=array_merge($prodtapizado, $ids_from_tapizados_oferton);


			$prodsofas=array(442,453,3028,10553);

            $res_oferton_canape = Db::getInstance()->executeS('Select id_product from `' . _DB_PREFIX_ . 'product` where id_category_default=187 AND active =1');
            // Extraer solo los id_product de la categoria Outlet Canapés
            $ids_from_canapes_oferton = array_column($res_oferton_canape, 'id_product');
			$prodcanape=array(481,533,543,544,596,638,2663,2678,3015,5520,10022,10033,10072,10073,10074,10075,10076,10077,10078,10079,10080,10081,10120,10369,10407,12114,15799);
            // Unir los 2 arrays
            $prodcanape=array_merge($prodcanape, $ids_from_canapes_oferton);
            // PrestaShopLogger::addLog('Valores del array $prodcanape2: ' . implode(',', $prodcanape2), 3);

            $prodcamas=array(101,493,5998,10504);
            // productos para que aparezca el montaje cuando va con cabeceros
			$prodmontaje=array(13,14,33,75,460,491,5817,6082,6157,9183,9184,10063,10151,10274,10296,10318,612,3234,509,597);
            $prodrecogida=array(13,14,19,21,33,75,356,359,361,363,365,372,373,460,464,484,485,491,5817,6082,6157,9183,9184,10026,10027,10028,10063,10092,10151,10274,10276,10296,10318,10340,15632);

			foreach ($array_product as $product) {

				if (in_array($product['id_product'], $prodsofas)){
					$encontradosofa=true;
				}
				if (in_array($product['id_product'], $prodcanape)){
					$encontrado=true;
				}else{
					$res_prod = Db::getInstance()->getValue('Select id_producto from `' . _DB_PREFIX_ . 'idxrcustomproduct_clones` where id_clon=' . $product['id_product']);
					if((int)$res_prod>0){
						if (in_array($res_prod, $prodcanape))
							$encontrado=true;
					}
				}
				if(in_array($product['id_product'], $prodamedida)){
					$encontradoamedida=true;
				}
				if(in_array($product['id_product'], $prodtapizado)){
					$encontradotapizado=true;
				}
                if(in_array($product['id_product'], $prodcamas)){
					$encontradocamas=true;
				}else{
					$res_prod = Db::getInstance()->getValue('Select id_producto from `' . _DB_PREFIX_ . 'idxrcustomproduct_clones` where id_clon=' . $product['id_product']);
					if((int)$res_prod>0){
						if (in_array($res_prod, $prodcamas))
							$encontradocamas=true;
					}
				}
				if (in_array($product['id_product'], $prodmontaje)) {
					$encontradomontaje = true;
				}
                if (in_array($product['id_product'], $prodrecogida)) {
					$encontradorecogida = true;
				}
			}

			$madrid = false;
			$balearessm = false;
            $baleares = false;
			$ciudad_real = false;
			$canarias = false;
			$ceutaymelilla = false;
			$formentera = false;
			$portugal = false;
			if (isset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'])){
				if (is_array($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']) || is_object($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']))
				{
					foreach ($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] as $carrier){
						$id_zone = Address::getZoneById((int) $trans1);
                        //PrestaShopLogger::addLog('Zona baleares: ' . $id_zone);
						if($id_zone==14){
							$madrid = true;
						}
						if($id_zone==13){
							$ciudad_real = true;
						}
						if($id_zone==11){
							$balearessm = true;
						}
                        if($id_zone==66){
							$baleares = true;
						}
						if($id_zone==10){
							$canarias = true;
						}
						if($id_zone==12){
							$ceutaymelilla = true;
						}
						if($id_zone==65){
							$formentera = true;
						}
						if($id_zone==58){
							$portugal = true;
						}
					}
				}
			}
			if ($encontradosofa){
				if (!$madrid && !$baleares && !$balearessm && !$ciudad_real && !$canarias && !$ceutaymelilla && !$formentera && !$portugal){
					$trans_nuevos = array(431,434);
					$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
					$clave = array_search("416", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
					if($clave)
						unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
				}
				if ($madrid){
					$trans_nuevos = array(434);
					$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
				}
				if ($ciudad_real){
				}
				if ($baleares){
					$trans_nuevos = array(434);
					$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
					$clave = array_search("416", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
					if($clave)
						unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
				}
			}else{
				if (!$madrid && !$baleares && !$balearessm && !$ciudad_real && !$canarias && !$ceutaymelilla && !$formentera && !$portugal){
					$trans_nuevos = array();
					if ($encontrado && $encontradorecogida){
						$clave = array_search("400", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						if($clave)
							unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
						$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						if($clave)
							unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
						$trans_nuevos = array(417,423,431,433);
						$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
					}elseif ($encontrado && !$encontradorecogida){
						$clave = array_search("400", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						if($clave)
							unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
						$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						if($clave)
							unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
						$trans_nuevos = array(417,423,431);
						$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
					}elseif($encontradocamas && $encontradorecogida){
						$clave = array_search("416", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
							$clave = array_search("400", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
							$trans_nuevos = array(512,432);
							$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
					}elseif($encontradocamas && !$encontradorecogida){
						$clave = array_search("416", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
							$clave = array_search("400", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
							$trans_nuevos = array(512);
							$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
					}else{
						if ($encontradoamedida && $encontradorecogida){
							$clave = array_search("400", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave)
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave)
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							$trans_nuevos = array(422,428,432);
							$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						}elseif ($encontradoamedida && !$encontradorecogida){
							$clave = array_search("400", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave)
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave)
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							$trans_nuevos = array(422,428);
							$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						}
						if ($encontradotapizado && $encontradomontaje) {
                                if ($encontradorecogida) {
                                    $trans_nuevos = array(432);
                                    $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos, $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
                                }
							$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if ($clave)
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							$clave = array_search("428", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if ($clave) {
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
							$trans_nuevos = array(416,431);
							$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos, $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						}else{
                            if ($encontradotapizado){
                                $clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
                                if($clave)
                                    unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
                                $clave = array_search("428", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
                                if($clave){
                                    unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
                                }
                                $trans_nuevos = array(431);
                                $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
                            }
                        }
                        if ($encontradorecogida){
                            $trans_nuevos = array(432);
                            $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						}
					}
				}
				if ($balearessm){
					$trans_nuevos = array();
					if ($encontrado && $encontradorecogida){
						$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						if($clave)
							unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
						$trans_nuevos = array(417,433);
						$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
					}elseif ($encontrado && !$encontradorecogida){
						$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						if($clave)
							unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
						$trans_nuevos = array(417);
						$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
					}else{
						if ($encontradoamedida && $encontradorecogida){
							$clave = array_search("400", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave)
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave)
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
                            $trans_nuevos = array(432);
						    $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						}elseif ($encontradoamedida && !$encontradorecogida){
							$clave = array_search("400", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave)
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave)
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
						}
						if ($encontradotapizado && $encontradorecogida){
							$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
                            $trans_nuevos = array(432);
						    $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						}elseif ($encontradotapizado && !$encontradorecogida){
							$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
						}
                        if ($encontradocamas && $encontradorecogida){
							$clave = array_search("416", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
							$clave = array_search("398", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
							$trans_nuevos = array(512,432);
							$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						}elseif ($encontradocamas && !$encontradorecogida){
							$clave = array_search("416", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
							$clave = array_search("398", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
							$trans_nuevos = array(512);
							$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						}
                        if ($encontradorecogida){
                            $trans_nuevos = array(432);
                            $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						}
					}
				}
				if($ciudad_real){
					$trans_nuevos = array();
					if ($encontrado){
						$clave = array_search("400", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						if($clave)
							unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
						$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						if($clave)
							unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
						$clave = array_search("423", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						if($clave)
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);


					}else{
						if ($encontradoamedida){
							$clave = array_search("400", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
							$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
							$trans_nuevos = array(422);
							$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						}
						if ($encontradotapizado){

						}
					}
				}
				if($madrid){
					$trans_nuevos = array();
					if ($encontrado){
						$clave = array_search("400", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						if($clave)
							unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
						$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						if($clave)
							unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
						$trans_nuevos = array(423);
						$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);

					}else{
						if ($encontradoamedida){
							$clave = array_search("400", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
							$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
							$trans_nuevos = array(422,428);
							$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						}
						if ($encontradotapizado){
							$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave)
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							$clave = array_search("428", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave){
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							}
							$trans_nuevos = array(431);
							$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						}
					}
				}
				if($portugal){
					$trans_nuevos = array();
					if ($encontrado){
						$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						if($clave)
							unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
						$trans_nuevos = array(431);
						$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);

					} else {
						if ($encontradotapizado){
							$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave)
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							$trans_nuevos = array(431);
							$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						}
						if ($encontradoamedida){
							$clave = array_search("400", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave)
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							$clave = array_search("419", $package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
							if($clave)
								unset($package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'][$clave]);
							$trans_nuevos = array(422,428);
							$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list'] = array_merge($trans_nuevos,$package_list[$trans1][$trans2][$trans3][$trans4]['carrier_list']);
						}
					}
				}

			}


        }
        $final_package_list = [];
        foreach ($package_list as $id_address_delivery => $products_in_stock_list) {
            if (!isset($final_package_list[$id_address_delivery])) {
                $final_package_list[$id_address_delivery] = [];
            }
            foreach ($products_in_stock_list as $key => $warehouse_list) {
                foreach ($warehouse_list as $id_warehouse => $products_grouped_by_carriers) {
                    foreach ($products_grouped_by_carriers as $data) {
                        $final_package_list[$id_address_delivery][] = [
                            'product_list' => $data['product_list'],
                            'carrier_list' => $data['carrier_list'],
                            'warehouse_list' => $data['warehouse_list'],
                            'id_warehouse' => $id_warehouse,
                        ];
                    }
                }
            }
        }
        static::$cachePackageList[$cache_key] = $final_package_list;
        return $final_package_list;
    }

    /*
    * module: onepagecheckoutps
    * date: 2022-12-12 13:11:37
    * version: 4.1.5
    */
    public function getTotalShippingCost($delivery_option = null, $use_tax = true, Country $default_country = null)
    {
        if (version_compare(_PS_VERSION_, '1.7.4.0') < 0) {
            static $_total_shipping;
            $opc = Module::getInstanceByName('onepagecheckoutps');
            if (Validate::isLoadedObject($opc)
                && Context::getContext()->customer->isLogged()
                && (int) Context::getContext()->customer->id === (int) $opc->config_vars['OPC_ID_CUSTOMER']
            ) {
                $_total_shipping = null;
            }
            if (null === $_total_shipping) {
                if (isset(Context::getContext()->cookie->id_country)) {
                    $default_country = new Country(Context::getContext()->cookie->id_country);
                }
                if (is_null($delivery_option)) {
                    $delivery_option = $this->getDeliveryOption($default_country, false, false);
                }
                $_total_shipping = array(
                    'with_tax' => 0,
                    'without_tax' => 0,
                );
                $delivery_option_list = $this->getDeliveryOptionList($default_country);
                foreach ($delivery_option as $id_address => $key) {
                    if (!isset($delivery_option_list[$id_address])
                        || !isset($delivery_option_list[$id_address][$key])
                    ) {
                        continue;
                    }
                    $_total_shipping['with_tax'] += $delivery_option_list[$id_address][$key]['total_price_with_tax'];
                    $_total_shipping['without_tax'] += $delivery_option_list[$id_address][$key]['total_price_without_tax'];
                }
            }
            return ($use_tax) ? $_total_shipping['with_tax'] : $_total_shipping['without_tax'];
        }
        return parent::getTotalShippingCost($delivery_option, $use_tax, $default_country);
    }
    /*
    * module: onepagecheckoutps
    * date: 2022-12-12 13:11:37
    * version: 4.1.5
    */
    public function getDeliveryOptionList(Country $default_country = null, $flush = false)
    {
        if (version_compare(_PS_VERSION_, '1.7.4.0') < 0) {
            $opc = Module::getInstanceByName('onepagecheckoutps');
            if (Validate::isLoadedObject($opc)
                && Context::getContext()->customer->isLogged()
                && (int) Context::getContext()->customer->id === (int) $opc->config_vars['OPC_ID_CUSTOMER']
            ) {
                $flush = true;
            }
        }
        return parent::getDeliveryOptionList($default_country, $flush);
    }















    /*
    * module: quantitydiscountpro
    * date: 2024-07-24 13:24:00
    * version: 2.1.47
    */
    public function addCartRule($id_cart_rule, bool $useOrderPrices = false)
    {
        $result = parent::addCartRule($id_cart_rule, $useOrderPrices);
        if (Module::isEnabled('quantitydiscountpro')) {
            include_once _PS_MODULE_DIR_ . 'quantitydiscountpro/quantitydiscountpro.php';
            $quantityDiscountRulesAtCart = QuantityDiscountRule::getQuantityDiscountRulesAtCart((int) Context::getContext()->cart->id);
            if (is_array($quantityDiscountRulesAtCart) && count($quantityDiscountRulesAtCart)) {
                foreach ($quantityDiscountRulesAtCart as $quantityDiscountRuleAtCart) {
                    $quantityDiscountRuleAtCartObj = new QuantityDiscountRule((int) $quantityDiscountRuleAtCart['id_quantity_discount_rule']);
                    if (!$quantityDiscountRuleAtCartObj->compatibleCartRules()) {
                        QuantityDiscountRule::removeQuantityDiscountCartRule($quantityDiscountRuleAtCart['id_cart_rule'], (int) Context::getContext()->cart->id);
                    }
                }
            }
        }
        return $result;
    }
    /*
    * module: quantitydiscountpro
    * date: 2024-07-24 13:24:00
    * version: 2.1.47
    */
    public function getCartRules($filter = CartRule::FILTER_ACTION_ALL, $autoAdd = true, $useOrderPrices = false)
    {
        $cartRules = parent::getCartRules($filter, $autoAdd, $useOrderPrices);
        if (Module::isEnabled('quantitydiscountpro')) {
            include_once _PS_MODULE_DIR_ . 'quantitydiscountpro/quantitydiscountpro.php';
            foreach ($cartRules as &$cartRule) {
                if (QuantityDiscountRule::isQuantityDiscountRule($cartRule['id_cart_rule'])
                    && !QuantityDiscountRule::isQuantityDiscountRuleWithCode($cartRule['id_cart_rule'])) {
                    $cartRule['code'] = '';
                }
            }
            unset($cartRule);
        }
        return $cartRules;
    }























	public function duplicate()
    {
        $id_cart_old = (int) $this->id;
        $result = parent::duplicate();
        $id_cart_new = (int) $result['cart']->id;
        Module::getInstanceByName('dynamicproduct');
        if (Module::isEnabled('dynamicproduct')) {
            $module = Module::getInstanceByName('dynamicproduct');
            $module->hookCartDuplicated([
                'id_cart_old' => $id_cart_old,
                'id_cart_new' => $id_cart_new,
            ]);
        }

        //Cambio Valbuena
         if ((bool) Module::isEnabled('idxrcustomproduct')) {
             $module = Module::getInstanceByName('idxrcustomproduct');
             $module->duplicateCartInfo($this->id, $result->id);
         }
        //Fin cambio Valbuena

        return $result;
    }
var $megas;
	var $applyRules = true;










}
