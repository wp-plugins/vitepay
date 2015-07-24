<?php
/*
Plugin Name: Vitepay - WooCommerce Gateway
Plugin URI: http://www.vitepay.com/
Description: Extends WooCommerce by Adding the Vitepay Gateway.
Version: 1
Author: Logineo
Author URI: http://www.logineo.com/
*/

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'vitepay_aim_init', 0 );
function vitepay_aim_init() {
    // If the parent WC_Payment_Gateway class doesn't exist
    // it means WooCommerce is not installed on the site
    // so do nothing
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    // If we made it this far, then include our Gateway Class
    include_once( 'woocommerce-vitepay.php' );

    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'add_vitepay_aim_gateway' );
    function add_vitepay_aim_gateway( $methods ) {
        $methods[] = 'Vitepay_AIM';
        return $methods;
    }
}

// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'vitepay_aim_action_links' );
function vitepay_aim_action_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'vitepay-aim' ) . '</a>',
    );

    // Merge our new link with the default ones
    return array_merge( $plugin_links, $links );
}

