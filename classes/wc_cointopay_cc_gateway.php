<?php
/**
 * Define Cointopay CC Class
 *
 * @package  WooCommerce
 * @author   Cointopay <info@cointopay.com>
 * @link     cointopay.com
 */

if (!defined('ABSPATH')) exit;

class WC_CointopayCC_Gateway extends WC_Payment_Gateway {
	public $msg = [];
	private $merchant_id;
	private $api_key;
	private $secret;
	public $alt_coin_id;
	public $description;
	public $title;
	/**
	 * Define Cointopay CC Class constructor
	 **/
	public function __construct() {
		$this->id   = sanitize_key('cointopay_cc');
		$this->icon = !empty($this->get_option('logo'))
			? sanitize_text_field($this->get_option('logo')) : WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/crypto.png';

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = sanitize_text_field($this->get_option('title'));
		$this->enabled          = $this->get_option('enabled');
		$this->description = sanitize_text_field($this->get_option('description'));
		$this->merchant_id = sanitize_text_field($this->get_option('merchant_id'));
		$this->alt_coin_id = sanitize_text_field($this->get_option('cointopay_cc_alt_coin'));

		$this->api_key        = '1';
		$this->secret         = sanitize_text_field($this->get_option('secret'));
		$this->msg['message'] = '';
		$this->msg['class']   = '';
		add_action('init', array(&$this, 'cointopay_cc_check_response'));
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
			&$this,
			'process_admin_options'
		));

		add_action('woocommerce_api_' . strtolower(get_class($this)), array(
			&$this,
			'cointopay_cc_check_response'
		));


		// Valid for use.
		if (empty($this->merchant_id)) {
			$this->enabled = 'no';
		} elseif(empty($this->secret)) {
			$this->enabled = 'no';
		}

		// Checking if apikey is not empty.
		if (empty($this->merchant_id)) {
			add_action('admin_notices', array( &$this, 'api_key_missing_message' ));
		}

		// Checking if app_secret is not empty.
		if (empty($this->secret)) {
			add_action('admin_notices', array(&$this, 'secret_missing_message'));
		}
		add_action('admin_enqueue_scripts', array(&$this, 'cointopay_cc_include_custom_js'));

	}//end __construct()


	public function cointopay_cc_include_custom_js()
	{
		if (!did_action('wp_enqueue_media')) {
			wp_enqueue_media();
		}
		wp_enqueue_script('cointopay_cc_js', WC_Cointopay_CC_Payments::plugin_url() . '/assets/js/ctp_cc_custom.js', array('jquery'), '1.0', false);
		wp_localize_script('cointopay_cc_js', 'ajaxurlctpcc', array('ajaxurl' => admin_url('admin-ajax.php'),
        'ctpconfinonce'    => wp_create_nonce('cointopay_cc_ajax_nonce')));
	}
	
	/**
	 * Define initFormfields function
	 *
	 * @return mixed
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => esc_html__('Enable/Disable', 'cointopay-com-cc-only'),
				'type'    => 'checkbox',
				'label'   => esc_html__('Enable Cointopay CC Only', 'cointopay-com-cc-only'),
				'default' => 'yes',
			),
			'title'       => array(
				'title'       => esc_html__('Title', 'cointopay-com-cc-only'),
				'type'        => 'text',
				'description' => esc_html__('This controls the title the user can see during checkout.', 'cointopay-com-cc-only'),
				'default'     => esc_html__('Cointopay CC Only', 'cointopay-com-cc-only'),
			),
			'description' => array(
				'title'       => esc_html__('Description', 'cointopay-com-cc-only'),
				'type'        => 'textarea',
				'description' => esc_html__('This controls the title the user can see during checkout.', 'cointopay-com-cc-only'),
				'default'     => esc_html__('You will be redirected to cointopay.com to complete your purchase.', 'cointopay-com-cc-only'),
			),
			'merchant_id' => array(
				'title'       => esc_html__('Your MerchantID', 'cointopay-com-cc-only'),
				'type'        => 'text',
				/* translators: %s: https://cointopay.com */
				'description' => sprintf(wp_kses(__('Please enter your Cointopay Merchant ID, You can get this information in: <a href="%s" target="_blank">Cointopay Account</a>.', 'cointopay-com-cc-only'), array(  'a' => array( 'href' => array() ))), esc_url('https://cointopay.com')),
				'default'     => '',
			),
			'secret'      => array(
				'title'       => esc_html__('Security Code', 'cointopay-com-cc-only'),
				'type'        => 'text',
				/* translators: %s: https://cointopay.com */
				'description' => sprintf(wp_kses(__('Please enter your Cointopay SecurityCode, You can get this information in: <a href="%s" target="_blank">Cointopay Account</a>.', 'cointopay-com-cc-only'), array(  'a' => array( 'href' => array() ))), esc_url('https://cointopay.com')),
				'default'     => '',
			),
			'cointopay_cc_alt_coin' =>  array(
				'type'          => 'select',
				'class'         => array('cointopay_cc_alt_coin'),
				'title'         => esc_html__('Default Receive Currency', 'cointopay-com-cc-only'),
				'options'       => array(
					'blank'		=> esc_html__('Select Alt Coin', 'cointopay-com-cc-only'),
				)
			),
		);
	}

	public function admin_options()
	{ ?>
		<h3><?php esc_html_e('Cointopay CC Only Checkout', 'cointopay-com-cc-only'); ?></h3>

		<div id="wc_get_started">
			<span class="main"><?php esc_html_e('Provides a secure way to accept crypto currencies.', 'cointopay-com-cc-only'); ?></span>
			<p>
				<a href="<?php echo esc_url('https://app.cointopay.com/signup'); ?>" target="_blank" class="button button-primary">
					<?php esc_html_e('Join free', 'cointopay-com-cc-only'); ?>
				</a>
				<a href="<?php echo esc_url('https://cointopay.com'); ?>" target="_blank" class="button">
					<?php esc_html_e('Learn more about WooCommerce and Cointopay', 'cointopay-com-cc-only'); ?>
				</a>
			</p>
		</div>

		<table class="form-table">
			<?php $this->generate_settings_html(); ?>
		</table>
<?php
	}

	public function payment_fields()
	{
		if (!empty($this->description)) {
			echo esc_html($this->description);
		}
	}

	public function process_payment($order_id)
	{
		global $woocommerce;
		$order = wc_get_order($order_id);

		$item_names = array();

		if (count($order->get_items()) > 0) :
			foreach ($order->get_items() as $item) :
				if (true === $item['qty']) {
					$item_names[] = $item['name'] . ' x ' . $item['qty'];
				}
			endforeach;
		endif;
		$url      = 'https://app.cointopay.com/MerchantAPI?Checkout=true';
		$customer_email = $order->get_billing_email();
		$params = array(
			'body' => array(
				'email'                 => sanitize_email($customer_email),
				'SecurityCode'          => sanitize_text_field($this->secret),
				'MerchantID'            => sanitize_text_field($this->merchant_id),
				'Amount'                => number_format((float) $order->get_total(), 8, '.', ''),
				'AltCoinID'             => sanitize_text_field($this->alt_coin_id),
				'output'                => 'json',
				'inputCurrency'         => get_woocommerce_currency(),
				'CustomerReferenceNr'   => sanitize_text_field($order_id . '-' . $order->get_order_number()),
				'returnurl'             => esc_url_raw($this->get_return_url($order)),
				'transactionconfirmurl' => esc_url_raw(site_url('/?wc-api=WC_CointopayCC_Gateway')),
				'transactionfailurl'    => esc_url_raw($order->get_cancel_order_url()),
			),
		);
		$response = wp_safe_remote_post($url, $params);
		if ((false === is_wp_error($response)) && (200 === $response['response']['code']) && ('OK' === $response['response']['message'])) {
			$result = json_decode($response['body']);
			// Redirect to relevant paymenty page
			$htmlDom = new DOMDocument();
            $htmlDom->loadHTML($result->PaymentDetailCConly);
            $links = $htmlDom->getElementsByTagName('a');
            $matches = [];

            foreach ($links as $link) {
                $linkHref = $link->getAttribute('href');
                if (strlen(trim($linkHref)) == 0) {
                    continue;
                }
                if ($linkHref[0] == '#') {
                    continue;
                }
                $matches[] = $linkHref;
            }
            if (!empty($matches)) {
				if ($matches[0] != '') {
					return array(
					'result'   => 'success',
					'redirect' => esc_url_raw($matches[0]),
				);
				} else {
					wc_add_notice('Payment link is empty', 'error');
				}
            } else {
                wc_add_notice('pattern not match', 'error');
            }
			/*return array(
				'result'   => 'success',
				'redirect' => esc_url($result->shortURL . "?tab=fiat"),
				//'redirect' => $result->PaymentDetailCConly,
			);*/
		} else {
			$error_msg = str_replace('"', "", $response['body']);
			wc_add_notice($error_msg, 'error');
		}
	}

	private function extractOrderId(string $customer_reference_nr)
	{
		return intval(explode('-', sanitize_text_field($customer_reference_nr))[0]);
	}

	public function cointopay_cc_check_response()
	{
		if (is_admin()) {
			return;
		}
		
		// Nonce verification is not used here because this is a server-to-server callback.
		// Security is handled via transaction validation and confirm code verification.
		if(isset($_GET['wc-api']) && isset($_GET['CustomerReferenceNr']) && isset($_GET['TransactionID']))
		{
			$ctp_cc = (isset($_GET['wc-api'])) ? sanitize_text_field(wp_unslash($_GET['wc-api'])) : '';
			if ($ctp_cc == 'WC_CointopayCC_Gateway') {
				global $woocommerce;
				$woocommerce->cart->empty_cart();
				$order_id                = (isset($_GET['CustomerReferenceNr'])) ? $this->extractOrderId(sanitize_text_field(wp_unslash($_GET['CustomerReferenceNr']))) : 0;
				$order_status            = (isset($_GET['status'])) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
				$order_transaction_id    = (isset($_GET['TransactionID'])) ? sanitize_text_field(wp_unslash($_GET['TransactionID'])) : '';
				$order_confirm_code      = (isset($_GET['ConfirmCode'])) ? sanitize_text_field(wp_unslash($_GET['ConfirmCode'])) : '';
				$stripe_transaction_code = (isset($_GET['stripe_transaction_id'])) ? sanitize_text_field(wp_unslash($_GET['stripe_transaction_id'])) : '';
				$not_enough              = (isset($_GET['notenough'])) ? intval($_GET['notenough']) : 1;
				$is_live                 = (isset($_GET['is_live'])) ? (string) sanitize_text_field(wp_unslash($_GET['is_live'])) : 'true';
				$order = wc_get_order($order_id);
				$data = array(
					'mid'           => $this->merchant_id,
					'TransactionID' => $order_transaction_id,
					'ConfirmCode'   => $order_confirm_code,
				);
				if ($is_live == 'true') {
					$transactionData = $this->validate_order($data);
					if (200 !== $transactionData['status_code']) {
						get_header();
						printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'cointopay-com-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">%s</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'cointopay-com-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'), esc_html($transactionData['message']), esc_url(site_url()));
						get_footer();
						exit;
					} else {
						$transaction_order_id = $this->extractOrderId($transactionData['data']['CustomerReferenceNr']);

						if ($transactionData['data']['Security'] != $order_confirm_code) {
							get_header();
							printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'cointopay-com-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('Data mismatch! ConfirmCode doesn\'t match', 'cointopay-com-cc-only') . '</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'cointopay-com-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'), esc_url(site_url()));
							get_footer();
							exit;
						} elseif ($transaction_order_id != $order_id) {
							get_header();
							printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'cointopay-com-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('Data mismatch! CustomerReferenceNr doesn\'t match', 'cointopay-com-cc-only') . '</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'cointopay-com-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'), esc_url(site_url()));
							get_footer();
							exit;
						} elseif ($transactionData['data']['TransactionID'] != $order_transaction_id) {
							get_header();
							printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'cointopay-com-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('Data mismatch! TransactionID doesn\'t match', 'cointopay-com-cc-only') . '</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'cointopay-com-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'),  esc_url(site_url()));
							get_footer();
							exit;
						} elseif ($transactionData['data']['Status'] != $order_status) {
							get_header();
							printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'cointopay-com-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('Data mismatch! status doesn\'t match. Your order status is', 'cointopay-com-cc-only') . ' %s</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'cointopay-com-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'), esc_html($transactionData['data']['Status']), esc_url(site_url()));
							get_footer();
							exit;
						}
					}
				} else {
					// Validate via CTP plugin
					$url      = "https://app.cointopay.com/ctp/?call=verifyTransaction&stripeTransactionCode=" . $stripe_transaction_code;
					$response = wp_safe_remote_post($url, []);
					$result   = json_decode($response['body'], true);
					if ($result['statusCode'] === 200 && $result['data'] === 'fail') {
						if (1 === $not_enough) {
							$order->update_status('on-hold', sprintf(esc_html__('IPN: Payment failed notification from Cointopay because not enough', 'cointopay-com-cc-only')));
							get_header();
							printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'cointopay-com-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('The payment has been failed.', 'cointopay-com-cc-only') . '</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'cointopay-com-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'),  esc_url(site_url()));
							get_footer();
							exit;
						} else {
							$order->update_status('failed', sprintf(esc_html__('IPN: Payment failed notification from Cointopay', 'cointopay-com-cc-only')));
							get_header();
							printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'cointopay-com-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('The payment has been failed.', 'cointopay-com-cc-only') . '</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'cointopay-com-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'),  esc_url(site_url()));
							get_footer();
							exit;
						}
					}
				}
				if (('paid' === $order_status) && (0 === $not_enough)) {
					// Do your magic here, and return 200 OK to Cointopay.
					$status = $order->get_status();

					if ( 'completed' === $status || 'processing' === $status ) {
					    // Do nothing if order is already completed or processing
					    //$new_status = $status;
					} else {
					    $order->payment_complete(); // This automatically sets status to processing
					    $new_status = $order->get_status();
						/* translators: 1: previous order status, 2: new order status, 3: order ID */
						$message = sprintf(__( 'IPN: Update event for Cointopay from status %1$s to %2$s: %3$s', 'cointopay-com-cc-only' ),
							$status,
							$new_status,
							$order_id
						);

						$order->add_order_note( $message );
					}

					get_header();
					printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#0fad00">' . esc_html__('Success!', 'cointopay-com-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('The payment has been received and confirmed successfully.', 'cointopay-com-cc-only') . '</p><a href="%s" style="background-color: #0fad00;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'cointopay-com-cc-only') . '</a><br><br><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/check.png'),  esc_url(site_url()));
					get_footer();
					exit;
				} elseif ('failed' === $order_status && 1 === $not_enough) {
					$order->update_status('on-hold', sprintf(esc_html__('IPN: Payment failed notification from Cointopay because not enough', 'cointopay-com-cc-only')));
					get_header();
					printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'cointopay-com-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('The payment has been failed.', 'cointopay-com-cc-only') . '</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'cointopay-com-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'),  esc_url(site_url()));
					get_footer();
					exit;
				} else {
					$order->update_status('failed', sprintf(esc_html__('IPN: Payment failed notification from Cointopay', 'cointopay-com-cc-only')));
					get_header();
					printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'cointopay-com-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('The payment has been failed.', 'cointopay-com-cc-only') . '</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'cointopay-com-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'),  esc_url(site_url()));
					get_footer();
					exit;
				}
			}
		}
	}

	/**
	 * Adds error message when not configured the api key.
	 */
	public function api_key_missing_message()
	{
		$message = '<div class="error">';
		$message .= '<p><strong>' . esc_html__('Gateway Disabled', 'cointopay-com-cc-only') . '</strong>' . esc_html__(' You should enter your API key in Cointopay configuration.', 'cointopay-com-cc-only') . ' <a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=cointopay">' . esc_html__('Click here to configure', 'cointopay-com-cc-only') . '</a></p>';
		$message .= '</div>';

		return $message;
	}

	/**
	 * Adds error message when not configured the secret.
	 */
	public function secret_missing_message()
	{
		$message = '<div class="error">';
		$message .= '<p><strong>' . esc_html__('Gateway Disabled', 'cointopay-com-cc-only') . '</strong>' . esc_html__(' You should check your SecurityCode in Cointopay configuration.', 'cointopay-com-cc-only') . ' <a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=cointopay">' . esc_html__('Click here to configure!', 'cointopay-com-cc-only') . '</a></p>';
		$message .= '</div>';

		return $message;
	}

	public function validate_order($data)
	{
		$params = array(
			'body'           => 'MerchantID=' . sanitize_text_field($data['mid']) . '&Call=Transactiondetail&APIKey=a&output=json&ConfirmCode=' . sanitize_text_field($data['ConfirmCode']),
			'authentication' => 1,
			'cache-control'  => 'no-cache',
		);

		$url = 'https://app.cointopay.com/v2REAPI?';

		$response = wp_safe_remote_post($url, $params);

		return json_decode($response['body'], true);
	}
	
	public function validate_merchantid_field( $key, $value ) {
		if ( empty( $value ) ) {
			WC_Admin_Settings::add_error( __( 'Merchant ID is required.', 'cointopay-com-cc-only' ) );
		}
		return $value;
	}

	public function validate_secret_field( $key, $value ) {
		if ( empty( $value ) ) {
			WC_Admin_Settings::add_error( __( 'Security Code is required.', 'cointopay-com-cc-only' ) );
		}
		return $value;
	}
	
}//end class
