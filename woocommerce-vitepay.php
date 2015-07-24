<?php
class Vitepay_AIM extends WC_Payment_Gateway
{
// Setup our Gateway's id, description and other values
    function __construct()
    {

        // The global ID for this Payment method
        $this->id = "vitepay_aim";

        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = __("vitepay", 'vitepay-aim');

        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __("Vitepay Payment Gateway Plug-in for WooCommerce", 'vitepay-aim');

        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = __("vitepay", 'vitepay-aim');

        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = null;

        // Bool. Can be set to true if you want payment fields to show on the checkout
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = true;

        // Supports the default credit card form
        //$this->supports = array( 'default_credit_card_form' );

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();

        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option( 'title' );
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        // Lets check for SSL
        //add_action( 'admin_notices', array( $this,  'do_ssl_check' ) );

        // Save settings
        if (is_admin()) {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }


        add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( &$this, 'handle_vitepay_callback' )  );

    } // End __construct()
    // Build the administration fields for this specific Gateway
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'     => __( 'Enable / Disable', 'vitepay-aim' ),
                'label'     => __( 'Enable this payment gateway', 'vitepay-aim' ),
                'type'      => 'checkbox',
                'default'   => 'no',
            ),
            'title' => array(
                'title'     => __( 'Title', 'vitepay-aim' ),
                'type'      => 'text',
                'desc_tip'  => __( 'Payment title the customer will see during the checkout process.', 'vitepay-aim' ),
                'default'   => __( 'Credit card', 'vitepay-aim' ),
            ),
            'description' => array(
                'title'     => __( 'Description', 'vitepay-aim' ),
                'type'      => 'textarea',
                'desc_tip'  => __( 'Payment description the customer will see during the checkout process.', 'vitepay-aim' ),
                'default'   => __( 'Pay securely using Orange Money.', 'vitepay-aim' ),
                'css'       => 'max-width:350px;'
            ),
            'api_key' => array(
                'title'     => __( 'Vitepay API key', 'vitepay-aim' ),
                'type'      => 'text',
                'desc_tip'  => __( 'This is the API Key provided by vitepay when you signed up for an account.', 'vitepay-aim' ),
            ),
            'api_secret' => array(
                'title'     => __( 'Vitepay API secret', 'vitepay-aim' ),
                'type'      => 'password',
                'desc_tip'  => __( 'This is the Secret Key provided by vitepay when you signed up for an account.', 'vitepay-aim' ),
            ),
            'api_signature' => array(
                'title'     => __( 'Vitepay API Signature', 'vitepay-aim' ),
                'type'      => 'text',
                'desc_tip'  => __( 'This is the API Signature provided by vitepay when you signed up for an account.', 'vitepay-aim' ),
            ),
            'api_cart_ID' => array(
                'title'     => __( 'Woo cart page ID', 'vitepay-aim' ),
                'type'      => 'text',
                'desc_tip'  => __( 'This is the page ID for the cart of the woocommerce plugin.', 'vitepay-aim' ),
            ),
            'environment' => array(
                'title'     => __( 'vitepay Test Mode', 'vitepay-aim' ),
                'label'     => __( 'Enable Test Mode', 'vitepay-aim' ),
                'type'      => 'checkbox',
                'description' => __( 'Place the payment gateway in test mode.', 'vitepay-aim' ),
                'default'   => 'no',
            ),
            'default_locale' => array(
                'title'     => __( 'Default Locale', 'vitepay-aim' ),
                'type'      => 'text',
                'desc_tip'  => __( 'Your website default locale.', 'vitepay-aim' ),
            ),
            'default_currency' => array(
                'title'     => __( 'Default Currency', 'vitepay-aim' ),
                'type'      => 'text',
                'desc_tip'  => __( 'Your website default currency', 'vitepay-aim' ),
            ),
            'default_country' => array(
                'title'     => __( 'Country', 'vitepay-aim' ),
                'type'      => 'text',
                'desc_tip'  => __( 'Your website default country', 'vitepay-aim' ),
            )
        );
    }
    public function process_payment( $order_id ) {
        global $woocommerce;

        // Get this Order's information so that we know
        // who to charge and how much
        $customer_order = new WC_Order( $order_id );

        // Are we testing right now or is it a real transaction
        $environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';

        // Decide which URL to post to
        $environment_url = ( "FALSE" == $environment )
            ? 'https://api.vitepay.com/v1/prod/payments'
            : 'https://api.vitepay.com/v1/sandbox/payments';

        #$environment_url = "http://requestb.in/1e39vyc1";


        $order_id = str_replace( "#", "", $customer_order->get_order_number() );
        $order_id = str_replace( "n°", "", $order_id );

        $original_url = "http" . (($_SERVER['SERVER_PORT'] == 443) ? "s://" : "://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $amout_100 = $customer_order->order_total * 100;
        $currency_code = $this->default_currency;
        $api_secret = $this->api_secret;
        $callback_url = get_site_url() . '?wc-api=Vitepay_AIM';
        $upped = strtoupper("$order_id;$amout_100;$currency_code;$callback_url;$api_secret");
        $hash = SHA1($upped);

        // This is where the fun stuff begins
        $payload = array(
            // Authorize.net Credentials and API Info
            "api_key"               => $this->api_key,
            "hash"                  => $hash,
            "api_version"           => "1",

            // Order total
            //
            #"test" => $order_id."/".$amout_100."/".$currency_code."/".$callback_url."/".$api_secret,
            "payment[language_code]"=> $this->default_locale, # fr
            "payment[currency_code]"=> $this->default_currency, # XOF
            "payment[country_code]" => $this->default_country, # ML
            "payment[order_id]"     => $order_id,
            "payment[description]"  => 'ACHAT DE XXX SUR LOGITEST',
            "payment[amount_100]"   => $customer_order->order_total * 100,
            "payment[buyer_ip_adress]"=> $_SERVER['REMOTE_ADDR'],
            "payment[return_url]"   => $this->get_return_url( $customer_order ), # URL called if process was OK
            "payment[decline_url]"  => $this->get_return_url( $customer_order ), # URL called when payment's failed
            "payment[cancel_url]"   => (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]?page_id=" . $this->api_cart_ID,//(isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]", # Obvious... User hit cancel!
            "payment[callback_url]" => get_site_url() . '?wc-api=Vitepay_AIM', # URL for server-2-server call
            "payment[email]"        => $customer_order->billing_email,
            "payment[p_type]"       => 'orange_money'
        );

        // Send this payload to Authorize.net for processing
        $response = wp_remote_post( $environment_url, array(
            'method'    => 'POST',
            'body'      => http_build_query( $payload ),
            'timeout'   => 90,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) )
            throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'vitepay-aim' ) );

        if ( empty( $response['body'] ) )
            throw new Exception( __( 'vitepay\'s Response was empty.', 'vitepay-aim' ) );

        // Retrieve the body's resopnse if no errors found
        $response_body = wp_remote_retrieve_body( $response );

        // Redirect to thank you page
        return array(
            'result'   => 'success',
            'redirect' => $response_body,
        );
    }

    function handle_vitepay_callback() {
        @ob_clean();
        global $woocommerce;
        if ( isset( $_REQUEST['order_id'] ) && isset( $_REQUEST['authenticity'] ) ) {
            $order_id = $_REQUEST['order_id'];
            if ($order_id != '') {
                $order = new WC_Order($order_id);

                $our_authenticity = sprintf('%s;%s;%s;%s', $order_id, $order->order_total * 100, 'XOF', $this->api_secret);
                # bPGRHvbH6qzjL2kGyPALEHJyAlrupoi0h2X4uaPxO6L8MQUCQfVXq406ukGCat/J1NPUvuYxSTDYVcwr
                $our_authenticity = strtoupper(sha1($our_authenticity));

                if ($our_authenticity == $_REQUEST['authenticity']) {
                    if ($_REQUEST['success']=='1') {
                        $order->payment_complete();
                        $order->add_order_note( __( 'VitePay payment completed.', 'vitepay-aim' ) );
                        $woocommerce->cart->empty_cart();
                        echo json_encode(array("status"=>1, "message" => "OK"));
                    }else if ($_REQUEST['failure']=='1') {
                        wc_add_notice( 'VitePay payment has failed', 'error' );
                        // Add note to the order for your reference
                        $order->update_status('failed',  __( 'VitePay payment failed.', 'vitepay-aim' ));
                        $order->add_order_note( 'Error: '. 'VitePay payment has failed.' );
                        echo json_encode(array("status"=>1, "message" => "KO"));
                    }else {
                        echo json_encode(array("status"=>0, "error"=>"unknown status"));
                    }

                }else {
                    echo json_encode(array(
                        "status"=>0,
                        "our_authenticity"=>$our_authenticity,
                        "error" => "bad_authenticity"
                    ));
                }
                exit();
            }
        }

        echo json_encode(array("status"=>0));
        exit();
    }

}
