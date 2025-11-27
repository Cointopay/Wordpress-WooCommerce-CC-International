<?php
/**
 * Define Cointopay CC Class
 *
 * @package  WooCommerce
 * @author   Cointopay <info@cointopay.com>
 * @link     cointopay.com
 */
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


		if (
			empty($this->settings['enabled']) === false
			&& empty($this->api_key) === false && empty($this->secret) === false
		) {
			$this->enabled = 'yes';
		} else {
			$this->enabled = 'no';
		}
		// Checking if api key is not empty.
		if (empty($this->api_key) === true) {
			add_action('admin_notices', array(&$this, 'api_key_missing_message'));
		}

		// Checking if app_secret is not empty.
		if (empty($this->secret) === true) {
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
		wp_localize_script('cointopay_cc_js', 'ajaxurlctpcc', array('ajaxurl' => admin_url('admin-ajax.php')));
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
				'title'   => esc_html__('Enable/Disable', 'wc-cointopay-cc-only'),
				'type'    => 'checkbox',
				'label'   => esc_html__('Enable Cointopay CC Only', 'wc-cointopay-cc-only'),
				'default' => 'yes',
			),
			'title'       => array(
				'title'       => esc_html__('Title', 'wc-cointopay-cc-only'),
				'type'        => 'text',
				'description' => esc_html__('This controls the title the user can see during checkout.', 'wc-cointopay-cc-only'),
				'default'     => esc_html__('Cointopay CC Only', 'wc-cointopay-cc-only'),
			),
			'description' => array(
				'title'       => esc_html__('Description', 'wc-cointopay-cc-only'),
				'type'        => 'textarea',
				'description' => esc_html__('This controls the title the user can see during checkout.', 'wc-cointopay-cc-only'),
				'default'     => esc_html__('You will be redirected to cointopay.com to complete your purchase.', 'wc-cointopay-cc-only'),
			),
			'merchant_id' => array(
				'title'       => esc_html__('Your MerchantID', 'wc-cointopay-cc-only'),
				'type'        => 'text',
				/* translators: %s: https://cointopay.com */
				'description' => sprintf(wp_kses(__('Please enter your Cointopay Merchant ID, You can get this information in: <a href="%s" target="_blank">Cointopay Account</a>.', 'wc-cointopay-cc-only'), array(  'a' => array( 'href' => array() ))), esc_url('https://cointopay.com')),
				'default'     => '',
			),
			'secret'      => array(
				'title'       => esc_html__('Security Code', 'wc-cointopay-cc-only'),
				'type'        => 'text',
				/* translators: %s: https://cointopay.com */
				'description' => sprintf(wp_kses(__('Please enter your Cointopay SecurityCode, You can get this information in: <a href="%s" target="_blank">Cointopay Account</a>.', 'wc-cointopay-cc-only'), array(  'a' => array( 'href' => array() ))), esc_url('https://cointopay.com')),
				'default'     => '',
			),
			'cointopay_cc_alt_coin' =>  array(
				'type'          => 'select',
				'class'         => array('cointopay_cc_alt_coin'),
				'title'         => esc_html__('Default Receive Currency', 'wc-cointopay-cc-only'),
				'options'       => array(
					'blank'		=> esc_html__('Select Alt Coin', 'wc-cointopay-cc-only'),
				)
			),
		);
	}

	public function admin_options()
	{ ?>
		<h3><?php esc_html_e('Cointopay CC Only Checkout', 'wc-cointopay-cc-only'); ?></h3>

		<div id="wc_get_started">
			<span class="main"><?php esc_html_e('Provides a secure way to accept crypto currencies.', 'wc-cointopay-cc-only'); ?></span>
			<p>
				<a href="<?php echo esc_url('https://app.cointopay.com/signup'); ?>" target="_blank" class="button button-primary">
					<?php esc_html_e('Join free', 'wc-cointopay-cc-only'); ?>
				</a>
				<a href="<?php echo esc_url('https://cointopay.com'); ?>" target="_blank" class="button">
					<?php esc_html_e('Learn more about WooCommerce and Cointopay', 'wc-cointopay-cc-only'); ?>
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
		if (true === $this->description) {
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
		$params   = array(
			'body' => 'email='.$customer_email.'&SecurityCode=' . $this->secret . '&MerchantID=' . $this->merchant_id . '&Amount=' . number_format($order->get_total(), 8, '.', '') . '&AltCoinID=' . $this->alt_coin_id . '&output=json&inputCurrency=' . get_woocommerce_currency() . '&CustomerReferenceNr=' . $order_id . '-' . $order->get_order_number() . '&returnurl=' . rawurlencode(esc_url($this->get_return_url($order))) . '&transactionconfirmurl=' . site_url('/?wc-api=WC_CointopayCC_Gateway') . '&transactionfailurl=' . rawurlencode(esc_url($order->get_cancel_order_url())),
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
		if(isset($_GET['wc-api']) && isset($_GET['CustomerReferenceNr']) && isset($_GET['TransactionID']))
		{
			$ctp_cc = sanitize_text_field($_REQUEST['wc-api']);
			if ($ctp_cc == 'WC_CointopayCC_Gateway') {
				global $woocommerce;
				$woocommerce->cart->empty_cart();
				$order_id                = (isset($_REQUEST['CustomerReferenceNr'])) ? $this->extractOrderId($_REQUEST['CustomerReferenceNr']) : 0;
				$order_status            = (isset($_REQUEST['status'])) ? sanitize_text_field($_REQUEST['status']) : '';
				$order_transaction_id    = (isset($_REQUEST['TransactionID'])) ? sanitize_text_field($_REQUEST['TransactionID']) : '';
				$order_confirm_code      = (isset($_REQUEST['ConfirmCode'])) ? sanitize_text_field($_REQUEST['ConfirmCode']) : '';
				$stripe_transaction_code = (isset($_REQUEST['stripe_transaction_id'])) ? sanitize_text_field($_REQUEST['stripe_transaction_id']) : '';
				$not_enough              = (isset($_REQUEST['notenough'])) ? intval($_REQUEST['notenough']) : 1;
				$is_live                 = (isset($_REQUEST['is_live'])) ? (string) sanitize_text_field($_REQUEST['is_live']) : 'true';
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
						printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'wc-cointopay-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">%s</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'wc-cointopay-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'), esc_html($transactionData['message']), esc_url(site_url()));
						get_footer();
						exit;
					} else {
						$transaction_order_id = $this->extractOrderId($transactionData['data']['CustomerReferenceNr']);

						if ($transactionData['data']['Security'] != $order_confirm_code) {
							get_header();
							printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'wc-cointopay-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('Data mismatch! ConfirmCode doesn\'t match', 'wc-cointopay-cc-only') . '</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'wc-cointopay-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'), esc_url(site_url()));
							get_footer();
							exit;
						} elseif ($transaction_order_id != $order_id) {
							get_header();
							printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'wc-cointopay-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('Data mismatch! CustomerReferenceNr doesn\'t match', 'wc-cointopay-cc-only') . '</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'wc-cointopay-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'), esc_url(site_url()));
							get_footer();
							exit;
						} elseif ($transactionData['data']['TransactionID'] != $order_transaction_id) {
							get_header();
							printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'wc-cointopay-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('Data mismatch! TransactionID doesn\'t match', 'wc-cointopay-cc-only') . '</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'wc-cointopay-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'),  esc_url(site_url()));
							get_footer();
							exit;
						} elseif ($transactionData['data']['Status'] != $order_status) {
							get_header();
							printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'wc-cointopay-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('Data mismatch! status doesn\'t match. Your order status is', 'wc-cointopay-cc-only') . ' %s</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'wc-cointopay-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'), esc_html($transactionData['data']['Status']), esc_url(site_url()));
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
							$order->update_status('on-hold', sprintf(esc_html__('IPN: Payment failed notification from Cointopay because not enough', 'woocommerce')));
							get_header();
							printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'wc-cointopay-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('The payment has been failed.', 'wc-cointopay-cc-only') . '</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'wc-cointopay-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'),  esc_url(site_url()));
							get_footer();
							exit;
						} else {
							$order->update_status('failed', sprintf(esc_html__('IPN: Payment failed notification from Cointopay', 'woocommerce')));
							get_header();
							printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'wc-cointopay-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('The payment has been failed.', 'wc-cointopay-cc-only') . '</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'wc-cointopay-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'),  esc_url(site_url()));
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
						// Add order note
						$order->add_order_note( __( 'IPN: Update event for Cointopay from status '.$status.' to '.$new_status.':', 'woocommerce' ) . ' ' . $order_id );
					}

					get_header();
					printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#0fad00">' . esc_html__('Success!', 'wc-cointopay-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('The payment has been received and confirmed successfully.', 'wc-cointopay-cc-only') . '</p><a href="%s" style="background-color: #0fad00;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'wc-cointopay-cc-only') . '</a><br><br><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/check.png'),  esc_url(site_url()));
					get_footer();
					exit;
				} elseif ('failed' === $order_status && 1 === $not_enough) {
					$order->update_status('on-hold', sprintf(esc_html__('IPN: Payment failed notification from Cointopay because not enough', 'woocommerce')));
					get_header();
					printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'wc-cointopay-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('The payment has been failed.', 'wc-cointopay-cc-only') . '</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'wc-cointopay-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'),  esc_url(site_url()));
					get_footer();
					exit;
				} else {
					$order->update_status('failed', sprintf(esc_html__('IPN: Payment failed notification from Cointopay', 'woocommerce')));
					get_header();
					printf('<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">' . esc_html__('Failure!', 'wc-cointopay-cc-only') . '</h2><img style="width: 100px; margin: 0 auto 20px;"  src="%s"><p style="font-size:20px;color:#5C5C5C;">' . esc_html__('The payment has been failed.', 'wc-cointopay-cc-only') . '</p><a href="%s" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >' . esc_html__('Back', 'wc-cointopay-cc-only') . '</a><br><br></div></div></div>', esc_url(WC_Cointopay_CC_Payments::plugin_url() . '/assets/images/fail.png'),  esc_url(site_url()));
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
		$message .= '<p><strong>' . esc_html__('Gateway Disabled', 'wc-cointopay-cc-only') . '</strong>' . esc_html__(' You should enter your API key in Cointopay configuration.', 'wc-cointopay-cc-only') . ' <a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=cointopay">' . esc_html__('Click here to configure', 'wc-cointopay-cc-only') . '</a></p>';
		$message .= '</div>';

		return $message;
	}

	/**
	 * Adds error message when not configured the secret.
	 */
	public function secret_missing_message()
	{
		$message = '<div class="error">';
		$message .= '<p><strong>' . esc_html__('Gateway Disabled', 'wc-cointopay-cc-only') . '</strong>' . esc_html__(' You should check your SecurityCode in Cointopay configuration.', 'wc-cointopay-cc-only') . ' <a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=cointopay">' . esc_html__('Click here to configure!', 'wc-cointopay-cc-only') . '</a></p>';
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
}//end class
