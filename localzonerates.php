<?php
/*
Plugin Name: WooCommerce Local Zones Table Rate
Plugin URI: http://cheekybebeshop.com
Description: Plugin for local rate shipping using zones
Version: 0.0.1
Author: Junel Mujar
Author URI: http://cheekybebeshop.com
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	function local_zone_rates_init() {

		if ( ! class_exists( 'WC_Local_Zone_Rates_Method' ) ) {
		
			class WC_Local_Zone_Rates_Method extends WC_Shipping_Method {
				/**
				 * Constructor for your shipping class
				 *
				 * @access public
				 * @return void
				 */
				public function __construct() {
					$this->id                 = 'local_zone_rates_method';
					$this->title              = __( 'Local Zone Rates' );
					$this->method_description = __( 'Calculate shipping rates based on zones (group of postal code)' ); // 
					$this->enabled            = "yes"; // This can be added as an setting but for this example its forced enabled
					$this->title              = "Local Zone Rates Method"; // This can be added as an setting but for this example its forced.
					$this->init();
				}

				/**
				 * Init your settings
				 *
				 * @access public
				 * @return void
				 */
				function init() {

					// Load the settings API
					$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
					$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

					// Save settings in admin if you have any defined
					add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

					$this->title                   = $this->get_option( 'title' );
					$this->city_zone               = explode(',', $this->get_option( 'city_zone' ));
					$this->provincial_zone         = explode(',', $this->get_option( 'provincial_zone' ));
					$this->city_package_rate       = explode(',', $this->get_option( 'city_package_rate' ));
					$this->provincial_package_rate = explode(',', $this->get_option( 'provincial_package_rate' ));
					$this->free_shipping_override  = json_decode($this->get_option( 'free_shipping_override' ));

					// Restart array index to 1 to act as weight based selector
					$this->city_package_rate       = $this->reindex($this->city_package_rate);
					$this->provincial_package_rate = $this->reindex($this->provincial_package_rate);
				}

				function reindex($arr) {
					$tmp = array();
					foreach (array_values($arr) as $key => $value) {
					    $tmp[$key+1] = $value;
					}
					return $tmp;
				}

				function init_form_fields() {
					
					global $woocommerce;
					$this->form_fields = array(
						'enabled' => array(
							'title'      => __( 'Enable/Disable' ),
							'type'       => 'checkbox',
							'label'      => __( 'Enable this shipping method' ),
							'default'    => 'no',
						),
						'title' => array(
							'title'      => __( 'Method Title' ),
							'type'       => 'text',
							'description'  => __( 'This controls the title which the user sees during checkout.' ),
							'default'    => __( 'Shipping Rate' ),
							'desc_tip'     => true
						),
						'city_zone' => array(
							'title'      => __( 'City Codes' ),
							'type'       => 'textarea',
							'description'  => __( 'This contains enrolled postal codes categorized under city zone' ),
							'default'    => __( '' ),
							'desc_tip'     => true
						),
						'city_package_rate' => array(
							'title'      => __( 'Package Rate for City Delivery' ),
							'type'       => 'text',
							'default'    => __( '' ),
							'desc_tip'     => true
						),	
						'provincial_package_rate' => array(
							'title'      => __( 'Package Rate for City Provincial' ),
							'type'       => 'text',
							'default'    => __( '' ),
							'desc_tip'     => true
						),		
						'free_shipping_override' => array(
							'title'      => __( 'Free Shipping Overrides' ),
							'type'      => 'textarea',
						),							
						'categories_table' => array(
							'title'      => __( 'Apply Free Shipping Based On Item Count' ),
							'type'      => 'categories_table',
						),																																				
					);

				}	

				function get_category_id( $product_id ) {
					$terms = get_the_terms( $product_id, 'product_cat' );
					$category = null;
					foreach ($terms as $term) {
						if ($term->parent != 0) {
							$category = $term;
							break;
						}
					}			
					return $category->term_id;		
				}

				/**
				 * calculate_shipping function.
				 *
				 * @access public
				 * @param mixed $package
				 * @return void
				 */
				public function calculate_shipping( $package ) {
					// This is where you'll add your rates
					
					$woocommerce = function_exists('WC') ? WC() : $GLOBALS['woocommerce'];

					$weight = ceil($woocommerce->cart->cart_contents_weight);
					
					$overrides = array();
					foreach ($this->free_shipping_override as $key => $val) {
						$overrides[$val->id] = ($val->value ? $val->value : 0);
					}

					$shipping_postcode = $woocommerce->customer->get_shipping_postcode();    
					$billing_postcode  = $woocommerce->customer->get_postcode();
					
					$ctr = 0;
					foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $cart_item ) {

						$product_id = $cart_item['product_id'];
						$qty        = $cart_item['quantity'];
						$category_id= $this->get_category_id($product_id);

						// Debug: To check if right category is returned;
			            // $this->add_rate(array
			            // (
			            //     'id'       => 'test'.$product_id,
			            //     'label'    => $product_id,
			            //     'cost'     => $category_id,
			            //     'taxes'    => '',
			            //     'calc_tax' => ''
			            // ));		

			            if (!empty($overrides[$category_id])) {
			            	$override_qty = $overrides[$category_id];
			            	if ($qty >= $override_qty) {
			            		$ctr++;
			            	}
			            } 			
					}

					if ($ctr == 0) {
						if (in_array($billing_postcode, $this->city_zone)) {
							$price = $this->city_package_rate[$weight];
						} else {
							$price = $this->provincial_package_rate[$weight];
						}
					} else {
						$this->title = 'Free Shipping';
						$price = 0;
					}

					// Debug: Check for the items meeting the free shipping rule;
		            // $this->add_rate(array
		            // (
		            //     'id'       => 'free_check123',
		            //     'label'    => 'free_check1',
		            //     'cost'     => $ctr,
		            //     'taxes'    => '',
		            //     'calc_tax' => ''
		            // ));	

		            $this->add_rate(array
		            (
		                'id'       => $this->id,
		                'label'    => $this->title,
		                'cost'     => $price,
		                'taxes'    => '',
		                'calc_tax' => ''
		            ));					

				}

				function generate_categories_table_html() {
					ob_start();
					$product_categories = get_terms( 'product_cat' );
					?>
					<th scope="row" class="titledesc">
						<label for="woocommerce_local_zone_rates_method_categories_table">Free Shipping Category Override</label>
					</th>
					<td><div style="background: white; border: 1px solid #ddd; ">
					<div style="padding: 10px; border-bottom: 1px solid #ddd; text-align: right;">
					<a href="#" class='button-primary set_free_shipping_override'>Update Overrides</a>
					<a href="#" class='button reset_override_values'>Reset All</a>
					</div>
					<div style="padding: 15px;">
					<table id="override_table" style="width:100%;">
					<thead>
					<tr>
						<th>Product Category</th>
						<th>Min. Value</th>
					</tr>
					</thead>
					<tbody>
					<?php

					$overrides = array();
					foreach ($this->free_shipping_override as $key => $val) {
						$overrides[$val->id] = ($val->value ? $val->value : 0);
					}

					//print_r($product_categories);
			     	foreach ( $product_categories as $product_category ) {
				       	if ($product_category->parent != 0) {
						?>					       		
						<tr class="override_row">
							<td><?php echo $product_category->name; ?></td>
							<td width="100" align="right" class="override_column">
								<input type="hidden" size="4" class="category_id" value="<?php echo $product_category->term_id; ?>" />
								<input type="text" size="4" class="min_value" style="text-align: right !important" value="<?php echo $overrides[$product_category->term_id] ?>" />
								<a href="#" class="button clear-override-value">&times;</a>
							</td>
						</tr>
						<?php
				     	}	
					}
					?>
					</tbody>
					</table>
					</div>
					<style>
						#override_table thead tr th {
							text-align: center;
							padding: 10px 0px 5px !important;
						}
						.override_row td {
							border-top: 1px solid #ddd;
						}
						.override_row:first-child td {
							border-top: 1px solid teal;
						}
					</style>
					<script type="text/javascript">
						jQuery(document).ready(function(){
							
							jQuery('textarea#woocommerce_local_zone_rates_method_free_shipping_override').attr('readonly','readonly');

							function update_values() {
								var overrides = [];
								jQuery('#override_table td.override_column').each(function() {
									var id, min_value;
									id        = jQuery(this).children('input.category_id').val();	
									if (!min_value || min_value == '' || min_value == 'undefined') min_value = 0;	
									min_value = jQuery(this).children('input.min_value').val();
									overrides.push({ 'id' : id, 'value' : min_value });
								});
								overrides = JSON.stringify(overrides);
								jQuery('textarea#woocommerce_local_zone_rates_method_free_shipping_override').val(overrides);
							}

							jQuery('.set_free_shipping_override').on('click', function(event) {
								event.preventDefault();
								update_values();
								jQuery('#mainform').submit();
							});
							jQuery('.clear-override-value').on('click', function(event) {
								event.preventDefault();
								jQuery(this).siblings('input.min_value').val('0');
								update_values();
							});
							jQuery('.reset_override_values').on('click', function(event) {
								event.preventDefault();
								jQuery('#override_table td.override_column').each(function() {
									jQuery(this).children('input.min_value').val('0');
								});
								update_values();
							});				
							jQuery('#mainform').on('submit', function() {
								update_values();
								return true;
							});
						});
					</script>
					</div></td>
					<?php
					return ob_get_clean();
				}	
	
			}
		}
	}

	add_action( 'woocommerce_shipping_init', 'local_zone_rates_init' );

	function local_zone_rates_method( $methods ) {
		$methods[] = 'WC_Local_Zone_Rates_Method';
		return $methods;
	}
	 
	add_filter( 'woocommerce_shipping_methods', 'local_zone_rates_method' );

}