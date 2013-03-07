<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Alipay Payment Gateway
 *
 * Provides a Alipay Payment Gateway.
 *
 * @class 		WC_Alipay
 * @extends		WC_Payment_Gateway
 * @version		1.0
 */
function alipay_gateway_init() {
	if( !class_exists('WC_Payment_Gateway') ) return;
 	load_plugin_textdomain( 'alipay', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/'  ); 

	class WC_Alipay extends WC_Payment_Gateway {
                var $notify_url;
	   /**
		 * Constructor for the gateway.
		 *
		 * @access public
		 * @return void
		 */
		public function __construct() {
			global $woocommerce;
			
			$this->id = 'alipay';
			$this->icon = apply_filters('woocommerce_alipay_icon', plugins_url('images/alipay.gif', __FILE__));
			$this->has_fields = false;			
		 
			// Load the form fields.
			$this->init_form_fields();
		 
			// Load the settings.
			$this->init_settings();
		 
			// Define user set variables
			$this->title                    = $this->get_option( 'title' );
			$this->description              = $this->get_option( 'description' );
			$this->alipay_account           = $this->get_option( 'alipay_account' );
			$this->partnerID                = $this->get_option( 'partnerID' );
			$this->secure_key               = $this->get_option( 'secure_key' );
			$this->payment_method           = $this->get_option( 'payment_method' ); 
			$this->debug                    = $this->get_option( 'debug' );
			$this->form_submission_method   = $this->get_option( 'form_submission_method' ) == 'yes' ? true : false;
			$this->availability		= $this->get_option( 'availability' );
			$this->countries		= $this->get_option( 'countries' );
			
			$this->secure_key 		= $this->get_option( 'secure_key' );	
			
			$this->notify_url 		= str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Alipay', home_url( '/' ) ) );//trailingslashit(home_url()); 
			
			//Log
			if ($this->debug=='yes') $this->log = $woocommerce->logger();
			
			// Actions
			add_action( 'woocommerce_api_wc_alipay', array(&$this, 'check_alipay_response') );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action('woocommerce_thankyou_alipay', array(&$this, 'thankyou_page'));
			add_action('woocommerce_receipt_alipay', array(&$this, 'receipt_page'));
			
			//Availability check
			if( $this->availability == 'specific' ) {
				add_filter('woocommerce_available_payment_gateways', array(&$this,'remove_alipay'));
			}
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
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'alipay' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Alipay Payment', 'alipay' ),
					'default' => 'no'
				),
				'title' => array(
					'title' => __( 'Title', 'alipay' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'alipay' ),
					'default' => __( 'Alipay', 'alipay' )
				),
				'description' => array(
					'title' => __( 'Description', 'alipay' ),
					'type' => 'textarea',
					'default' => __('Pay via Alipay, if you don\'t have an Alipay account, you can also pay with your debit card or credit card', 'alipay')
				),
				'payment_method' => array(
					'title' => __( 'Alipay Payment Gateway Type', 'alipay' ),
					'type' => 'select',
					'description' => __( 'Choose a payment method', 'alipay' ),
					'options' => array(
						'direct' => __('Direct Payment', 'alipay'),
						'escrow' => __('Escrow Payment', 'alipay'),
						'dualfun' => __('Dual(Direct Payment + Escrow payment)', 'alipay')
					)
				),
				'partnerID' => array(
					'title' => __('Partner ID', 'alipay'),
					'type' => 'text',
					'description' => __('Please enter the partner ID<br />If you don\'t have one, <a href="https://b.alipay.com/newIndex.htm" target="_blank">click here</a> to get.', 'alipay'),
					'css' => 'width:400px'
				),
				'secure_key' => array(
					'title' => __('Security Key', 'alipay'),
					'type' => 'text',
					'description' =>__('Please enter the security key<br />If you don\'t have one, <a href="https://b.alipay.com/newIndex.htm" target="_blank">click here</a> to get.', 'alipay'),
					'css' => 'width:400px'
				),
				'alipay_account' =>  array(
					'title' =>	__('Alipay Account', 'alipay'),
					'type' => 'text',
					'description' => __('Please enter your Alipay Email; this is needed in order to take payment.', 'alipay'),
					'css' => 'width:200px'
				),
				'form_submission_method' => array(
					'title' => __( 'Submission method', 'alipay' ),
					'type' => 'checkbox',
					'label' => __( 'Use form submission method.', 'alipay' ),
					'description' => __( 'Enable this to post order data to Alipay via a form instead of using a redirect/querystring.', 'alipay' ),
					'default' => 'no'
				),
				'availability' => array(
						'title' 		=> __( 'Method availability', 'alipay' ),
						'type' 			=> 'select',
						'default' 		=> 'all',
						'class'			=> 'availability',
						'options'		=> array(
							'all' 		=> __( 'All allowed countries', 'alipay' ),
							'specific' 	=> __( 'Specific Countries', 'alipay' ),
					)
				),
				'countries' => array(
					'title' 		=> __( 'Specific Countries', 'alipay' ),
					'type' 			=> 'multiselect',
					'class'			=> 'chosen_select',
					'css'			=> 'width: 450px;',
					'default' 		=> '',
					'options'		=> $woocommerce->countries->countries,
				),
				'debug' => array(
					'title' => __( 'Debug Log', 'alipay' ),
					'type' => 'checkbox',
					'label' => __( 'Enable logging', 'alipay' ),
					'default' => 'no',
					'description' => __( 'Log Alipay events, such as trade status, inside <code>woocommerce/logs/alipay.txt</code>', 'alipay' ),
				)
		
			);
		}
		
		
		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and account etc.
		 *
		 * @since 1.0
		 */
		public function admin_options() {
	
			?>
			<h3><?php _e('Alipay', 'alipay'); ?></h3>
			<p><?php _e('Alipay is one of the most widely used payment method in China, customer can pay with or without an alipay account', 'alipay'); ?></p>
			<table class="form-table">
			<?php
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
			?>
			</table><!--/.form-table-->
			<?php
		}
		
		/**
		 * Availability check,remove alipay if not wanted
		 *
		 * @access public
		 * @param array $available_gateways
		 * @return array
		 */
		function remove_alipay( $available_gateways ) {
			global $woocommerce;
			
			$ship_to_countries = '';
			$ship_to_countries = $this->countries;

			if ( is_array( $ship_to_countries ) ){
				if ( ! in_array( $woocommerce->customer->get_country(), $ship_to_countries ) ) {
					if( isset($available_gateways['alipay']) ) unset($available_gateways['alipay']);
				}
			}
			return $available_gateways;
		}
	
		
		/**
		 * Get Alipay Args for passing to Alipay
		 *
		 * @access public
		 * @param mixed $order
		 * @return array
		 */
		function get_alipay_args( $order ) {
			global $woocommerce;
	
			$paymethod = 'directPay';
			$order_id = $order->id;
			
			if ($this->debug=='yes') $this->log->add( 'alipay', 'Generating payment form for order #' . $order_id . '. Notify URL: ' . $this->notify_url);
			
			$buyer_name = $order->billing_last_name.$order->billing_first_name;
			if( $this->payment_method == 'direct') 
				$service = 'create_direct_pay_by_user';
			else if ( $this->payment_method == 'dualfun' )
				$service = 'trade_create_by_buyer';
			else if ( $this->payment_method == 'escrow' )
				$service = 'create_partner_trade_by_buyer';
			$total_fee = $order->order_total;
			
			$alipay_args = array(
				"service"		=> $service,
				"payment_type"		=> "1",
				
				"partner"		=> trim($this->partnerID),
				"_input_charset"	=> 'utf-8',
				"seller_email"		=> trim($this->alipay_account),
				"return_url"		=> $this->get_return_url( $order ),
				"notify_url"		=> $this->notify_url,
				
				"out_trade_no"		=> $order->order_key.'|'.$order->id,
				"subject"		=> $buyer_name,
				"body"			=> $order->customer_note,
				"price"			=> $total_fee,
				"quantity"		=> 1
			);
			
			if( $this->payment_method != 'direct' ) {
				$add_args = array(
					"logistics_fee"		=> '0',
					"logistics_type"	=> 'EXPRESS', //optional EXPRESS（快递）、POST（平邮）、EMS（EMS）
					"logistics_payment"	=> 'SELLER_PAY', //optional SELLER_PAY（卖家承担运费）、BUYER_PAY（买家承担运费）
					
					"receive_name"		=> $buyer_name,
					"receive_address"	=> $order->billing_address_1,
					"receive_zip"		=> $order->shipping_postcode,
					//"receive_phone"	=> $order->billing_phone,
					"receive_mobile"	=> $order->billing_phone
				);
				$alipay_args = array_merge($alipay_args, $add_args);
			}
		
			$alipay_args = apply_filters( 'woocommerce_alipay_args', $alipay_args );

			return $alipay_args;
		}
		
		
		/**
		 * Get Alipay configuration
		 *
		 * @access public
		 * @param mixed $order
		 * @return array
		 */
		function get_alipay_config( $order ){
			$alipay_config = array();
			$alipay_config['partner']      = trim($this->partnerID);
			$alipay_config['key']          = trim($this->secure_key);
			$alipay_config['seller_email'] = trim($this->alipay_account);
			$alipay_config['return_url']   = $this->get_return_url( $order );
			$alipay_config['notify_url']   = $this->notify_url;
			
			$alipay_config['sign_type']    = 'MD5';
			$alipay_config['input_charset']= 'utf-8';
			$alipay_config['transport']    = 'http';
			$alipay_config = apply_filters( 'woocommerce_alipay_config_args', $alipay_config );
			return $alipay_config;
		}
		
		
		/**
		 * Build Alipay Query String for redirection to Alipay using GET method
		 *
		 * @access public
		 * @param mixed $order
		 * @return string
		 */
		function build_alipay_string( $order ) {
			require_once( "lib/alipay_service.class.php");
			//Get alipay args
			$alipay_args = $this->get_alipay_args( $order ); 
			$alipay_config = $this->get_alipay_config( $order );
			
			$alipayService = new AlipayService( $alipay_config );
			$alipay_submit = new AlipaySubmit();
			$para = $alipay_submit->buildRequestPara($alipay_args,$alipay_config);
			$query_string = http_build_query( $para, '', '&' );
			$alipay_string = $alipayService->alipay_gateway_new.$query_string;
			
			return $alipay_string;			
		}	
		

		/**
		 * Payment form on checkout page
		 *
		 * @access public
		 * @return void
		 */
		function payment_fields() {
			if ($this->description) echo wpautop(wptexturize($this->description));
		}
		 
		/**
		 * Return page of Alipay, show Alipay Trade No. 
		 *
		 * @access public
		 * @param mixed Sync Notification
		 * @return void
		 */ 
		function thankyou_page($order_id) {
			global $woocommerce;
			if ( isset($_GET['trade_status']) ){
				require_once("lib/alipay_notify.class.php");
				$aliapy_config = $this->get_alipay_config( $order );
				$alipayNotify = new AlipayNotify($aliapy_config);	
						
				$get_temp = $_GET;				
				unset($_GET['order']);
				unset($_GET['key']);
				
				if ($this->debug=='yes') $log = true;	
				$verify_result = $alipayNotify->verifyReturn( $log );
				if( $verify_result ) {
					$trade_no		= $_GET['trade_no'];
					$order = &new WC_Order( $order_id ); 
					echo '<ul class="order_details">
					<li class="alipayNo">'.__('Your Alipay Trade No.: ', 'alipay'). '<strong>' . $trade_no . '</strong></li>
					</ul>';	
					
				}
				$_GET = $get_temp;
			}
		}
		
		
		/**
		 * Generate the alipay button link (POST method)
		 *
		 * @access public
		 * @param mixed $order_id
		 * @return string
		 */
		function generate_alipay_form( $order_id ) {
			global $woocommerce;
	
			$order = new WC_Order( $order_id );
			require_once( "lib/alipay_service.class.php");
		
			$alipay_args = $this->get_alipay_args( $order );
			$alipay_config = $this->get_alipay_config( $order );
								
			$alipayService = new AlipayService( $alipay_config );
			$alipay_submit = new AlipaySubmit();
			$alipay_adr = $alipayService->alipay_gateway_new;			
			$para = $alipay_submit->buildRequestPara($alipay_args,$alipay_config);
			
			$alipay_args_array = array();
	
			foreach ($para as $key => $value) {
				$alipay_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
			}
			
			$woocommerce->add_inline_js('
				jQuery("body").block({
						message: "<img src=\"' . esc_url( apply_filters( 'woocommerce_ajax_loader_url', $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif' ) ) . '\" alt=\"Redirecting&hellip;\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Alipay to make payment.', 'alipay').'",
						overlayCSS:
						{
							background: "#fff",
							opacity: 0.6
						},
						centerY: false,
						css: {
							top:			"20%",
							padding:        20,
							textAlign:      "center",
							color:          "#555",
							border:         "3px solid #aaa",
							backgroundColor:"#fff",
							cursor:         "wait",
							lineHeight:		"32px"
						}
					});
				jQuery("#submit_alipay_payment_form").click();				
			');
			
			return '<form id="alipaysubmit" name="alipaysubmit" action="'.$alipay_adr.'_input_charset='.trim(strtolower($alipay_config['input_charset'])).'" method="post" target="_top">' . implode('', $alipay_args_array) . '
					<input type="submit" class="button-alt" id="submit_alipay_payment_form" value="'.__('Pay via Alipay', 'alipay').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'alipay').'</a>
				</form>';
	
		}
	
		
		/**
		 * Process the payment and return the result
		 *
		 * @access public
		 * @param int $order_id
		 * @return array
		 */
		function process_payment( $order_id ) {
			global $woocommerce;		 
			$order = &new WC_Order( $order_id );	 	
		 	if( !$this->form_submission_method ) {
				$redirect = $this->build_alipay_string( $order );		
				if ($this->debug=='yes') $this->log->add( 'alipay', 'Query string: '.$redirect);
				return array(
					'result' 	=> 'success',
					'redirect'	=> $redirect
				);	
			} else {
				return array(
					'result' 	=> 'success',
					'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
				);
			}		
		}	
		
		
		/**
		 * Output for the order received page.
		 *
		 * @access public
		 * @return void
		 */
		function receipt_page( $order ) {

			echo '<p>'.__('Thank you for your order, please click the button below to pay with Alipay.', 'alipay').'</p>';
	
			echo $this->generate_alipay_form( $order );
	
		}
		
		
		/**
		 * Check for Alipay IPN Response
		 *
		 * @access public
		 * @return void
		 */
		function check_alipay_response() {
			global $woocommerce;
			@ob_clean();
			if ( isset($_POST['seller_id']) && $_POST['seller_id'] == $this->partnerID ){
				if ($this->debug=='yes') $this->log->add( 'alipay','Received notification from Alipay, the order number is: '.$_POST['out_trade_no']);
					
				//Get order id
				$out_trade_no	= $_POST['out_trade_no'];
				$array =  explode('|', $out_trade_no);
				$order_id = $array[1];
				if( !$order_id || !is_numeric($order_id ) ) wp_die("Invalid Order ID");
				
				//Get alipay config
				$order = new WC_Order( $order_id );
				$alipay_config = $this->get_alipay_config( $order );
				
				//Verify alipay's notification
				require_once("lib/alipay_notify.class.php");				
				$alipayNotify = new AlipayNotify($alipay_config);	
				
				//Log verification
				if ($this->debug=='yes') $log = true;		
				$verify_result = $alipayNotify->verifyNotify( $log );
				
				if ($this->debug=='yes'){
					$verify_result = $verify_result? 'Valid' : 'Invalid';
					if ($this->debug=='yes') $this->log->add( 'alipay','Verification result: '. $verify_result);
				}
					
				if($verify_result) {
					
					if($this->payment_method == 'direct') {
						 if($_POST['trade_status'] == 'TRADE_FINISHED' || $_POST['trade_status'] == 'TRADE_SUCCESS') {
                                                        $order->add_order_note( __('The order is completed', 'alipay') );
						 	$order->payment_complete();								
							$this->successful_request( $_POST);
						 } 
					
					} else {
						if($_POST['trade_status'] == 'WAIT_BUYER_PAY') {
							$order->add_order_note( __('Order received, awaiting payment', 'alipay') );					
							$this->successful_request( $_POST);
						} else if($_POST['trade_status'] == 'WAIT_SELLER_SEND_GOODS') {
							$order->update_status('processing', __('Payment received, awaiting fulfilment', 'alipay'));
							$woocommerce->cart->empty_cart();					
							$this->successful_request( $_POST);
						} else if($_POST['trade_status'] == 'WAIT_BUYER_CONFIRM_GOODS') {
							$order->add_order_note( __('Your order has been shipped, awaiting buyer\'s confirmation', 'alipay') );	
							$this->successful_request( $_POST);
						} else if($_POST['trade_status'] == 'TRADE_FINISHED') {				
							$order->payment_complete();
							$order->add_order_note( __('The order is completed', 'alipay') );	
							$this->successful_request( $_POST);
						}  else {
							header('HTTP/1.1 200 OK');
							echo "success";
							exit;
						}
					}
				} else {
					wp_die("fail");
				}
			
			} else {
                            wp_die( "Alipay Notification Request Failure" );
                        }
		}	
		
		
		/**
		 * Successful Payment!
		 *
		 * @access public
		 * @param array $posted
		 * @return void
		 */
		function successful_request( $posted ){
			if ($this->debug=='yes') $this->log->add( 'alipay','Trade Status Received: ['. $posted['trade_status'] . '] For Order: ['. $_POST['out_trade_no'].']');
			header('HTTP/1.1 200 OK');
			echo "success";	
			exit;
		}
	}
		
	
	/**
	 * Add the gateway to WooCommerce
	 *
	 * @access public
	 * @param array $methods
	 * @package		WooCommerce/Classes/Payment
	 * @return array
	 */
	function add_alipay_gateway( $methods ) {
		$methods[] = 'WC_Alipay'; return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'add_alipay_gateway' );
}
?>