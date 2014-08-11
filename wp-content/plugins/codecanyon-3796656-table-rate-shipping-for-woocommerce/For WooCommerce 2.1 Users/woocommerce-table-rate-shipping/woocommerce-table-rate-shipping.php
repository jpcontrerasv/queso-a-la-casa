<?php
/*
Plugin Name: WooCommerce Table Rate Shipping
Plugin URI: http://www.bolderelements.net/woocommerce-demo/woocommerce-table-rate-shipping/
Description: WooCommerce custom plugin designed to calculate shipping costs and add one or more rates based on a table of rules
Author: Erica Dion
Author URI: http://www.bolderelements.net/
Version: 3.3.7

	Copyright: © 2012 Bolder Elements (email : erica@bolderelements.net)
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_action('plugins_loaded', 'woocommerce_table_rate_shipping_init', 0);

function woocommerce_table_rate_shipping_init() {

	/**
	 * Check if WooCommerce is active
	 */
	if ( class_exists( 'Woocommerce' ) || class_exists( 'WooCommerce' ) ) {
		
		if (!class_exists('WC_Shipping_Method')) return;

		if ( !class_exists( 'BE_Table_Rate_Shipping' ) ) {

			$GLOBALS['settings_slug'] = 'woocommerce_settings';
			if( WOOCOMMERCE_VERSION >= 2.1 ) {
				if( !isset( $woocommerce ) ) $woocommerce = WC();
				$GLOBALS['settings_slug'] = 'wc-settings';
			}
			require('inc/woocommerce-shipping-zones.php');
			
			class BE_Table_Rate_Shipping extends WC_Shipping_Method {

				public static $version = '3.3.7';

				/**
				 * __construct function.
				 *
				 * @access public
				 * @return void
				 */
				function __construct() {
		        	$this->id = 'table_rate_shipping';
			 		$this->method_title = __( 'Table Rate', 'woocommerce' );
					$this->admin_page_heading = __('Table Rates', 'woocommerce' );
					$this->admin_page_description = __( 'Table rate shipping allows you to set numerous rates based on location and specified conditions. Click the headlines below to expand or hide additional settings.', 'woocommerce' );
					$this->table_rate_options = 'woocommerce_table_rates';
					$this->class_priorities_options = 'woocommerce_class_priorities';
					$this->handling_rates_options = 'woocommerce_handling_rates';
					$this->title_order_options = 'woocommerce_trshipping_title_orders';

					// Remove PHP Notices in WP_DEBUG mode
					$this->handling = 0;
					//$this->availability = $this->countries = '';

					add_action( 'woocommerce_update_options_shipping_' . $this->id, array( &$this, 'process_admin_options' ) );
					add_action( 'woocommerce_update_options_shipping_' . $this->id, array( &$this, 'process_table_rates' ) );

					add_action( 'admin_enqueue_scripts', array( &$this, 'register_plugin_styles' ) );

					$this->init();
				}


				/**
				* init function.
				* initialize variables to be used
				*
				* @access public
				* @return void
				*/
				function init() {
					// Load the form fields.
					$this->init_form_fields();

					// Load the settings.
					$this->init_settings();

					// Define user set variables
					$this->enabled = $this->settings['enabled'];
					$this->title = $this->settings['title'];
					$this->condition = $this->settings['condition'];
					$this->ship_free = $this->settings['ship_free'];
					$this->ship_free_label = $this->settings['ship_free_label'];
					$this->ship_free_option = $this->settings['ship_free_option'];
					$this->includetax = $this->settings['includetax'];
					$this->volumetric_enable = $this->settings['volumetric_enable'];
					$this->volumetric_divisor = $this->settings['volumetric_divisor'];
					$this->highest_class = $this->settings['highest_class'];

					// Load Cart rates
					$this->get_table_rates();

					// Load Shipping Classes
					$this->get_class_priorities();

					// Load Shipping Classes
					$this->get_handling_rates();

					// Load Shipping Classes
					$this->get_title_order();
				}


				/**
				 * Initialise Gateway Settings Form Fields
				 *
				 * @access public
				 * @return void
				 */
				function init_form_fields() {
					global $woocommerce;

					$this->form_fields = array(
						'general_settings_title' => array(
							'title' => __( 'General Settings', 'woocommerce' ),
							'type' => 'title',
							'class' => 'title_drop title_h4 general_settings_title active',
							),
						'enabled' => array(
							'title' => __( 'Enable/Disable', 'woocommerce' ),
							'type' 	=> 'checkbox',
							'label' => __( 'Enable this shipping method', 'woocommerce' ),
							'default' => 'no',
							),
						'title' => array(
							'title' => __( 'Method Title', 'woocommerce' ),
							'type' => 'text',
							'description' => __( '', 'woocommerce' ),
							'default' => __( 'Shipping', 'woocommerce' ),
							),
						'condition' => array(
							'title' => __( 'Condition', 'woocommerce' ),
							'type' => 'select',
							'default' => 'per-order',
							'options' => array(
								'per-order' => __( 'Per Order', 'woocommerce' ),
								'per-item' => __( 'Per Item', 'woocommerce' ),
								'per-class' => __( 'Per Class', 'woocommerce' ),
								)
							),
						'ship_free_title' => array(
							'title' => __( 'Free Shipping Override', 'woocommerce' ),
							'type' => 'title',
							'class' => 'title_drop title_h4 ship_free_title',
							),
						'ship_free' => array(
							'title' => __( 'Free Shipping at', 'woocommerce' ),
							'type' => 'text',
							'label' => __( 'Minimum cost of ALL cart items to be eligible for free shipping. Leave blank to disable free shipping option', 'woocommerce' ),
							'default' => '',
							'css' => 'width:100px;'
							),
						'ship_free_label' => array(
							'title' => __( 'Free Shipping Label', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'Label to appear next to Free Shipping Option in cart/checkout pages', 'woocommerce' ),
							'default' => '',
							),
						'ship_free_option' => array(
							'title' => __( 'Add Free Shipping as Option', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'When checked, free shipping will be the only shipping cost, otherwise free shipping will be added as an option in addition to the table below', 'woocommerce' ),
							'default' => 'no',
							),
						'tax_options_title' => array(
							'title' => __( 'Tax Options', 'woocommerce' ),
							'type' => 'title',
							'class' => 'title_drop title_h4 tax_options_title',
							),
						'includetax' => array(
							'title' => __( 'Include Tax', 'woocommerce' ),
							'type' 	=> 'checkbox',
							'label' => __( 'Calculate shipping based on prices AFTER tax', 'woocommerce' ),
							'default' => 'no',
							),
						'volumetric_shipping_title' => array(
							'title' => __( 'Volumetric Shipping', 'woocommerce' ),
							'type' => 'title',
							'class' => 'title_drop title_h4 volumetric_shipping_title',
							),
						'volumetric_enable' => array(
							'title' => __( 'Enable Volumetric Comparison', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'When activated, plugin will determine if volumetric weight is heavier than weight given and charge shipping based on the higher amount', 'woocommerce' ),
							'default' => 'no',
							),
						'volumetric_divisor' => array(
							'title' => __( 'Volumetric Divisor', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'This number can be found through your carrier\'s website', 'woocommerce' ),
							'default' => '',
							),
						'misc_settings_title' => array(
							'title' => __( 'Miscellaneous Settings', 'woocommerce' ),
							'type' => 'title',
							'class' => 'title_drop title_h4 misc_settings_title',
							),
						'highest_class' => array(
							'title' => __( 'Single Class Only', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'When enabled, only items of the highest priority shipping class will be counted towards the shipping cost', 'woocommerce' ) . ' <b>' . __( '(Per Class Method Only)', 'woocommerce' ) . '</b>',
							'default' => 'no',
							),
						'table_settings_title' => array(
							'title' => __( 'Shipping Cost Tables', 'woocommerce' ),
							'type' => 'title',
							'class' => 'title_drop active title_h4 table_settings_title',
							),
					);

				}


				/**
				 * calculate_shipping function.
				 *
				 * @access public
				 * @param array $package (default: array())
				 * @return void
				 */
				function calculate_shipping( $package = array() ) {
					global $woocommerce;

					$this->rates = array();
					$shippingClasses = array();
					$itemsByClass = array();
					$fee_handling = $this->handling;
					$condition = $this->condition;
					$shipForFreeOption = false;
					$cart_subtotal = ($this->includetax == 'yes') ? 0 : $package['contents_cost'];
					$cart_dimensions = 0;
					$shipping_country = $package['destination']['country'];
					$shipping_state = $package['destination']['state'];
					$shipping_zipcode = $package['destination']['postcode'];
					$excludedClasses = array();
					$shipping_classes = $this->class_priorities;
					$handling_rates = $this->handling_rates;

					// get excluded shipping classes
					foreach ($shipping_classes as $key => $value) {
						if($value['excluded'] == 'on')
							$excludedClasses[$value['term_id']] = $value;
					}

					// count number of items in cart and accumulated weights
					$cart_item_count = 0;
					$cart_weight_total = 0;
		    		if ( sizeof( $package['contents'] ) > 0 ) {
						foreach ( $package['contents'] as $item_id => $values ) {
							if($values['data']->needs_shipping()) {
								// get class information
								$item_class_id = $values['data']->get_shipping_class_id();
								$item_class_name = $values['data']->get_shipping_class();
								if( $item_class_name == '' ) $item_class_name = '*';
								if($this->includetax == 'yes') $cart_subtotal += $values['data']->get_price_including_tax() * $values['quantity'];

									$shippingClasses[] = $item_class_id;
								if($condition != 'per-order') {
									$cart_item_count += $values['quantity'];
									$cart_weight_total += $values['data']->weight * $values['quantity'];
								} else {
									if(!array_key_exists($item_class_id, $excludedClasses)) {
										$cart_item_count += $values['quantity'];
										$p_length = ($values['data']->length) ? $values['data']->length : 1;
										$p_width = ($values['data']->width) ? $values['data']->width : 1;
										$p_height = ($values['data']->height) ? $values['data']->height : 1;
										$cart_dimensions += ($p_length*$p_width*$p_height) * $values['quantity'];
										//calculate volumetric weight
										if($this->volumetric_enable == 'yes') {
											$v_weight = ($p_length*$p_width*$p_height) / $this->volumetric_divisor;
											$cart_weight_total += ($v_weight > $values['data']->weight) ? $v_weight * $values['quantity'] : $values['data']->weight * $values['quantity'];
										} else
											$cart_weight_total += $values['data']->weight * $values['quantity'];
									} elseif($this->includetax == 'no') {
										$cart_subtotal -= ($values['data']->get_price() * $values['quantity']);
									}
								}

								// group items by class for per-class method
								$new_array = array(
									'class_name' => $item_class_name,
									'product_data' => $values,
									);
								if(!array_key_exists($item_class_name, $itemsByClass)) $itemsByClass[$item_class_name] = array('class_id' => $item_class_id,'products'=>array());
								array_push($itemsByClass[$item_class_name]['products'],$values);
							}
						}
		    		}
		    		$shippingClasses = array_unique($shippingClasses);

					// check for free shipping
					if($this->ship_free != '') {
						if($cart_subtotal >= (float) $this->ship_free) {
							$free_rate_label = ($this->ship_free_label == "") ? $this->title : $this->ship_free_label;
							$free_rate = array( 'id' => sanitize_title($free_rate_label), 'label' => $free_rate_label, 'cost' => floatval(0) );
							$this->add_rate( $free_rate );

							if($this->ship_free_option == 'yes')
								return;
						}
					}

					if($condition == 'per-order') {
						$order_data = array(
							"condition" => $condition,
							"fee_handling" => $fee_handling,
							"subtotal" => $cart_subtotal,
							"totalweight" => $cart_weight_total,
							"itemcount" => $cart_item_count,
							"dimensions" => $cart_dimensions,
							"volumetric" => ($this->volumetric_enable == 'yes') ? ($cart_dimensions / $this->volumetric_divisor) : 0,
							"shipping_country" => $shipping_country,
							"shipping_state" => $shipping_state,
							"shipping_zipcode" => $shipping_zipcode,
							"shipping_classes" => $shippingClasses,
							"excluded_classes" => $excludedClasses,
							);
						$rate = $this->calculate_shipping_perorder($order_data);
					} elseif($condition == 'per-item') {
						$rate_temp = array();
			    		// cycle through all cart items
			    		if ( sizeof( $package['contents'] ) > 0 ) {
			    			$p_dimensions = 0;
			    		$denied_rates = array();

						// get excluded shipping classes
						$charge_shipping = FALSE;
						foreach ($shippingClasses as $key => $value) {
							if(!array_key_exists($value, $excludedClasses))
								$charge_shipping = TRUE;
						}

						if(!$charge_shipping) {
							$newAr = array( 'id' => sanitize_title($this->title),
											'label' => $this->title,
											'cost' => '0',
											'default' => 'no',
											'shiptype' => get_woocommerce_currency_symbol());
							array_push($rate_temp, array($newAr));
						}

						foreach ( $package['contents'] as $item_id => $values ) {
							if($values['data']->needs_shipping() && !array_key_exists($values['data']->get_shipping_class_id(), $excludedClasses)) {
								$product_weight = $values['data']->get_weight();
								//calculate volumetric weight
								$p_length = ($values['data']->length) ? $values['data']->length : 1;
								$p_width = ($values['data']->width) ? $values['data']->width : 1;
								$p_height = ($values['data']->height) ? $values['data']->height : 1;
								if($this->volumetric_enable == 'yes') {
									$v_weight = ($p_length*$p_width*$p_height) / $this->volumetric_divisor;
									if($v_weight > $values['data']->get_weight()) $product_weight = $v_weight;
								}
								$product_weight *= $values['quantity'];
								$item_cost = ($this->includetax == 'no') ? $values['data']->get_price() : $values['data']->get_price_including_tax();

								$p_length = ($values['data']->length) ? $values['data']->length : 1;
								$p_width = ($values['data']->width) ? $values['data']->width : 1;
								$p_height = ($values['data']->height) ? $values['data']->height : 1;
								$p_dimensions = ($p_length*$p_width*$p_height);

								$order_data = array(
									"condition" => $condition,
									"fee_handling" => $fee_handling,
									"price" => $item_cost, //sale_price,
									"quantity" => $values['quantity'],
									"class" => $values['data']->get_shipping_class_id(),
									"subtotal" => $cart_subtotal,
									"totalweight" => $product_weight,
									"dimensions" => $p_dimensions,
									"volumetric" => ($this->volumetric_enable == 'yes') ? ($p_dimensions / $this->volumetric_divisor) : 0,
									"shipping_country" => $shipping_country,
									"shipping_state" => $shipping_state,
									"shipping_zipcode" => $shipping_zipcode,
									);
									$returnedRate = $this->calculate_shipping_peritem($order_data, $denied_rates); 
									$rate_temp[] = $returnedRate['rate'];
									$denied_rates = $returnedRate['denied_rates'];
				    			}
				    		}
			    		}

			    		$total_tmp = array();
			    		if( isset($rate_temp) && count($rate_temp)) {
				    		// ensure all products are accounted for
				    		$shippingTitles = array();
				    		foreach ($rate_temp as $rt) {
				    			if(count($rt)) {
					    			foreach ($rt as $key => $rtd) {
					    				$shippingTitles[] = $key;
					    			}
				    			}
				    		}
							foreach ($shippingTitles as $skey => $title) {
								foreach ($rate_temp as $rkey => $rate) {
									if(!array_key_exists($title, $rate)) {
				    					//if( isset( $rate_temp[ $rkey ][$title] ) ) unset($rate_temp[$rkey][ $title ]);
				    					$denied_rates[] = $title;
				    				}
								}
							}
					    	// setup rates
				    		foreach ($rate_temp as $item) {
				    			foreach ($item as $key => $rate) {
				    				// check if rate has been denied and should not be added
									if(!in_array($key,$denied_rates)) {
					    				if(!isset($total_tmp[$rate['id']]['default']) || $total_tmp[$rate['id']]['default'] == '') $total_tmp[$rate['id']]['default'] = 'off';
					    				if(!isset($total_tmp[$rate['id']]['cost']) || $total_tmp[$rate['id']]['cost'] == '') $total_tmp[$rate['id']]['cost'] = 0;
				    					$total_tmp[$rate['id']]['id'] = sanitize_title($rate['id']);
				    					$total_tmp[$rate['id']]['label'] = $rate['label'];
				    					$total_tmp[$rate['id']]['cost'] += (array_key_exists('cost', $rate)) ? floatval($rate['cost']) : '';
				    					$total_tmp[$rate['id']]['shiptype'] = $rate['shiptype'];
				    					if($rate['default'] == 'on') $total_tmp[$rate['id']]['default'] = 'on';
				    				}
					    		}
				    		}
				    	}
						$rate = $total_tmp;

					} elseif($condition == 'per-class') {
						$order_data = array(
							"items" => $itemsByClass,
							"condition" => $condition,
							"fee_handling" => $fee_handling,
							"subtotal" => $cart_subtotal,
							"totalweight" => $cart_weight_total,
							"itemcount" => $cart_item_count,
							"shipping_country" => $shipping_country,
							"shipping_state" => $shipping_state,
							"shipping_zipcode" => $shipping_zipcode,
							"shipping_classes" => $shippingClasses,
							"excluded_classes" => $excludedClasses,
							);
						$rate = $this->calculate_shipping_perclass($order_data);
					}

		    		/* add handling fee */
			    	if(count($rate) > 0) {
			    		$handlingFee = 0;
						if(count($this->handling_rates) > 0) {
							foreach ($this->handling_rates as $value) {
								if(be_in_zone($value['zone'],$shipping_country, $shipping_state, $shipping_zipcode)) {
									$handlingFee = $value['fee'];
									$handlingPer = $value['percent'];
								}
							}
						}

						// Get chosen method
						if ( ! empty( $woocommerce->session->chosen_shipping_method ) )
							$chosen_method = $woocommerce->session->chosen_shipping_method;

						//sort array by user chosen order
						$properOrderedArray = $rate;
						if(count($this->title_order)) {
							$title_order = array_map( 'sanitize_title', $this->title_order );
							$properOrderedArray = array_merge(array_flip($title_order), $rate);
						}

			    		foreach ($properOrderedArray as $value) {
			    			if(is_array($value)) {
					    		if(isset($handlingPer) && $handlingPer > 0) $value['cost'] += ($handlingPer / 100) * $cart_subtotal;
					    		if(isset($handlingFee) && $handlingFee > 0) $value['cost'] += $handlingFee;

				    			$value['label'] = ($value['label'] == "") ? $this->title : $value['label'];
				    			$value['id'] = sanitize_title($value['id']);

								// Register the rates
								$this->add_rate( $value );

								// Set default rate if one is not already chosen
								if(isset($value['default']) && $value['default'] == 'on') {
									if( empty( $chosen_method ) || ! isset( $_available_methods[$chosen_method] ) ) {
										// Store chosen method
										$woocommerce->session->chosen_shipping_method = $value['id'];

										// Do action for this chosen method
										do_action( 'woocommerce_shipping_method_chosen', $value['id'] );
									}
								}
							}
			    		}
			    	}
				}



				/**
				 * calculate_shipping_perorder function.
				 *
				 * @access public
				 * @param array $package (default: array())
				 * @return array
				 */
				function calculate_shipping_perorder( $data = array() ) {
					global $woocommerce;

					$shipping_options = $this->table_rates;
					$rate = array();
					$shipping_costs = array();
					$charge_shipping = FALSE;
					$denied_rates = array();

					// get excluded shipping classes
					foreach ($data['shipping_classes'] as $key => $value) {
						if(!array_key_exists($value, $data['excluded_classes']))
							$charge_shipping = TRUE;
					}

					if(!$charge_shipping)
						array_push($shipping_costs, array($this->title,'0',get_woocommerce_currency_symbol(),'no',sanitize_title($this->title)));

					foreach ($shipping_options as $key => $value) {
						if(!be_in_zone($value['zone'], $data['shipping_country'], $data['shipping_state'], $data['shipping_zipcode'])) unset($shipping_options[$key]);
					}

					if(count($shipping_options) <= 0) return $rate;

		    		// cycle through all shipping options
		    		foreach ($shipping_options as $shipping_id => $values) {
		    			$shipping_total = 0;
		    			if( $values['identifier'] == '' ) $values['identifier'] = sanitize_title( $values['title'] );
    					if($values['class'] == '*' || in_array($values['class'], $data['shipping_classes'])) {
	    					switch($values['cond']) {
	    						case 'price':
	    							if(($values['min'] == '*' || $data['subtotal'] >= $values['min']) && ($values['max'] == '*' || $data['subtotal'] <= $values['max'])) {
	    								if($values['bundle_qty'] > 1) {
	    									for($i = 1; $i <= $data['itemcount']; $i++) {
	    										if($i < $values['bundle_qty'])
		    										$shipping_total += $values['cost'];
		    									else
		    										$shipping_total += $values['bundle_cost'];
	    									}
	    								} else {
											if($values['shiptype'] == '%') {
												$shipping_total = $data['subtotal'] * ($values['cost'] / 100);
											}elseif($values['shiptype'] == 'x') {
												$shipping_total = $values['cost'] * $data['itemcount'];
											}elseif($values['shiptype'] == 'w') {
												$shipping_total = $values['cost'] * $data['totalweight'];
											} else {
												$shipping_total = $values['cost'];
											}
										}
										if($values['shiptype'] == 'D') {
											if(isset($shipping_costs[$values['identifier']])) unset($shipping_costs[$values['identifier']]);
											$denied_rates[] = $values['identifier'];
										} elseif( !in_array( $values['identifier'], $denied_rates ) ) {
		    								$shipping_costs[sanitize_title($values['identifier'])] = array($values['title'],$shipping_total,$values['default'],$values['shiptype'],$values['identifier']);
		    							}
	    							}
	    							break;
	    						case 'weight':
	    							if(($values['min'] == '*' || $data['totalweight'] >= $values['min']) && ($values['max'] == '*' || $data['totalweight'] <= $values['max'])) {
	    								if($values['bundle_qty'] > 1) {
	    									for($i = 1; $i <= $data['itemcount']; $i++) {
	    										if($i < $values['bundle_qty'])
		    										$shipping_total += $values['cost'];
		    									else
		    										$shipping_total += $values['bundle_cost'];
	    									}
	    								} else {
											if($values['shiptype'] == '%') {
												$shipping_total = $data['subtotal'] * ($values['cost'] / 100);
											}elseif($values['shiptype'] == 'x') {
												$shipping_total = $values['cost'] * $data['itemcount'];
											}elseif($values['shiptype'] == 'w') {
												$shipping_total = $values['cost'] * $data['totalweight'];
											} else {
												$shipping_total = $values['cost'];
											}
										}
										if($values['shiptype'] == 'D') {
											if(isset($shipping_costs[$values['identifier']])) unset($shipping_costs[$values['identifier']]);
											$denied_rates[] = $values['identifier'];
										} elseif( !in_array( $values['identifier'], $denied_rates ) ) {
		    								$shipping_costs[sanitize_title($values['identifier'])] = array($values['title'],$shipping_total,$values['default'],$values['shiptype'],$values['identifier']);
		    							}
	    							}
	    							break;
	    						case 'item-count':
	    							if(($values['min'] == '*' || $data['itemcount'] >= $values['min']) && ($values['max'] == '*' || $data['itemcount'] <= $values['max'])) {
	    								if($values['bundle_qty'] > 1) {
	    									for($i = 1; $i <= $data['itemcount']; $i++) {
	    										if($i < $values['bundle_qty'])
		    										$shipping_total += $values['cost'];
		    									else
		    										$shipping_total += $values['bundle_cost'];
	    									}
	    								} else {
											if($values['shiptype'] == '%') {
												$shipping_total = $data['subtotal'] * ($values['cost'] / 100);
											}elseif($values['shiptype'] == 'x') {
												$shipping_total = $values['cost'] * $data['itemcount'];
											}elseif($values['shiptype'] == 'w') {
												$shipping_total = $values['cost'] * $data['totalweight'];
											} else {
												$shipping_total = $values['cost'];
											}
										}
										if($values['shiptype'] == 'D') {
											if(isset($shipping_costs[$values['identifier']])) unset($shipping_costs[$values['identifier']]);
											$denied_rates[] = $values['identifier'];
										} elseif( !in_array( $values['identifier'], $denied_rates ) ) {
		    								$shipping_costs[sanitize_title($values['identifier'])] = array($values['title'],$shipping_total,$values['default'],$values['shiptype'],$values['identifier']);
		    							}
	    							}
	    							break;
	    						case 'dimensions':
	    							if(($values['min'] == '*' || $data['dimensions'] >= $values['min']) && ($values['max'] == '*' || $data['dimensions'] <= $values['max'])) {
	    								if($values['bundle_qty'] > 1) {
	    									for($i = 1; $i <= $data['itemcount']; $i++) {
	    										if($i < $values['bundle_qty'])
		    										$shipping_total += $values['cost'];
		    									else
		    										$shipping_total += $values['bundle_cost'];
	    									}
	    								} else {
											if($values['shiptype'] == '%') {
												$shipping_total = $data['subtotal'] * ($values['cost'] / 100);
											}elseif($values['shiptype'] == 'x') {
												$shipping_total = $values['cost'] * $data['itemcount'];
											}elseif($values['shiptype'] == 'w') {
												$shipping_total = $values['cost'] * $data['totalweight'];
											} else {
												$shipping_total = $values['cost'];
											}
										}
										if($values['shiptype'] == 'D') {
											if(isset($shipping_costs[$values['identifier']])) unset($shipping_costs[$values['identifier']]);
											$denied_rates[] = $values['identifier'];
										} elseif( !in_array( $values['identifier'], $denied_rates ) ) {
		    								$shipping_costs[sanitize_title($values['identifier'])] = array($values['title'],$shipping_total,$values['default'],$values['shiptype'],$values['identifier']);
		    							}
	    							}
	    							break;
							}	
	    				}
		    		}

		    		/* create rate(s) */
		    		foreach ($shipping_costs as $value) {
		    			if( is_array($value) && isset($value[4]) ) {
							$rate[sanitize_title($value[4])] = array(
								'id' 	=> sanitize_title($this->id."_".$value[4]),
								'label' => $value[0],
								'cost' 	=> $value[1],
								'default' => $value[2],
								'shiptype' => $value[3],
								);
						}
		    		}

					return $rate;
				}


				/**
				 * calculate_shipping_peritem function.
				 *
				 * @access public
				 * @param array $package (default: array())
				 * @return array
				 */
				function calculate_shipping_peritem( $data = array(), $denied_rates = array() ) {
					$shipping_options = $this->table_rates;
					$rate = array('rate' => array(), 'denied_rates' => $denied_rates);
					$shipping_costs = array();

					foreach ($shipping_options as $key => $value) {
						if($value['zone'] != '*' && !be_in_zone($value['zone'], $data['shipping_country'], $data['shipping_state'], $data['shipping_zipcode'])) unset($shipping_options[$key]);
					}
					if(count($shipping_options) <= 0) return $rate;

		    		// cycle through all shipping options
		    		foreach ($shipping_options as $shipping_id => $values) {
		    			$shipping_total = 0;
		    			if( $values['identifier'] == '' ) $values['identifier'] = sanitize_title( $values['title'] );
    					if($data['class'] == $values['class'] || $values['class'] == '*') {
	    					switch($values['cond']) {
	    						case 'price':
	    							if(($values['min'] == '0' || $data['price'] >= $values['min']) && ($values['max'] == '*' || $data['price'] <= $values['max'])) {
	    								if($values['bundle_qty'] > 1) {
	    									for($i = 1; $i <= $data['quantity']; $i++) {
	    										if($i < $values['bundle_qty'])
		    										$shipping_total += $values['cost'];
		    									else
		    										$shipping_total += $values['bundle_cost'];
	    									}
	    								} else {
											if($values['shiptype'] == '%') {
												$shipping_total = ($data['quantity'] * $data['price']) * ($values['cost'] / 100);
											}elseif($values['shiptype'] == 'w') {
												$shipping_total = $values['cost'] * $data['totalweight'] * $data['quantity'];
											} else {
												$shipping_total = $values['cost'] * $data['quantity'];
											}
										}
										if($values['shiptype'] == 'D') {
											$denied_rates[] = $values['identifier'];
										} elseif( !in_array( $values['identifier'], $denied_rates ) ) {
		    								$shipping_costs[sanitize_title($values['identifier'])] = array($values['title'],$shipping_total,$values['default'],$values['shiptype'],$values['identifier']);
		    							}
	    							}
	    							break;
	    						case 'weight':
	    							if(($values['min'] == '0' || $data['totalweight'] >= $values['min']) && ($values['max'] == '*' || $data['totalweight'] <= $values['max'])) {
	    								if($values['bundle_qty'] > 1) {
	    									for($i = 1; $i <= $data['quantity']; $i++) {
	    										if($i < $values['bundle_qty'])
		    										$shipping_total += $values['cost'];
		    									else
		    										$shipping_total += $values['bundle_cost'];
	    									}
	    								} else {
											if($values['shiptype'] == '%') {
												$shipping_total = $data['price'] * ($values['cost'] / 100);
											}elseif($values['shiptype'] == 'w') {
												$shipping_total = $values['cost'] * $data['totalweight'];
											} else {
												$shipping_total = $values['cost'];
											}
										}
										if($values['shiptype'] == 'D') {
											$denied_rates[] = $values['identifier'];
										} elseif( !in_array( $values['identifier'], $denied_rates ) ) {
		    								$shipping_costs[sanitize_title($values['identifier'])] = array($values['title'],$shipping_total,$values['default'],$values['shiptype'],$values['identifier']);
		    							}
	    							}
	    							break;
	    						case 'item-count':
	    							if(($values['min'] == '0' || $data['quantity'] >= $values['min']) && ($values['max'] == '*' || $data['quantity'] <= $values['max'])) {
	    								if($values['bundle_qty'] > 1) {
	    									for($i = 1; $i <= $data['quantity']; $i++) {
	    										if($i < $values['bundle_qty'])
		    										$shipping_total += $values['cost'];
		    									else
		    										$shipping_total += $values['bundle_cost'];
	    									}
	    								} else {
											if($values['shiptype'] == '%') {
												$shipping_total = ($data['quantity'] * $data['price']) * ($values['cost'] / 100);
											}elseif($values['shiptype'] == 'w') {
												$shipping_total = $values['cost'] * $data['totalweight'] * $data['quantity'];
											} else {
												$shipping_total = $values['cost'] * $data['quantity'];
											}
										}
										if($values['shiptype'] == 'D') {
											$denied_rates[] = $values['identifier'];
										} elseif( !in_array( $values['identifier'], $denied_rates ) ) {
		    								$shipping_costs[sanitize_title($values['identifier'])] = array($values['title'],$shipping_total,$values['default'],$values['shiptype'],$values['identifier']);
		    							}
	    							}
	    							break;
	    						case 'dimensions':
	    							if(($values['min'] == '0' || $data['dimensions'] >= $values['min']) && ($values['max'] == '*' || $data['dimensions'] <= $values['max'])) {
	    								if($values['bundle_qty'] > 1) {
	    									for($i = 1; $i <= $data['quantity']; $i++) {
	    										if($i < $values['bundle_qty'])
		    										$shipping_total += $values['cost'];
		    									else
		    										$shipping_total += $values['bundle_cost'];
	    									}
	    								} else {
											if($values['shiptype'] == '%') {
												$shipping_total = ($data['quantity'] * $data['price']) * ($values['cost'] / 100);
											}elseif($values['shiptype'] == 'w') {
												$shipping_total = $values['cost'] * $data['totalweight'] * $data['quantity'];
											} else {
												$shipping_total = $values['cost'] * $data['quantity'];
											}
										}
										if($values['shiptype'] == 'D') {
											$denied_rates[] = $values['identifier'];
										} elseif( !in_array( $values['identifier'], $denied_rates ) ) {
		    								$shipping_costs[sanitize_title($values['identifier'])] = array($values['title'],$shipping_total,$values['default'],$values['shiptype'],$values['identifier']);
		    							}
	    							}
	    							break;
							}	
	    				}
		    		}

		    		/* create rate(s) */
		    		$rate['denied_rates'] = $denied_rates;
		    		foreach ($shipping_costs as $value) {
						$rate['rate'][sanitize_title($value[4])] = array(
							'id' 	=> sanitize_title($this->id."_".$value[4]),
							'label' => $value[0],
							'cost' 	=> $value[1],
							'default' => $value[2],
							'shiptype' => $value[3],
							);
		    		}

					return $rate;
				}



				/**
				 * calculate_shipping_perclass function.
				 *
				 * @access public
				 * @param array $package (default: array())
				 * @return array
				 */
				function calculate_shipping_perclass( $data = array() ) {
					$shipping_options = $this->table_rates;
					$rate = array();
					$shipping_costs = array();
					$denied_rates = array();
					$charge_shipping = FALSE;

					foreach ($shipping_options as $key => $value) {
						if($value['zone'] != '*' && !be_in_zone($value['zone'], $data['shipping_country'], $data['shipping_state'], $data['shipping_zipcode'])) unset($shipping_options[$key]);
					}
					
					if(count($shipping_options) <= 0) return $rate;

					// get excluded shipping classes
					foreach ($data['shipping_classes'] as $key => $value) {
						if(!array_key_exists($value, $data['excluded_classes']))
							$charge_shipping = TRUE;
					}

					if(!$charge_shipping) {
						$newAr[sanitize_title($this->title)] = array('title' => $this->title,
										'cost' => '0',
										'shiptype' => get_woocommerce_currency_symbol(),
										'default' => 'no');
						array_push($shipping_costs, $newAr);
					} else {
						// cycle through each shipping class
						foreach ($data['items'] as $class => $cval) {
							// setup new array for price options
							$shipping_costs[$class] = array();

							// get total counts
							$class_total_price = $class_total_count = $class_total_weight = $class_total_dimensions = 0;
				    		if ( sizeof( $cval['products'] ) > 0 ) {
								foreach ( $cval['products'] as $values ) {
									$class_total_price += ($this->includetax == 'yes') ? $values['data']->get_price_including_tax() * $values['quantity'] : $values['data']->price * $values['quantity'];
									$class_total_count += $values['quantity'];

									$product_weight = $values['data']->get_weight();
									//calculate volumetric weight
									$p_length = ($values['data']->length) ? $values['data']->length : 1;
									$p_width = ($values['data']->width) ? $values['data']->width : 1;
									$p_height = ($values['data']->height) ? $values['data']->height : 1;
									if($this->volumetric_enable == 'yes') {
										$v_weight = ($p_length*$p_width*$p_height) / $this->volumetric_divisor;
										if($v_weight > $values['data']->get_weight()) $product_weight = $v_weight;
									}
									$class_total_dimensions += ($p_length*$p_width*$p_height) * $values['quantity'];
									$class_total_weight += $product_weight * $values['quantity'];

								}
				    		}

				    		// cycle through all shipping options
				    		foreach ($shipping_options as $shipping_id => $values) {
				    			$shipping_total = 0;
			    				if( $values['identifier'] == '' ) $values['identifier'] = sanitize_title( $values['title'] );
		    					if($values['class'] == '*' || $values['class'] == $cval['class_id']) {
			    					switch($values['cond']) {
			    						case 'price':
			    							if(($values['min'] == '*' || $class_total_price >= $values['min']) && ($values['max'] == '*' || $class_total_price <= $values['max'])) {
			    								if($values['bundle_qty'] > 1) {
			    									for($i = 1; $i <= $class_total_count; $i++) {
			    										if($i < $values['bundle_qty'])
				    										$shipping_total += $values['cost'];
				    									else
				    										$shipping_total += $values['bundle_cost'];
			    									}
			    								} else {
													if($values['shiptype'] == '%') {
														$shipping_total = $class_total_price * ($values['cost'] / 100);
													}elseif($values['shiptype'] == 'x') {
														$shipping_total = $values['cost'] * $class_total_count;
													}elseif($values['shiptype'] == 'w') {
														$shipping_total = $values['cost'] * $class_total_weight;
													} else {
														$shipping_total = $values['cost'];
													}
				    							}
												if($values['shiptype'] == 'D') {
													if(isset($shipping_costs[$class][$values['identifier']])) unset($shipping_costs[$class][$values['identifier']]);
													$denied_rates[] = $values['identifier'];
												} elseif( !in_array( $values['identifier'], $denied_rates ) ) {
					    							$shipping_costs[$class][$values['identifier']]['title'] = $values['title'];
					    							$shipping_costs[$class][$values['identifier']]['cost'] = $shipping_total;
					    							$shipping_costs[$class][$values['identifier']]['default'] = $values['default'];
					    							$shipping_costs[$class][$values['identifier']]['shiptype'] = $values['shiptype'];
				    							}
				    						}
			    							break;
			    						case 'weight':
			    							if(($values['min'] == '*' || $class_total_weight >= $values['min']) && ($values['max'] == '*' || $class_total_weight <= $values['max'])) {
			    								if($values['bundle_qty'] > 1) {
			    									for($i = 1; $i <= $class_total_count; $i++) {
			    										if($i < $values['bundle_qty'])
				    										$shipping_total += $values['cost'];
				    									else
				    										$shipping_total += $values['bundle_cost'];
			    									}
			    								} else {
													if($values['shiptype'] == '%') {
														$shipping_total = $class_total_price * ($values['cost'] / 100);
													}elseif($values['shiptype'] == 'x') {
														$shipping_total = $values['cost'] * $class_total_count;
													}elseif($values['shiptype'] == 'w') {
														$shipping_total = $values['cost'] * $class_total_weight;
													} else {
														$shipping_total = $values['cost'];
													}
												}
												if($values['shiptype'] == 'D') {
													if(isset($shipping_costs[$class][$values['identifier']])) unset($shipping_costs[$class][$values['identifier']]);
													$denied_rates[] = $values['identifier'];
												} elseif( !in_array( $values['identifier'], $denied_rates ) ) {
					    							$shipping_costs[$class][$values['identifier']]['title'] = $values['title'];
					    							$shipping_costs[$class][$values['identifier']]['cost'] = $shipping_total;
					    							$shipping_costs[$class][$values['identifier']]['default'] = $values['default'];
					    							$shipping_costs[$class][$values['identifier']]['shiptype'] = $values['shiptype'];
				    							}
			    							}
			    							break;
			    						case 'item-count':
			    							if(($values['min'] == '*' || $class_total_count >= $values['min']) && ($values['max'] == '*' || $class_total_count <= $values['max'])) {
			    								if($values['bundle_qty'] > 1) {
			    									for($i = 1; $i <= $class_total_count; $i++) {
			    										if($i < $values['bundle_qty'])
				    										$shipping_total += $values['cost'];
				    									else
				    										$shipping_total += $values['bundle_cost'];
			    									}
			    								} else {
													if($values['shiptype'] == '%') {
														$shipping_total = $class_total_price * ($values['cost'] / 100);
													}elseif($values['shiptype'] == 'x') {
														$shipping_total = $values['cost'] * $class_total_count;
													}elseif($values['shiptype'] == 'w') {
														$shipping_total = $values['cost'] * $class_total_weight;
													} else {
														$shipping_total = $values['cost'];
													}
												}
												if($values['shiptype'] == 'D') {
													if(isset($shipping_costs[$class][$values['identifier']])) unset($shipping_costs[$class][$values['identifier']]);
													$denied_rates[] = $values['identifier'];
												} elseif( !in_array( $values['identifier'], $denied_rates ) ) {
					    							$shipping_costs[$class][$values['identifier']]['title'] = $values['title'];
					    							$shipping_costs[$class][$values['identifier']]['cost'] = $shipping_total;
					    							$shipping_costs[$class][$values['identifier']]['default'] = $values['default'];
					    							$shipping_costs[$class][$values['identifier']]['shiptype'] = $values['shiptype'];
				    							}
			    							}
			    							break;
			    						case 'dimensions':
			    							if(($values['min'] == '*' || $class_total_dimensions >= $values['min']) && ($values['max'] == '*' || $class_total_dimensions <= $values['max'])) {
			    								if($values['bundle_qty'] > 1) {
			    									for($i = 1; $i <= $class_total_count; $i++) {
			    										if($i < $values['bundle_qty'])
				    										$shipping_total += $values['cost'];
				    									else
				    										$shipping_total += $values['bundle_cost'];
			    									}
			    								} else {
													if($values['shiptype'] == '%') {
														$shipping_total = $class_total_price * ($values['cost'] / 100);
													}elseif($values['shiptype'] == 'x') {
														$shipping_total = $values['cost'] * $class_total_count;
													}elseif($values['shiptype'] == 'w') {
														$shipping_total = $values['cost'] * $class_total_weight;
													} else {
														$shipping_total = $values['cost'];
													}
												}
												if($values['shiptype'] == 'D') {
													if(isset($shipping_costs[$class][$values['identifier']])) unset($shipping_costs[$class][$values['identifier']]);
													$denied_rates[] = $values['identifier'];
												} elseif( !in_array( $values['identifier'], $denied_rates ) ) {
					    							$shipping_costs[$class][$values['identifier']]['title'] = $values['title'];
					    							$shipping_costs[$class][$values['identifier']]['cost'] = $shipping_total;
					    							$shipping_costs[$class][$values['identifier']]['default'] = $values['default'];
					    							$shipping_costs[$class][$values['identifier']]['shiptype'] = $values['shiptype'];
				    							}
			    							}
			    							break;
									}	
			    				}
				    		}
				    	}
				    }
			    	// adjust array if highest priority only is enabled
		    		if( $this->highest_class == 'yes' ) {
		    			$classesUsed = array_keys( $shipping_costs );
		    			$highestClass = $this->get_highest_priority_class( $classesUsed );
		    			$highestClassTerm = get_term_by( 'id', $highestClass, 'product_shipping_class', ARRAY_A );
		    			$shipping_costs_new[ $highestClassTerm['slug'] ] = $shipping_costs[ $highestClassTerm['slug'] ];
		    			$shipping_costs = $shipping_costs_new;
		    		}

		    		// ensure all products are accounted for
		    		$shippingTitles = array();
		    		$productClasses = array_keys( $shipping_costs );
		    		foreach ($productClasses as $pc) {
		    			foreach ($shipping_costs[ $pc ] as $key => $sc) {
		    				$shippingTitles[ $pc ][] = $key;
		    			}
		    			
		    		}
		    		$compArray = $shippingTitles[ $productClasses[ 0 ] ];
		    		for( $i=1; $i<count($shippingTitles);$i++) {
			    		$differences = array_diff($compArray,$shippingTitles[$productClasses[ $i ]]);
			    		$differences = array_merge( $differences, array_diff($shippingTitles[$productClasses[ $i ]], $compArray) );
			    		if( count( $differences ) ) {
			    			foreach ($differences as $key => $dif) {
			    				foreach ($productClasses as $pc) {
			    					if( isset( $compArray[ $key ] ) ) unset( $compArray[ $key ] );
			    					if( isset( $shipping_costs[ $pc ][ $dif ] ) ) unset($shipping_costs[ $pc ][ $dif ]);
			    				}
			    			}
			    		}
			    	}

			    	// structure shipping costs for final output
		    		$new_rates_array = array();
		    		foreach ($shipping_costs as $class => $val) {
		    			foreach ($val as $key => $val2) {
		    				if(!isset($new_rates_array[$key]['cost'])) $new_rates_array[$key]['cost'] = 0;
		    				$new_rates_array[$key]['title'] = $val2['title'];
		    				$new_rates_array[$key]['cost'] += (float) $val2['cost'];
		    				if($val2['default'] == 'on') $new_rates_array[$key]['default'] = 'on';
		    			}
		    		}

		    		/* create rate(s) */
		    		foreach ($new_rates_array as $identifier => $value) {
						$rate[sanitize_title($identifier)] = array(
							'id' 	=> sanitize_title($this->id."_".$identifier),
							'label' => $value['title'],
							'cost' 	=> $value['cost'],
							'shiptype' => (isset($value['shiptype'])) ? $value['shiptype'] : "",
							);
						if(isset($value['default'])) $rate[$this->id."_".$title]['default'] = $value['default'];
		    		}

					return $rate;
				}



				/**
				 * Admin Panel Options
				 * - Options for the cart based portion
				 *
				 * @since 1.0.0
				 * @access public
				 * @return void
				 */
				public function admin_options() {
					global $woocommerce;

					$cur_symbol = get_woocommerce_currency_symbol();
			        $condOpsG = $classOpsG = $zoneOpsJS = "";
					$shippingClasses = $woocommerce->shipping->get_shipping_classes();
               		if(count($shippingClasses) > 0) foreach($shippingClasses as $key => $val) $classOpsG .= "<option value=\"".$val->term_id."\">".$val->name."</option>";
			        $conds = array("price" => "Price","weight" => "Weight","item-count" => "Item Count","dimensions" => "Dimensions");
               		foreach($conds as $key => $val) $condOpsG .= "<option value=\"".$key."\">".$val."</option>";
			        $countries = $woocommerce->countries->get_allowed_countries();
			        $zones = be_get_zones();
               		if( count( $zones ) ) foreach($zones as $val) $zoneOpsJS .= "<option value=\"".$val['zone_id']."\">".$val['zone_title']."</option>";
	?>
			<style>.check-column input{margin-left:8px;} .check-column {margin: 0;padding: 0;}</style>
	    	<h3><?php echo $this->admin_page_heading; ?></h3>
	    	<p><?php echo $this->admin_page_description; ?></p>
	    	<table class="form-table">
	    	<?php
	    		// Generate the HTML For the settings form.
	    		$this->generate_settings_html();
	    		?>
		    	<tr valign="top" id="shipping_handling_rates">
		            <th scope="row" class="titledesc"><?php _e( 'Handling / Base Rates', 'woocommerce' ); ?>:</th>
		            <td class="forminp" id="<?php echo $this->id; ?>_handling_rates">
		            	<table class="shippingrows widefat" style="width: 60%;min-width:550px;" cellspacing="0">
		            		<thead>
		            			<tr>
		            				<th class="check-column"><input type="checkbox"></th>
		        	            	<th><?php _e( 'Zone', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e('Setup and review zones under the \'Shipping Zones\' tab','woocommerce'); ?>">[?]</a></th>
		        	            	<th><?php _e( 'Fee', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e('Adds the specified percentage of purchase total followed by the fixed fee','woocommerce'); ?>">[?]</a></th>
		            			</tr>
		            		</thead>
		            		<tfoot>
		            			<tr>
		            				<th colspan="2"><a href="#" class="add button"><?php _e( '+ Add Handling Fee', 'woocommerce' ); ?></a></th>
		            				<th colspan="1" style="text-align:right;"><a href="#" class="remove button"><?php _e( 'Delete selected fees', 'woocommerce' ); ?></a></th>
		            			</tr>
		            		</tfoot>
		            		<tbody class="class_priorities">
		                	<?php
		                	$i = -1;
		                	if(count($this->handling_rates) > 0) {
		                		foreach ( $this->handling_rates as $id => $arr ) {
			                		$countryOps = "";
		                			$i++;
			                		foreach ( $zones as $val ) {
										$countryOps .= '<option value="' . $val['zone_id'] . '" ' . selected( $val['zone_id'], $arr['zone'], false ) . '>' . $val['zone_title'] . '</option>';
			                		}
			                		echo '<tr class="handling_fees">
			                		    <td class="check-column"><input type="checkbox" name="select" /></td>
					                    <td><select name="'. $this->id .'_handling_country[' . $i . ']">' . $countryOps . '</select></td>
			                		    <td>' . $cur_symbol . '<input type="text" value="' . $arr['fee'] . '" name="'. $this->id .'_handling_fee[' . $i . ']" size="5" /> &nbsp; % <input type="text" value="' . $arr['percent'] . '" name="'. $this->id .'_handling_percent[' . $i . ']" size="5" /></td></tr>';
		                		}
		                	} echo '<tr colspan="3">' . _e( 'Set different handling rates or base fees for different countries. These prices will be added to all qualifying orders.', 'woocommerce' ) . '</tr>';
		                	?>
		                	</tbody>
		                </table>
		            </td>
		        </tr>
		    	<tr valign="top" id="table_rate_based">
		            <th scope="row" class="titledesc"><?php _e( 'Shipping Table Rates', 'woocommerce' ); ?>:</th>
		            <td class="forminp" id="<?php echo $this->id; ?>_table_rates">
		            	<table class="shippingrows widefat" cellspacing="0">
		            		<thead>
		            			<tr>
		            				<th class="check-column"><input type="checkbox"></th>
		        	            	<th class="shipping_class"><?php _e( 'Title', 'woocommerce' ); ?>* <a class="tips" data-tip="<?php _e('This controls the title which the user sees during checkout','woocommerce'); ?>">[?]</a></th>
		        	            	<th class="shipping_class"><?php _e( 'Identifier', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e('Separates which rates are combined and which become different options. If left blank, one will be generated.','woocommerce'); ?>">[?]</a></th>
		            				<th><?php _e( 'Zone', 'woocommerce' ); ?>* <a class="tips" data-tip="<?php _e('Setup and review zones under the \'Shipping Zones\' tab','woocommerce'); ?>">[?]</a></th>
		        	            	<th><?php _e( 'Shipping Class', 'woocommerce' ); ?></th>
		        	            	<th><?php _e( 'Based On', 'woocommerce' ); ?></th>
		        	            	<th><?php _e( 'Min', 'woocommerce' ); ?></th>
		        	            	<th><?php _e( 'Max', 'woocommerce' ); ?></th>
		        	            	<th><?php _e( 'Cost', 'woocommerce' ); ?> <a class="tips" data-tip="<?php echo $cur_symbol; _e(' - Fixed Price&lt;br /&gt;% - Percentage of Cart Subtotal&lt;br /&gt;x - Multiply cost by quantity&lt;br /&gt;(does not apply to Per Item method)&lt;br /&gt;w - Multiply cost by weight&lt;br /&gt;D - Deny: the titled shipping rate will be removed','woocommerce'); ?>">[?]</a></th>
		        	            	<th><?php _e( 'Bundle', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e('If supplied, charges cost up until quantity given. Then charges second price for this and every item after.','woocommerce'); ?>">[?]</a></th>
		        	            	<th><?php _e( 'Default', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e('Check the box to set this option as the default selected choice on the cart page','woocommerce'); ?>">[?]</a></th>
		            			</tr>
		            		</thead>
		            		<tfoot>
		            			<tr>
		            				<th colspan="3"><a href="#" class="add button"><?php _e( '+ Add Table Rate', 'woocommerce' ); ?></a></th>
		            				<th colspan="8" style="text-align:right;"><small><?php _e( 'Use the wildcard symbol (*) to denote multiple regions', 'woocommerce' ); ?></small>
		            					<a href="#" class="double button"><?php _e( 'Duplicate selected rates', 'woocommerce' ); ?></a>
		            					<a href="#" class="remove button"><?php _e( 'Delete selected rates', 'woocommerce' ); ?></a></th>
		            			</tr>
		            		</tfoot>
		            		<tbody class="table_rates">
		                	<?php
		                	$i = -1;
		                	if ( $this->table_rates ) {
		                		foreach ( $this->table_rates as $class => $rate ) {
			                		$i++;
									$selType = "<select name=\"". $this->id ."_shiptype[" . $i . "]\" class=\"shiptype\">
										<option>".$cur_symbol."</option>
										<option";
										if($rate['shiptype'] == "%") $selType .= " selected=\"selected\"";
										$selType .= ">%</option>
										<option";
										if($rate['shiptype'] == "x") $selType .= " selected=\"selected\"";
										$selType .= ">x</option>
										<option";
										if($rate['shiptype'] == "w") $selType .= " selected=\"selected\"";
										$selType .= ">w</option>
										<option";
										if($rate['shiptype'] == "D") $selType .= " selected=\"selected\"";
										$selType .= ">D</option></select>";
			                		$condOps = "";
			                		foreach($conds as $key => $val) { 
			                			$condOps .= '<option value="' . $key . '" ' . selected($rate['cond'], $key, false) . '>' . $val . '</option>';
			                		}
			                		$zoneOps = "";
			                		foreach ($zones as $value) {
			                			$zoneOps .= '<option value="' . $value['zone_id'] . '" ' . selected($rate['zone'], $value['zone_id'], false) . '>' . $value['zone_title'] . '</option>';
			                		}

			                		echo '<tr class="cart_rate">
			                		    <td class="check-column"><input type="checkbox" name="select" /></td>
			                		    <td><input type="text" value="' . stripslashes( $rate['title'] ) . '" name="'. $this->id .'_title[' . $i . ']" class="title" size="25" /></td>
			                		    <td><input type="text" value="' . $rate['identifier'] . '" name="'. $this->id .'_identifier[' . $i . ']" class="identifier" size="25" /></td>
					                    <td><select name="'. $this->id .'_zone[' . $i . ']" class="zone">' . $zoneOps . '</select></td>
					                    <td><select name="'. $this->id .'_class[' . $i . ']" class="class"><option>*</option>';
					                    foreach($shippingClasses as $key => $val) echo '<option value="' . $val->term_id . '" '.selected( $rate['class'], $val->term_id, false) . '>' . $val->name . '</option>';
					                echo '</select></td><td><select name="'. $this->id .'_cond[' . $i . ']" class="condition">' . $condOps . '</select></td>
					                    <td><input type="text" value="' . $rate['min'] . '" name="'. $this->id .'_min[' . $i . ']" class="min" placeholder="'.__( 'n/a', 'woocommerce' ).'" size="6" /></td>
					                    <td><input type="text" value="' . $rate['max'] . '" name="'. $this->id .'_max[' . $i . ']" class="max" placeholder="'.__( 'n/a', 'woocommerce' ).'" size="6" /></td>
					                    <td>' . $selType . ' <input type="text" value="' . $rate['cost'] . '" name="'. $this->id .'_cost[' . $i . ']" class="cost" placeholder="'.__( '0.00', 'woocommerce' ).'" size="6" /></td>
					                    <td>qty >= <input type="text" value="' . $rate['bundle_qty'] . '" name="'. $this->id .'_bundle_qty[' . $i . ']" class="bundle_qty" placeholder="0" size="3" /><br />' . $cur_symbol . '
					                    	<input type="text" value="' . $rate['bundle_cost'] . '" name="' . $this->id . '_bundle_cost[' . $i . ']" class="bundle_cost" placeholder="'.__( '0.00', 'woocommerce' ).'" size="6" /></td>
					                    <td><input type="checkbox" name="' . $this->id . '_default[' . $i . ']" class="default" '.checked( $rate['default'], 'on', false) . ' /></td>
				                    </tr>';
		                		}
		                	}
		                	?>
		                	</tbody>
		                </table>
		            </td>
		        </tr>
		    	<tr valign="top" id="shipping_class_priorities">
		            <th scope="row" class="titledesc"><?php _e( 'Shipping Class Priorities', 'woocommerce' ); ?>:</th>
		            <td class="forminp" id="<?php echo $this->id; ?>_class_priorities">
		            	<table class="shippingrows widefat" cellspacing="0">
		            		<thead>
		            			<tr>
		        	            	<th class="shipping_class"><?php _e( 'Shipping Class', 'woocommerce' ); ?></th>
		        	            	<th><?php _e( 'Priority', 'woocommerce' ); ?> <a class="tips" data-tip="Enter any whole number, largest number is highest priority">[?]</a></th>
		        	            	<th><?php _e( 'Exclude', 'woocommerce' ); ?> <a class="tips" data-tip="If shipping is free for items with this class, check the box to exclude these cart items from the per-order method">[?]</a></th>
		            			</tr>
		            		</thead>
		            		<tfoot>
		            			<tr>
		            				<th colspan="3"><i><?php _e( 'These priorities will be used to calculate the appropriate "Per Order" shipping price in the table above. When an order has items of different shipping classes, the one with the highest priority will be used.', 'woocommerce' ); ?></i></th>
		            			</tr>
		            		</tfoot>
		            		<tbody class="class_priorities">
		                	<?php
		                	$class_priorities_array = array();
		                	if(count($shippingClasses) > 0) {
			                	foreach ($shippingClasses as $key => $val) {
			                		$class_priorities_array[$val->term_id] = array("term_id" => $val->term_id, "name" => $val->name, "priority" => (float) 10, "exclude" => '0');
			                	}
			                }
		                	if(count($this->class_priorities) > 0) {
			                	foreach ($this->class_priorities as $key => $val) {
			                		if(!array_key_exists($val['term_id'], $class_priorities_array)) unset($this->class_priorities[$val['term_id']]);
			                			elseif( $class_priorities_array[$key]['name'] != $val['name'] ) $this->class_priorities[$key]['name'] = $class_priorities_array[$key]['name'];
			                	}
			                }
		                	$class_priorities_array = $this->class_priorities + $class_priorities_array;

							// Sort Array by Priority
							if(count($class_priorities_array) > 0) {
								foreach ($class_priorities_array as $key => $row) {
			    					$name[$key]  = $row['name'];
			    					$priority[$key] = $row['priority'];
								}
								array_multisort($priority, SORT_DESC, $name, SORT_ASC, $class_priorities_array);
							}

		                	$i = -1;
		                	if(count($class_priorities_array) > 0) {
		                		foreach ( $class_priorities_array as $id => $arr ) {
		                			$i++;
		                			$checked = ($arr['excluded'] == 'on') ? ' checked="checked"' : '';
			                		echo '<tr class="shipping_class">
			                			<input type="hidden" name="'. $this->id .'_scpid[' . $i . ']" value="' . $arr['term_id'] . '" />
			                			<input type="hidden" name="'. $this->id .'_scp[' . $i . ']" value="' . $id . '" />
			                			<input type="hidden" name="'. $this->id .'_sname[' . $i . ']" value="' . $arr['name'] . '" />
			                			<td>'.$arr['name'].'</td>
			                		    <td><input type="text" value="' . $arr['priority'] . '" name="'. $this->id .'_priority[' . $i . ']" size="5" /></td>
			                		    <td><input type="checkbox" ' . $checked . '" name="'. $this->id .'_excluded[' . $i . ']" size="5" /></td>';
		                		}
		                	} else echo '<tr colspan="3"><td>You have no shipping classes available</td></tr>'
		                	?>
		                	</tbody>
		                </table>
		            </td>
		        </tr>
			</table><!--/.form-table-->
			<h4 class="title_drop title_h4 ship_free_title"><?php _e('Set the Order Shipping Options Will Appear','woocommerce'); ?></h4>
			<table class="form-table">
		    	<tr valign="top" id="shipping_title_order">
		            <th scope="row" class="titledesc"><?php _e( 'Shipping Cost Order', 'woocommerce' ); ?>:</th>
		            <td class="forminp" id="<?php echo $this->id; ?>_order_titles">
		            	<table class="shippingrows widefat" cellspacing="0">
		            		<tbody>
<?php
	                	if(count($this->title_order) > 0) {
	                		foreach ( $this->title_order as $tor ) {
?>
								<tr><td class="title"><input type="hidden" name="<?php echo $this->id; ?>_title_order[]" value="<?php echo $tor; ?>"><span><?php echo $tor; ?></span></td></tr>
<?php
	                		}
	                	}
?>
							</tbody>
		            	</table>
						<p><?php _e('Not seeing all of your options','woocommerce'); ?>? <a href="#" id="refresh_list"><?php _e('Refresh List','woocommerce'); ?></a></p>
		            </td>
		        </tr>
			</table>
			<script type="text/javascript">
				jQuery(function() {
					jQuery('h4.title_drop').next('.form-table').css('display','none');
					jQuery('h4.title_drop.general_settings_title').next('.form-table').css('display','table');
					jQuery('h4.title_drop.table_settings_title').next('.form-table').css('display','table');
					jQuery('h4.title_drop').live('click', function(){
						if (jQuery(this).next('.form-table').is(":hidden")) {
							jQuery(this).next('.form-table').show("slow","linear");
							jQuery(this).addClass('active');
						} else {
							jQuery(this).next('.form-table').hide("slow","linear");
							jQuery(this).removeClass('active');
						}
						//jQuery(this).next('.form-table').slideToggle("slow");
					});

					jQuery('#<?php echo $this->id; ?>_table_rates a.add').live('click', function(){
						var size = jQuery('#<?php echo $this->id; ?>_table_rates tbody .cart_rate').size();

						jQuery('<tr class="cart_rate">\
						    <td class="check-column"><input type="checkbox" name="select" /></td>\
		            			    <td><input type="text" name="<?php echo $this->id; ?>_title[' + size + ']" class="title" size="25" /></td>\
		            			    <td><input type="text" name="<?php echo $this->id; ?>_identifier[' + size + ']" class="identifier" size="25" /></td>\
				                    <td><select name="<?php echo $this->id; ?>_zone[' + size + ']" class="zone"><?php echo addslashes($zoneOpsJS); ?></select></td>\
				                    <td><select name="<?php echo $this->id; ?>_class[' + size + ']" class="class"><option>*</option><?php echo addslashes($classOpsG); ?></select></td>\
				                    <td><select name="<?php echo $this->id; ?>_cond[' + size + ']" class="condition"><?php echo addslashes($condOpsG); ?></select></td>\
				                    <td><input type="text" name="<?php echo $this->id; ?>_min[' + size + ']" class="min" placeholder="0" size="6" /></td>\
				                    <td><input type="text" name="<?php echo $this->id; ?>_max[' + size + ']" class="max" placeholder="*" size="6" /></td>\
				                    <td><select name="<?php echo $this->id; ?>_shiptype[' + size + ']" class="shiptype"><option><?php echo $cur_symbol; ?></option><option>%</option><option>x</option><option>w</option><option>D</option></select>\
				                    	<input type="text" name="<?php echo $this->id; ?>_cost[' + size + ']" class="cost" placeholder="0.00" size="6" /></td>\
						            <td>qty >= <input type="text" name="<?php echo $this->id; ?>_bundle_qty[' + size + ']" class="bundle_qty" placeholder="0" size="3" /><br />\
						            	<?php echo $cur_symbol; ?> <input type="text" name="<?php echo $this->id; ?>_bundle_cost[' + size + ']" class="bundle_cost" placeholder="0.00" size="6" /></td>\
								    <td><input type="checkbox" name="<?php echo $this->id; ?>_default[' + size + ']" class="default" /></td>\
						    </tr>').appendTo('#<?php echo $this->id; ?>_table_rates table tbody');

						return false;
					});

					// Duplicate row
					jQuery('#<?php echo $this->id; ?>_table_rates a.double').live('click', function(){
						var size = jQuery('#<?php echo $this->id; ?>_table_rates tbody .cart_rate').size();

						jQuery('#<?php echo $this->id; ?>_table_rates table tbody tr td.check-column input:checked').each(function(i, el){
							
							jQuery('<tr class="cart_rate">\
							    <td class="check-column"><input type="checkbox" name="select" /></td>\
			            			    <td><input type="text" name="<?php echo $this->id; ?>_title[' + size + ']" class="title" size="25" value="' + jQuery(el).closest('tr').find('.title').val() +'" /></td>\
		            			    	<td><input type="text" name="<?php echo $this->id; ?>_identifier[' + size + ']" class="identifier" size="25" /></td>\
					                    <td><select name="<?php echo $this->id; ?>_zone[' + size + ']" class="zone"><?php echo addslashes($zoneOpsJS); ?></select></td>\
					                    <td><select name="<?php echo $this->id; ?>_class[' + size + ']" class="class"><option>*</option><?php echo addslashes($classOpsG); ?></select></td>\
					                    <td><select name="<?php echo $this->id; ?>_cond[' + size + ']" class="condition"><?php echo addslashes($condOpsG); ?></select></td>\
					                    <td><input type="text" name="<?php echo $this->id; ?>_min[' + size + ']" class="min" value="' + jQuery(el).closest('tr').find('.min').val() +'" placeholder="0" size="6" /></td>\
					                    <td><input type="text" name="<?php echo $this->id; ?>_max[' + size + ']" class="max" value="' + jQuery(el).closest('tr').find('.max').val() +'" placeholder="*" size="6" /></td>\
					                    <td><select name="<?php echo $this->id; ?>_shiptype[' + size + ']" class="shiptype"><option><?php echo $cur_symbol; ?></option><option>%</option><option>x</option><option>w</option><option>D</option></select>\
					                    	<input type="text" name="<?php echo $this->id; ?>_cost[' + size + ']" class="cost" value="' + jQuery(el).closest('tr').find('.cost').val() +'" placeholder="0.00" size="6" /></td>\
							            <td>qty >= <input type="text" name="<?php echo $this->id; ?>_bundle_qty[' + size + ']" placeholder="0" value="' + jQuery(el).closest('tr').find('.bundle_qty').val() +'" size="3" /><br />\
							            	<?php echo $cur_symbol; ?> <input type="text" name="<?php echo $this->id; ?>_bundle_cost[' + size + ']" value="' + jQuery(el).closest('tr').find('.bundle_cost').val() +'" class="bundle_cost" placeholder="0.00" size="6" /></td>\
							            <td><input type="checkbox" name="<?php echo $this->id; ?>_default[' + size + ']" class="default" /></td>\
							    </tr>').appendTo('#<?php echo $this->id; ?>_table_rates table tbody');

							jQuery('#<?php echo $this->id; ?>_table_rates table tbody tr').last().find('select.zone').val(jQuery(el).closest('tr').find('select.zone').val())
							jQuery('#<?php echo $this->id; ?>_table_rates table tbody tr').last().find('select.class').val(jQuery(el).closest('tr').find('select.class').val())
							jQuery('#<?php echo $this->id; ?>_table_rates table tbody tr').last().find('select.condition').val(jQuery(el).closest('tr').find('select.condition').val())
							jQuery('#<?php echo $this->id; ?>_table_rates table tbody tr').last().find('select.shiptype').val(jQuery(el).closest('tr').find('select.shiptype').val())
							if(jQuery(el).closest('tr').find('.default').attr('checked') == 'checked') jQuery('#<?php echo $this->id; ?>_table_rates table tbody tr').last().find('.default').attr('checked','checked');

							size = size + 1;
						});
						return false;
					});

					// Remove row
					jQuery('#<?php echo $this->id; ?>_table_rates a.remove').live('click', function(){
						var answer = confirm("<?php _e('Delete the selected rates?', 'woocommerce'); ?>")
						if (answer) {
							jQuery('#<?php echo $this->id; ?>_table_rates table tbody tr td.check-column input:checked').each(function(i, el){
								jQuery(el).closest('tr').remove();
							});
						}
						return false;
					});

					jQuery('#<?php echo $this->id; ?>_handling_rates a.add').live('click', function(){

					var size = jQuery('#<?php echo $this->id; ?>_handling_rates tbody .handling_fees').size();
					jQuery('<tr class="handling_fees">\
			               		    <td class="check-column"><input type="checkbox" name="select" /></td>\
				                    <td><select name="<?php echo $this->id; ?>_handling_country[' + size + ']"><?php echo addslashes($zoneOpsJS); ?></select></td>\
			               		    <td><?php echo $cur_symbol; ?> <input type="text" name="<?php echo $this->id; ?>_handling_fee[' + size + ']" placeholder="0.00" size="5" /> &nbsp; % <input type="text" name="<?php echo $this->id; ?>_handling_percent[' + size + ']" placeholder="0.00" size="5" /></td>\
							    </tr>').appendTo('#<?php echo $this->id; ?>_handling_rates table tbody');
					return false;
					});

					// Remove row
					jQuery('#<?php echo $this->id; ?>_handling_rates a.remove').live('click', function(){
						var answer = confirm("<?php _e('Delete the selected rates?', 'woocommerce'); ?>")
						if (answer) {
							jQuery('#<?php echo $this->id; ?>_handling_rates table tbody tr td.check-column input:checked').each(function(i, el){
								jQuery(el).closest('tr').remove();
							});
						}
						return false;
					});

					jQuery('#refresh_list').live('click', function(){
						var tableAr = new Array();
						var titlesAr = new Array();
						jQuery('#<?php echo $this->id; ?>_order_titles table tbody tr').each(function(i, el){
							titlesAr.push(jQuery(el).closest('tr').find('td.title span').html());
						});
						jQuery('#<?php echo $this->id; ?>_table_rates table tbody tr').each(function(i, el){
							tableAr.push(jQuery(el).closest('tr').find('input.identifier').val());
						});

					    for ( x = 0; x < tableAr.length; x++ ) {
				            if ( jQuery.inArray(tableAr[x], titlesAr) == -1 ) {
					        	titlesAr.push( tableAr[x] );
					        	jQuery('<tr><td class="title '+tableAr[x]+'"><input type="hidden" name="<?php echo $this->id; ?>_title_order[]" value="'+tableAr[x]+'"><span>'+tableAr[x]+'</span></td></tr>').appendTo('#<?php echo $this->id; ?>_order_titles table tbody');
					        }
					    }

					    for ( y = 0; y < titlesAr.length; y++ ) {
				            if ( jQuery.inArray(titlesAr[y], tableAr) == -1 ) {
					        	jQuery('#<?php echo $this->id; ?>_order_titles table tbody tr:contains("'+titlesAr[y]+'")').remove();
					        }
					    }

						return false;
					});

		            jQuery(function() {
		                var fixHelperModified = function(e, tr) {
		                    var $originals = tr.children();
		                    var $helper = tr.clone();
		                    $helper.children().each(function(index)
		                    {
		                      jQuery(this).width($originals.eq(index).width())
		                    });
		                    return $helper;
		                };
		                jQuery("#<?php echo $this->id; ?>_order_titles table tbody").sortable({
		                    helper: fixHelperModified
		                }).disableSelection();
		            });
				});
			</script>
	<?php
				} // End admin_options()


				/**
				 * process_cart_rates function.
				 *
				 * @access public
				 * @return void
				 */
				function process_table_rates() {
					global $wpdb;

					// Initialize blank arrays & save variables
					$table_rate_title = $table_rate_zone = $table_rate_class = $table_rate_cond = $table_rate_min = $table_rate_max = $table_rate_cost = $table_rate_bundle_qty = $table_rate_bundle_cost = $table_rate_default = $table_rates = $table_rate_priority = $class_scpid = $class_scp = $class_sname = $class_priorities = $class_excluded = $handling_country = $handling_fee = $handling_percent = $title_order = array();
					$saveNames = array('_title', '_identifier', '_zone', '_class', '_cond', '_min', '_max', '_shiptype', '_cost', '_bundle_qty', '_bundle_cost', '_default', '_title_order');

					// Clean table rate data
					foreach ($saveNames as $sn) {
						$save_name = 'table_rate' . $sn;
						if ( isset( $_POST[ $this->id . $sn] ) )  $$save_name = array_map( 'woocommerce_clean', $_POST[ $this->id . $sn] );
					}
					if( isset( $table_rate_title_order ) && count( $table_rate_title_order ) ) $table_rate_title_order = array_map( 'sanitize_title', $table_rate_title_order );
						else $table_rate_title_order = array();
					// Clean handling data
					$saveNames = array('_country', '_fee', '_percent');
					foreach ($saveNames as $sn) {
						$save_name = 'handling' . $sn;
						if ( isset( $_POST[ $this->id . '_handling'. $sn] ) )  $$save_name = array_map( 'woocommerce_clean', $_POST[ $this->id . '_handling' . $sn] );
					}
					// Clean classes data
					$saveNames = array('_scpid', '_scp', '_sname', '_priority', '_excluded');
					foreach ($saveNames as $sn) {
						$save_name = 'class' . $sn;
						if ( isset( $_POST[ $this->id . $sn] ) )  $$save_name = array_map( 'woocommerce_clean', $_POST[ $this->id . $sn] );
					}

					// Get max key
					$values = $class_scp;
					ksort( $values );
					$value = end( $values );
					$key = key( $values );

					for ( $i = 0; $i <= $key; $i++ ) {
						if(isset($class_scp[$i])) {
							if($class_priority[$i] == '' || !is_numeric($class_priority[$i])) $class_priority[$i] = '10';

							// Add priorities to class priorities array
							$class_priorities[sanitize_title($class_scpid[$i])] = array(
								"term_id" => $class_scpid[$i],
								"name" => $class_sname[$i],
								'priority' => ceil($class_priority[$i]),
								'excluded' => ( isset( $class_excluded[ $i ] ) ) ? $class_excluded[$i] : FALSE
							);
						}
					}

        			$zone_query = get_option( 'be_woocommerce_shipping_zones' );
					$zone_orders = array();
					$n = 1;
					if( count( $zone_query ) ) {
						foreach ($zone_query as $value) {
							$zone_orders[$value['zone_id']] = $n;
							$n++;
						}
					}

					// Get max key
					$values = $handling_country;
					ksort( $values );
					$value = end( $values );
					$key = key( $values );

					for ( $i = 0; $i <= $key; $i++ ) {
						if(isset($handling_country[$i])) {
							if($handling_fee[$i] == '' || !is_numeric($handling_fee[$i])) $handling_fee[$i] = '0';
							if($handling_percent[$i] == '' || !is_numeric($handling_percent[$i])) $handling_percent[$i] = '0';

							$handling_fee[$i] = number_format($handling_fee[$i], 2,  '.', '');
							$handling_percent[$i] = number_format($handling_percent[$i], 2,  '.', '');

							// Add priorities to class priorities array
							$handling_rates[sanitize_title($handling_country[$i])] = array(
								"zone" => $handling_country[$i],
								'zone_order' => $zone_orders[ $handling_country[$i] ],
								'fee' => $handling_fee[$i],
								'percent' => $handling_percent[$i]
							);
						}
					}

					// Get max key
					$values = $table_rate_title;
					ksort( $values );
					$value = end( $values );
					$key = key( $values );

					for ( $i = 0; $i <= $key; $i++ ) {
						if ( isset( $table_rate_title[$i] ) && isset( $table_rate_zone[$i] ) && isset( $table_rate_cond[$i] ) ) {

							if($table_rate_min[$i] == '') $table_rate_min[$i] = '0';
							if($table_rate_max[$i] == '') $table_rate_max[$i] = '*';
							if($table_rate_bundle_qty[$i] == '') $table_rate_bundle_qty[$i] = '0';
							if($table_rate_bundle_cost[$i] == '' && $table_rate_shiptype[$i] != 'C') $table_rate_bundle_cost[$i] = '0';
							if($table_rate_identifier[$i] == '') $table_rate_identifier[$i] = $table_rate_title[$i];
							$table_rate_identifier[$i] = sanitize_title($table_rate_identifier[$i]);

							$table_rate_priority_ind = ( isset( $table_rate_class[ $i ] ) && $table_rate_class[ $i ] != '' && $table_rate_class[ $i ] != '*' ) ? $class_priorities[ sanitize_title($table_rate_class[ $i ]) ]['priority'] : '';
							$table_rate_cost[$i] = wc_format_decimal( $table_rate_cost[$i], get_option( 'woocommerce_price_num_decimals' ), false );//number_format($table_rate_cost[$i], 2,  '.', '');
							if( $table_rate_cond[$i] == 'price' ) {
								$table_rate_min[$i] = wc_format_decimal( $table_rate_min[$i], get_option( 'woocommerce_price_num_decimals' ), false );//number_format($table_rate_min[$i], 2,  '.', '');
								if($table_rate_max[$i] != '*') $table_rate_max[$i] = wc_format_decimal( $table_rate_max[$i], get_option( 'woocommerce_price_num_decimals' ), false );//number_format($table_rate_max[$i], 2,  '.', '');
							}

							// Add to cart rates array
							$table_rates[$i] = array(
								'title' => $table_rate_title[ $i ],
								'identifier' => $table_rate_identifier[ $i ],
								'zone' => $table_rate_zone[ $i ],
								'zone_order' => $zone_orders[ $table_rate_zone[ $i ] ],
								'class' => $table_rate_class[ $i ],
								'class_priority' => $table_rate_priority_ind,
								'cond' => $table_rate_cond[ $i ],
							    'min' => $table_rate_min[ $i ],
							    'max' => $table_rate_max[ $i ],
							    'shiptype'  => $table_rate_shiptype[ $i ],
							    'cost'  => $table_rate_cost[ $i ],
							    'bundle_qty' => $table_rate_bundle_qty[ $i ],
							    'bundle_cost' => $table_rate_bundle_cost[ $i ],
							    'default' => ( isset( $table_rate_default[ $i ] ) ) ? $table_rate_default[ $i ] : 0,
							);
						}
					}

					// Obtain a list of columns
					$zone_order = $class_priority = $min = $title = $cost = array();
					if( count( $table_rates ) ) {
						foreach ($table_rates as $key => $row) {
						    $zone_order[$key]  = $row['zone_order'];
						    $class_priority[$key] = $row['class_priority'];
						    $min[$key] = $row['min'];
						    $title[$key] = $row['title'];
						    $cost[$key] = $row['cost'];
						}
						// Sort the rates based on the 5 columns
						array_multisort($zone_order, SORT_ASC,
										$class_priority, SORT_ASC,
										$min, SORT_ASC,
										$title, SORT_ASC,
										$cost, SORT_ASC, $table_rates);
					}

					update_option( $this->table_rate_options, $table_rates );

					update_option( $this->class_priorities_options, $class_priorities );

					// Obtain a list of columns
					$zone_order = $fee = $percent = array();
					if( isset( $handling_rates ) && count( $handling_rates ) ) {
						foreach ($handling_rates as $key => $row) {
						    $zone_order[$key]  = $row['zone_order'];
						    $fee[$key] = $row['fee'];
						    $percent[$key] = $row['percent'];
						}

						// Sort the base fees based on the 3 columns
						array_multisort($zone_order, SORT_ASC,
										$fee, SORT_ASC,
										$percent, SORT_ASC, $handling_rates);
					} else $handling_rates = array();

					update_option( $this->handling_rates_options, $handling_rates );

					update_option( $this->title_order_options, $table_rate_title_order );

					$this->get_table_rates();
					$this->get_class_priorities();
					$this->get_handling_rates();
					$this->get_title_order();
				}


				/**
				 * get_highest_priority_class function.
				 * sorts a multi-dimensional array by secondary value
				 *
				 * @access public
				 * @return string
				 */
				function get_highest_priority_class( $classes = array() ) {
					$classTerm = $classHigh = 0;
					$class_priorities = $this->class_priorities;

					if( isset( $classes ) && is_array( $classes ) && count( $classes ) ) {
						$new_class_priorities = array();
						foreach ($classes as $class) {
							$term = get_term_by( 'slug', $class, 'product_shipping_class', ARRAY_A );
							if( isset( $class_priorities[ $term['term_id'] ] ) ) $new_class_priorities[ $term['term_id'] ] = $class_priorities[ $term['term_id'] ];
						}
						$class_priorities = $new_class_priorities;
					}

					foreach ( $class_priorities as $key => $cls ) {
						if( $cls['excluded'] != 'on' && $cls['priority'] > $classHigh ) {
							$classTerm = $key;
							$classHigh = $cls['priority'];
						}
					}

					return $classTerm;
				}


				/**
				 * get_cart_rates function.
				 *
				 * @access public
				 * @return void
				 */
				function get_table_rates() {
					$this->table_rates = array_filter( (array) get_option( $this->table_rate_options ) );
				}



				/**
				 * get_class_priorities function.
				 *
				 * @access public
				 * @return void
				 */
				function get_class_priorities() {
					$this->class_priorities = array_filter( (array) get_option( $this->class_priorities_options ) );
				}


			    /**
				 * get_handling_rates function.
				 *
				 * @access public
				 * @return void
				 */
				function get_handling_rates() {
					$this->handling_rates = array_filter( (array) get_option( $this->handling_rates_options ) );
				}


			    /**
				 * get_handling_rates function.
				 *
				 * @access public
				 * @return void
				 */
				function get_title_order() {
					$this->title_order = array_filter( (array) get_option( $this->title_order_options ) );
				}


			    /**
			     * is_available function.
			     *
			     * @access public
			     * @param mixed $package
			     * @return bool
			     */
			    function is_available( $package ) {
			    	global $woocommerce;

			    	if ($this->enabled=="no") return false;

					return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true, $package );
			    }

				/**
				 * be_zone_update_notice
				 *
				 * @package		WooCommerce/Classes/Shipping
				 * @access public
				 * @param array $methods
				 * @return array
				 */
				function be_zone_update_notice() {
					global $wpdb;

					$current_zones = get_option( 'be_woocommerce_shipping_zones' );
					if( !isset( $current_zones ) || $current_zones == '' || ( is_array($current_zones) && count( $current_zones ) == 0 ) ) {
						if( !isset( $_GET['upgrade'] ) || ( isset( $_GET['upgrade'] ) && $_GET['upgrade'] != 'zones' ) ) {
							$old_version = (float) get_option('be_table_rate_version');
							if( $old_version <= 3.2 ) {
								$findTable = $wpdb->get_results("SHOW TABLES LIKE '".$wpdb->prefix."woocommerce_shipping_zones'", ARRAY_A);
								if( count( $findTable ) ) {
									echo '<div class="error" style="font-weight:bold;"><p>'.__( 'ATTENTION: Version 3.3 of the Table Rate Shipping plugin for WooCommerce introduced a new, more efficient way of storing zone information. You will need to update your system in order for the plugin to continue functioning properly.', 'woocommerce' ).' <a href="'.admin_url( 'admin.php?page='.$GLOBALS['settings_slug'].'&tab=shipping_zones&upgrade=zones' ).'"><button>Upgrade Zones</button></a></p></div>';
								} else
									echo '<div class="error" style="font-weight:bold;"><p>'.__( 'ATTENTION: You have not defined any shipping zones for the WooCommerce Table Rate Shipping plugin. You must setup your zones before creating any rates in the shipping method\'s settings page.','woocommerce' ).' <a href="'.admin_url( 'admin.php?page='.$GLOBALS['settings_slug'].'&tab=shipping_zones' ).'" class="button-primary">Setup Zones</a></p></div>';
							} else
								echo '<div class="error" style="font-weight:bold;"><p>'.__( 'ATTENTION: You have not defined any shipping zones for the WooCommerce Table Rate Shipping plugin. You must setup your zones before creating any rates in the shipping method\'s settings page.','woocommerce' ).' <a href="'.admin_url( 'admin.php?page='.$GLOBALS['settings_slug'].'&tab=shipping_zones' ).'" class="button-primary">Setup Zones</a></p></div>';
							}
					}
				}

				/**
				 * install function.
				 *
				 * @package		WooCommerce/Classes/Shipping
				 * @access public
				 * @param array $methods
				 * @return array
				 */
				function install_plugin_button() {
					global $wpdb;

					// upgrade original pre-zone versions
					$old_version = get_option('be_table_rate_version');
					if( (float) $old_version <= 3.0 ) {
						$zones = array();
						$zone_id = 1;
						$table_rates = $this->table_rates;
						if(count($table_rates) > 0) {
							if( !array_key_exists('zone', $table_rates[0])) {
								foreach ($table_rates as $key => $value) {
									$tmp = array('country'=>$value['country'],'zip'=>$value['zip']);
									$is_zone = false;
									foreach ($zones as $zk => $z) {
										if($z['country'] == $value['country'] && $z['zip'] == $value['zip'])
											$is_zone = $zk;
									}
									if($is_zone) {
										$table_rates[$key]['zone'] = $is_zone;
									} else {
										$zone_country = ($value['country']) ? array($value['country']) : array();
										$zone_type = ($value['country'] == '*') ? 'everywhere' : 'postal';
										$zone_postal = ($value['zip'] == '*') ? '' : $value['zip'];
										$zones[ $zone_id ] = array(
											'zone_id' => $zone_id,
											'zone_enabled' => 'on',
											'zone_title' => 'Zone'.$zone_id,
											'zone_description' => '',
											'zone_type' => $zone_type,
											'zone_country' => $zone_country,
											'zone_postal' => $zone_postal,
											'zone_except' => '',
											'zone_order' => $zone_id,
											);
										$table_rates[$key]['zone'] = $zone_id_new;
										$zone_id++;
									}
									unset($table_rates[$key]['country']);
									unset($table_rates[$key]['zip']);
								}
								update_option( $this->table_rate_options, $table_rates );
								$this->get_table_rates();
							}
						}
					}

					// upgrade users from 3.2.x
					$findTable = $wpdb->get_results("SHOW TABLES LIKE '".$wpdb->prefix."woocommerce_shipping_zones'", ARRAY_A);
					if( count( $findTable ) ) {
						$zones = array();
						$selectCurrentZones = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."woocommerce_shipping_zones", ARRAY_A);
						if(count($selectCurrentZones)) {
							foreach ($selectCurrentZones as $key => $zone) {
								$zones[$zone['zone_id']] = array(
									'zone_id' => $zone['zone_id'],
									'zone_enabled' => $zone['zone_enabled'],
									'zone_title' => $zone['zone_title'],
									'zone_description' => $zone['zone_description'],
									'zone_type' => $zone['zone_type'],
									'zone_country' => $zone['zone_country'],
									'zone_postal' => $zone['zone_postal'],
									'zone_order' => $zone['zone_order'],
									);
							}
						}
						update_option('be_woocommerce_shipping_zones',$zones);
					}

					if( !get_option('be_table_rate_version') )
						add_option('be_table_rate_version',BE_Table_Rate_Shipping::$version);
					else
						update_option('be_table_rate_version',BE_Table_Rate_Shipping::$version);
?>
		            <div class="updated" style="font-weight:bold;">
		                <p><?php _e('Your zones have been updated. Please test your forms to ensure that everything is in working order.','be_table_rate'); ?></p>
		            </div>
	                <p><a href="<?php echo admin_url( 'admin.php?page='.$GLOBALS['settings_slug'].'&tab=shipping&section=be_table_rate_shipping' ); ?>" class="button-primary">Continue to Table Rate Settings</a> <a href="<?php echo admin_url( 'admin.php?page='.$GLOBALS['settings_slug'].'&tab=shipping_zones' ); ?>">View Shipping Zones</a></p>
<?php
					//$redirect = add_query_arg( array('upgrade' => 'true'));
					//wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=shipping_zones&upgrade=true' ) );
					//exit;
				}

				/**
				 * Initialize CSS stylesheet for use with plugin
				 */
				public function register_plugin_styles() {
					wp_register_style( 'be-table-rate-shipping', plugins_url( 'assets/plugin.css', __FILE__ ) );
					wp_enqueue_style( 'be-table-rate-shipping' );
					wp_enqueue_script( 'jquery-ui-core' );
				}
			}


			/**
			 * add_cart_rate_method function.
			 *
			 * @package		WooCommerce/Classes/Shipping
			 * @access public
			 * @param array $methods
			 * @return array
			 */
			function add_table_rate_method( $methods ) {
				$methods[] = 'BE_Table_Rate_Shipping';
				return $methods;
			}
			add_filter( 'woocommerce_shipping_methods', 'add_table_rate_method' );

			/**
			 * ensure zone table is created and being used
			 */
			//register_activation_hook( __FILE__, array( 'BE_Table_Rate_Shipping', 'activate' ) );
			//add_action( 'admin_init', array( 'BE_Table_Rate_Shipping', 'update' ) );
			add_action( 'admin_notices', array( 'BE_Table_Rate_Shipping', 'be_zone_update_notice' ) );
		}
	}
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ts_add_plugin_action_links' );
function ts_add_plugin_action_links( $links ) {
	return array_merge(
		array(
			'settings' => '<a href="' . get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page='.$GLOBALS['settings_slug'].'&tab=shipping&section=BE_Table_Rate_Shipping">Settings</a>'
		),
		$links
	);
 
}