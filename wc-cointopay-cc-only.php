<?php
/**
 * Plugin Name: Cointopay.com CC Only
 * Description: Extends WooCommerce with card payments gateway.
 * Version: 1.3.6
 * Author: Cointopay
 * Text Domain: wc-cointopay-cc-only
 * @package  WooCommerce
 * @author   Cointopay <info@cointopay.com>
 * @link     cointopay.com
 * @disclaimer This plugin is using a https://cointopay.com backend integration, the Coinplusgroup S.R.O. Terms and conditions incl. privacy policy are applicable, please read the following information carefully: Terms: https://cointopay.com/terms and privacy policy: https://cdn-eur.s3.eu-west-1.amazonaws.com/Coinplusgroup-sro-Privacy-Policy.pdf. Any questions, please send to support@cointopay.com.
 * License: GPL v3.0
 */

defined('ABSPATH') || exit;

require_once plugin_dir_path( __FILE__ ) . 'hooks/get_merchant_coins.php';

add_filter( 'woocommerce_payment_gateways', 'wc_cointopay_cc_gateway_class' );
function wc_cointopay_cc_gateway_class( $gateways ) {
	$gateways[] = 'WC_CointopayCC_Gateway'; // your class name is here
	return $gateways;
}

add_action( 'plugins_loaded', 'woocommerce_cointopay_cc_init' );

function woocommerce_cointopay_cc_init() {
	require_once plugin_dir_path( __FILE__ ) . 'classes/wc_cointopay_cc_gateway.php';
}

class WC_Cointopay_CC_Payments {

    /**
     * Plugin bootstrapping.
     */
    public static function init() {
        // Registers WooCommerce Blocks integration.
        add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'woocommerce_gateway_cointopay_cc_woocommerce_block_support' ) );

    }

    /**
     * Plugin url.
     *
     * @return string
     */
    public static function plugin_url() {
        return untrailingslashit( plugins_url( '/', __FILE__ ) );
    }

    /**
     * Plugin url.
     *
     * @return string
     */
    public static function plugin_abspath() {
        return trailingslashit( plugin_dir_path( __FILE__ ) );
    }

    /**
     * Registers WooCommerce Blocks integration.
     *
     */
    public static function woocommerce_gateway_cointopay_cc_woocommerce_block_support() {
        if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            require_once 'classes/wc_cointopay_cc_block_support.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                    $payment_method_registry->register( new WC_Cointopay_CC_Block_support() );
                }
            );
        }
    }
}

WC_Cointopay_CC_Payments::init();
?>
