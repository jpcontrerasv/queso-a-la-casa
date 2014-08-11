<?php

/**
 * Plugin Name: WooCommerce Payment Reminder
 * Description: Send manually a reminder email to clients who finished their order (by cheque or bacs...) but never send their payment.
 * Version: 1.0
 * Author: MB Création
 * Author URI: http://www.mbcreation.net
 * License: http://codecanyon.net/licenses/regular_extended
 *
 */

// Required Classes

require_once('class.admin.php');

// Loader
function WooCommerce_Payment_Reminder_Plugin_Loader(){

	if(class_exists('Woocommerce')) {
		
		load_plugin_textdomain('wopar', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');

		if( is_admin() and current_user_can(apply_filters('wopar_admin_capabilities_filter', 'edit_shop_orders')) )
			$GLOBALS['WooCommerce_Payment_Reminder_Plugin_Back'] = new WooCommerce_Payment_Reminder_Plugin_Back();
	
	}
	
}

add_action( 'plugins_loaded' , 'WooCommerce_Payment_Reminder_Plugin_Loader');