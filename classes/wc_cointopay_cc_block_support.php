<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Cointopay_CC_Block_support extends AbstractPaymentMethodType
{

    private $gateway;

    protected $name = 'cointopay_cc';

    public function initialize()
    {
        $this->settings = get_option( 'woocommerce_cointopay_cc_settings', [] );
        $this->gateway  = new WC_CointopayCC_Gateway();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {
        $script_path       = '/assets/js/frontend/blocks.js';
        $script_asset_path = WC_Cointopay_CC_Payments::plugin_abspath() . 'assets/js/frontend/blocks.asset.php';
        $script_asset      = file_exists( $script_asset_path )
            ? require( $script_asset_path )
            : array(
                'dependencies' => array(),
                'version'      => '1.3.1'
            );
        $script_url        = WC_Cointopay_CC_Payments::plugin_url() . $script_path;
        wp_register_script(
            'wc-cointopay-cc-payments-blocks',
            $script_url,
            $script_asset[ 'dependencies' ],
            $script_asset[ 'version' ],
            true
        );
        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'wc-cointopay-cc-payments-blocks', 'woocommerce-gateway-cointopay-cc', WC_Cointopay_CC_Payments::plugin_abspath() . 'languages/' );
        }
        return [ 'wc-cointopay-cc-payments-blocks' ];
    }

    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting( 'title' ),
            'description' => $this->get_setting( 'description' ),
            'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] )
        ];
    }
}