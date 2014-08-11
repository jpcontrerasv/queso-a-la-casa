<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WooCommerce_Payment_Reminder_Plugin_Back
 * Class for backend.
 * @since 1.0
 */

if ( ! class_exists( 'WooCommerce_Payment_Reminder_Plugin_Back' ) ) {

class WooCommerce_Payment_Reminder_Plugin_Back{
		
		
		function __construct()
		{	
			$this->hooks();
		} //__construct
		
		
		protected function hooks()
		{
		
			//include the new email class
			add_filter( 'woocommerce_email_classes', array($this, 'add_email_classes'));
			
			//add an icon button to the orders list
			add_filter('woocommerce_admin_order_actions', array($this, 'order_actions'), 10, 2);
			
			//add the action of sending email while clicking the action button
			add_action('wp_ajax_woocommerce-payment-reminder', array($this, 'payment_reminder_send_email'));
			
			//add the email in the list of email you can send again in the order detail
			add_filter( 'woocommerce_resend_order_emails_available', array($this, 'add_email_to_resend_order_emails_list') );
			
			//css for button
			add_action( 'admin_init', array($this, 'css') );

					
		} // hooks
		
		
		/**
		 * Enqueue CSS for admin button
		 */
		public function css()
		{
			global $pagenow;
			if($pagenow == 'edit.php' and isset($_GET['post_type']) and $_GET['post_type']=='shop_order')
			{
				wp_register_style('wopar', plugins_url('css/wopar.css', __FILE__));
				wp_enqueue_style('wopar');
			}
		}
		
		/**
		 * Include the new email class
		 * @param array $email_classes Array of email's classes loaded on WooCommerce.
		 * @return array The array with the WC_Email_Payment_Reminder class added
		 */
		public function add_email_classes($email_classes)
		{
			if(!isset($email_classes['WC_Email_Payment_Reminder']))
			{
				require( 'class-wc-email-payment-reminder.php' );
				$email_classes['WC_Email_Payment_Reminder'] = new WC_Email_Payment_Reminder();
			}
			return $email_classes;
		}
		
		/**
		 * Add the email in the list of email you can send again in the order detail
		 * @param array $email Array of availables emails to re-send.
		 * @return array The array with our action added
		 */
		public function add_email_to_resend_order_emails_list($emails)
		{
			$emails[] = 'payment_reminder';
			return $emails;
		}
		
		/**
		 * Add an icon button to the orders list
		 * @param array $actions Array of availables actions on each order in the order list panel.
		 * @param object $the_order The order to add the action button
		 * @return array The $actions array with our action added
		 */
		public function order_actions($actions, $the_order)
		{
			if ( in_array( $the_order->status, apply_filters('wopar_button_status_filter', array( 'on-hold' )) ) )
			{
				global $woocommerce;
		
				$actions[] = array('image_url' => $woocommerce->plugin_url().'/assets/images/icons/reload.png',
					'name' => __('Send reminder', 'wopar'),
					'action' => 'wopar',
					'url' 		=> wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce-payment-reminder&order_id=' . $the_order->id ), 'woocommerce-payment-reminder' ) 
				);
			}
			return $actions;
		}
		
		
		/**
		 * Action to send the email
		 */
		public function payment_reminder_send_email() {

			if ( !is_admin() ) die;
			if ( !current_user_can(apply_filters('wopar_admin_capabilities_filter', 'edit_shop_orders')) ) wp_die( __( 'You do not have sufficient permissions to access this page.', 'woocommerce' ) );
			if ( !check_admin_referer('woocommerce-payment-reminder')) wp_die( __( 'You have taken too long. Please go back and retry.', 'woocommerce' ) );
			
			$order_id = isset($_GET['order_id']) && (int) $_GET['order_id'] ? (int) $_GET['order_id'] : '';
			if (!$order_id) die;
			
			$order = new WC_Order( $order_id );
			
			global $woocommerce;
			
			//calling it just to construct the classes... for hooking the woocommerce_email_before_order_table hook...
			$paiment_gateways = $woocommerce->payment_gateways->payment_gateways();
			
			$mailers = $woocommerce->mailer()->get_emails();
			$mailer = $mailers['WC_Email_Payment_Reminder'];
			
			do_action( 'woocommerce_before_resend_order_emails', $order );
			$mailer->trigger($order_id);
			do_action( 'woocommerce_after_resend_order_email', $order, 'payment_reminder' );
			
			wp_safe_redirect( wp_get_referer() );

		}
		
	} // WooCommerce_Payment_Reminder_Plugin_Back
}

