<?php
add_action( 'wp_ajax_nopriv_getCTPCCMerchantCoins', 'cointopay_cc_getCTPCCMerchantCoins' );
add_action( 'wp_ajax_getCTPCCMerchantCoins', 'cointopay_cc_getCTPCCMerchantCoins' );
function cointopay_cc_getCTPCCMerchantCoins()
{
	$merchantId = 0;
	$merchantId = intval($_REQUEST['merchant']);
	if (isset($merchantId) && $merchantId !== 0) {
		$option = '';
		$arr = cointopay_cc_getCTPCCCoins($merchantId);
		foreach ($arr as $key => $value) {
			$ctpbank = new WC_CointopayCC_Gateway;
			$ctpbselect = ($key == $ctpbank->alt_coin_id) ? 'selected="selected"' : '';
			$option .= '<option value="' . intval($key) . '" ' . $ctpbselect . '>' . esc_html($value) . '</option>';
		}
		echo esc_html($option);
		exit();
	}
}

function cointopay_cc_getCTPCCCoins($merchantId)
{
	$params = array(
		'body' => 'MerchantID=' . sanitize_text_field($merchantId) . '&output=json',
	);
	$url = 'https://cointopay.com/CloneMasterTransaction';
	$response  = wp_safe_remote_post($url, $params);
	if ((false === is_wp_error($response)) && (200 === $response['response']['code']) && ('OK' === $response['response']['message'])) {
		$php_arr = json_decode($response['body']);
		$new_php_arr = array();

		if (!empty($php_arr)) {
			for ($i = 0; $i < count($php_arr) - 1; $i++) {
				if (($i % 2) == 0) {
					$new_php_arr[$php_arr[$i + 1]] = $php_arr[$i];
				}
			}
		}

		return $new_php_arr;
	}
}