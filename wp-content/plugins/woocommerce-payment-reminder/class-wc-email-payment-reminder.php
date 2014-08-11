<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Customer Payment Reminder Email
 *
 * An email to remind a client to send its payment
 *
 * @class 		WC_Email_Payment_Reminder
 * @version		1.0.0
 * Version: 1.0
 * Author: MB Création
 * Author URI: http://www.mbcreation.net
 * @extends 	WC_Email
 */
class WC_Email_Payment_Reminder extends WC_Email {

	/**
	 * Constructor
	 */
	 
	function __construct() {

		$this->id 				= 'payment_reminder';
		$this->title 			= __( 'Payment reminder', 'wopar' );
		$this->description		= __( 'This is an email sent to the customer who finished their order by didnt payed it.', 'wopar' );

		$this->heading 			= __( 'Your order is still pending', 'wopar' );
		$this->subject      	= __( 'Your order is still pending', 'wopar' );
		
		$this->content      	= $this->get_option( 'content', __( 'You put an order on {order_date}, you indicate youll pay it by {order_payment_method}, but we are still waiting for your payment !', 'wopar' ) );

		
		//take the template payment-reminder.php if exists in the theme, else use the note template
		// final template can be overrided by filters wopar_template_html_filter / wopar_template_plain_filter
		
		global $woocommerce;
		
		if(locate_template( $woocommerce->template_url.apply_filters('wopar_template_html_filter', 'emails/payment-reminder.php' )))
			$this->template_html 	= apply_filters('wopar_template_html_filter', 'emails/payment-reminder.php');
		else
			$this->template_html 	= 'emails/customer-note.php';
		
		if(locate_template( $woocommerce->template_url.apply_filters('wopar_template_plain_filter', 'emails/plain/payment-reminder.php') ))
			$this->template_plain 	= apply_filters('wopar_template_plain_filter', 'emails/plain/payment-reminder.php');
		else
			$this->template_plain 	= 'emails/plain/customer-note.php';
		
		// Call parent constructor
		parent::__construct();
	}

	
	/**
	 * get_content_html function.
	 *
	 * @access public
	 * @attribute $order_id
	 * @return null
	 */
	function trigger( $order_id ) {
		global $woocommerce;

		if ( $order_id ) {
			$this->object 		= new WC_Order( $order_id );
			$this->recipient	= $this->object->billing_email;
			
			//use filter wopar_keys_to_find_and_replace_filter to add new $key => $value replacements
			
			$keys_to_find_and_replace = array(
				'{order_date}' => date_i18n( woocommerce_date_format(), strtotime( $this->object->order_date ) ), 
				'{order_number}' => $this->object->get_order_number(), 
				'{order_payment_method}' => apply_filters('wopar_payment_method_title_filter', $this->object->payment_method_title),
				'{first_name}' => $this->object->billing_first_name,
				'{last_name}' => $this->object->billing_last_name
			);
			
			$keys_to_find_and_replace = apply_filters('wopar_keys_to_find_and_replace_filter', $keys_to_find_and_replace, $this);
			
			foreach($keys_to_find_and_replace as $key=>$replacement)
			{
				$this->find[] =  $key;
				$this->replace[] = $replacement;
			}
		}

		if ( ! $this->is_enabled() || ! $this->get_recipient() )
			return;

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * get_content_html function.
	 *
	 * @access public
	 * @return string
	 */
	function get_content_html() {
		ob_start();
		
		//in the mail template, use $customer_note to get the message defined in back-end
		
		woocommerce_get_template( $this->template_html, array(
			'order' 		=> $this->object,
			'email_heading' => $this->get_heading(),
			'customer_note' => $this->content,
			'sent_to_admin' => false,
			'plain_text' => true
		) );
		$email = ob_get_clean();
		
		$email = str_replace( $this->find, $this->replace, $email);
		
		return $email;
	}

	/**
	 * get_content_plain function.
	 *
	 * @access public
	 * @return string
	 */
	function get_content_plain() {
		ob_start();
		
		//in the mail template, use $customer_note to get the message defined in back-end
		
		woocommerce_get_template( $this->template_plain, array(
			'order' 		=> $this->object,
			'email_heading' => $this->get_heading(),
			'customer_note' => $this->content,
			'sent_to_admin' => false,
			'plain_text' => false
		) );
		
		$email = ob_get_clean();
		
		$email = str_replace( $this->find, $this->replace, strip_tags($email));
		
		return $email;
	}
	
	
	function init_form_fields() {
    	$this->form_fields = array(
			'enabled' => array(
				'title' 		=> __( 'Enable/Disable', 'woocommerce' ),
				'type' 			=> 'checkbox',
				'label' 		=> __( 'Enable this email notification', 'woocommerce' ),
				'default' 		=> 'yes'
			),
			'subject' => array(
				'title' 		=> __( 'Subject', 'woocommerce' ),
				'type' 			=> 'text',
				'description' 	=> sprintf( __( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'woocommerce' ), $this->subject ),
				'placeholder' 	=> '',
				'default' 		=> ''
			),
			'heading' => array(
				'title' 		=> __( 'Email Heading', 'woocommerce' ),
				'type' 			=> 'text',
				'description' 	=> sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', 'woocommerce' ), $this->heading ),
				'placeholder' 	=> '',
				'default' 		=> ''
			),
			'content' => array(
				'title' 		=> __( 'Email content', 'wopar' ),
				'type' 			=> 'textarea',
				'description' 	=> __( 'Content of the message sent to customer. You may put those variables : {order_date}, {order_number}, {order_payment_method}, {first_name}, {last_name}', 'wopar' ),
				'placeholder' 	=> '',
				'default' 		=> $this->content
			),
			'email_type' => array(
				'title' 		=> __( 'Email type', 'woocommerce' ),
				'type' 			=> 'select',
				'description' 	=> __( 'Choose which format of email to send.', 'woocommerce' ),
				'default' 		=> 'html',
				'class'			=> 'email_type',
				'options'		=> array(
					'plain'		 	=> __( 'Plain text', 'woocommerce' ),
					'html' 			=> __( 'HTML', 'woocommerce' ),
					'multipart' 	=> __( 'Multipart', 'woocommerce' ),
				)
			)
		);
    }
}