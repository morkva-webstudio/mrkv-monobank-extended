<?php
if ( ! defined( 'ABSPATH' ) ) exit;
# Include namespaces
use MorkvaMonoGateway\Morkva_Mono_Order;
use MorkvaMonoGateway\Morkva_Mono_Payment;

/**
 * Class WC_Gateway_Morkva_Mono file
 * */
class WC_Gateway_Morkva_Mono extends WC_Payment_Gateway
{
    /**
     * @var string Token connect with monopay
     * */
    private $mrkv_mono_token;

    /**
     * Constructor for the gateway
     * */
    public function __construct()
    {
        # Load all classes monopay connection
        mrkv_mono_loadMonoLibrary();

        # Get settings        
        $this->id = 'morkva-monopay';
        $this->icon = $this->get_admin_icon_url();
        $this->has_fields = true;
        $this->method_title = __('morkva Plata by Mono Extended', 'morkva-monobank-extended');
        $this->method_description = __('Accept credit card payments on your website via morkva Monobank payment gateway.', 'morkva-monobank-extended');
        $this->supports = array('refunds', 'subscriptions', 'products', 'subscription_cancellation', 
               'subscription_suspension', 
               'subscription_reactivation',
               'subscription_amount_changes',
               'subscription_date_changes',
               'subscription_payment_method_change',
               'subscription_payment_method_change_customer',
               'subscription_payment_method_change_admin',
               'multiple_subscriptions');

        # Load the settings
        $this->init_form_fields();
        $this->init_settings();

        # Get settings
        $this->title = $this->get_option('title');
        $this->description  = $this->get_option( 'description' );
        $this->mrkv_mono_token = $this->get_option('API_KEY');

        # Include functions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_morkva-monopay', array($this, 'mrkv_mono_callback_success'));
        
        # Callback function
        add_action('woocommerce_thankyou_'.$this->id, array( $this, 'return_handler' ) );

        # Add payment image
        add_filter( 'woocommerce_gateway_icon', array( $this, 'morkva_monopay_gateway_icon' ), 100, 2 );
        add_filter('woocommerce_available_payment_gateways', array($this, 'mrkv_filter_gateway_by_admin_test_mode'), 10, 1);

        $section = filter_input( INPUT_GET, 'section', FILTER_DEFAULT );
        $section = $section ? (string) $section : '';

        if ( 'morkva-monopay' === $section ) {
            # Include styles
            add_action('admin_head', array($this, 'mrkv_mono_style_settings'));
            # Include scripts
            add_action('admin_enqueue_scripts', array($this, 'mrkv_mono_scripts_settings'));
        }
    }

    /**
     * Initialise Gateway Settings Form Fields
     * 
     */
    public function init_form_fields() 
    {
        $all_order_statuses = wc_get_order_statuses();
        $correct_order_statuses = array();

        foreach($all_order_statuses as $k => $v)
        {
            $k = str_replace('wc-', '', $k);
            $correct_order_statuses[$k] = $v;
        }
        $shipping_methods = WC()->shipping()->get_shipping_methods();
        $mono_shipping_methods['none'] = __( 'None', 'morkva-monobank-extended' );

        foreach($shipping_methods as $key => $method)
        {
            $mono_shipping_methods[$key] = $method->get_method_title();
        }

        # Create fields gateway
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'morkva-monobank-extended' ), 
                'type' => 'checkbox',
                'label' => '<span>' . __( 'Enable morkva Mono Payment', 'morkva-monobank-extended' )  . '</span>',
                'default' => 'no'
            ),
            'title' => array(
                'title' => __( 'Title', 'morkva-monobank-extended' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'morkva-monobank-extended' ),
                'default' => '',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __( 'Description', 'morkva-monobank-extended' ),
                'type' => 'textarea',
                'desc_tip' => true,
                'description' => __( 'This controls the description which the user sees during checkout.', 'morkva-monobank-extended' ),
            ),
            'payment_destination_template' => array(
                'title'       => __( 'Payment destination', 'morkva-monobank-extended' ),
                'type'        => 'text',
                'description' => __( 'Template for the "description" field sent to LiqPay (shown to customer as payment purpose). Available shortcodes: {order_id}, {billing_first_name}, {billing_last_name}. Leave empty to keep default behavior.', 'morkva-monobank-extended' ) . '<br>' . __( 'Shortcodes:', 'morkva-monobank-extended' ) . '<br>' . __( '{billing_first_name} - The buyer\'s name as listed in the order\'s payment details,', 'morkva-monobank-extended' ) . '<br>' . __( '{billing_last_name} - the buyer\'s last name as listed in the order\'s billing information,', 'morkva-monobank-extended' ) . '<br>' . __( '{order_id} - Order ID.', 'morkva-monobank-extended' ),
                'placeholder' => __( 'Оплата за замовлення №{order_id} від {billing_last_name} {billing_first_name}', 'morkva-monobank-extended' ),
                'default'     => '',
                'desc_tip'    => false,
            ),
            'API_KEY' => array(
                'title' => __( 'Api token', 'morkva-monobank-extended' ),
                'type' => 'text',
                'description' => __( 'You can find out your X-Token by the link: <a href="https://web.monobank.ua/" target="blank">web.monobank.ua</a>', 'morkva-monobank-extended' ) . __( '<br>After receiving the API token and activating your merchant, write to Monobank\'s support chat<br> to activate a redirect to the site\'s thank you page (the plugin transmits the page URL via API).<br> Tell support that you are using the morkva plugin.', 'morkva-monobank-extended' ),
                'default' => '',
            ),
            'monopay_order_status' => array(
                'title' => __( 'Status of completed payment', 'morkva-monobank-extended' ),
                'type' => 'select',
                'description' => __( 'Select the status to which the order status will change after successful payment', 'morkva-monobank-extended' ),
                'label' => __( 'Courier', 'morkva-monobank-extended' ),
                'options' => $correct_order_statuses,
                'default' => 'processing',
            ),
            'use_holds' => array(
                'title' => __('Holds', 'morkva-monobank-extended'),
                'label' => '<span>' . __( 'Enable', 'morkva-monobank-extended' )  . '</span>',
                'type' => 'checkbox',
                'default' => 'false',
                'description' => __( 'The payment is held for 9 days. After this period, the payment is automatically finalized. You can finalize it manually from the order page, or it will be finalized automatically when the status changes.', 'morkva-monobank-extended' ),
            ),
            'hold_finale_status' => array(
                'title' => __( 'Automatic finalization of holding when the order status changes', 'morkva-monobank-extended' ),
                'type' => 'select',
                'description' => '<br>',
                'options' => $correct_order_statuses,
                'default' => 'completed',
            ),
            'hold_cancel_status' => array(
                'title' => __( 'Automatic cancellation of holding when the order status changes', 'morkva-monobank-extended' ),
                'type' => 'select',
                'description' => '<br>',
                'options' => $correct_order_statuses,
                'default' => 'cancelled',
            ),
            'title_method_image' => array(
                'title' => __( 'Image Settings', 'morkva-monobank-extended' ),
                'type' => 'title',
                'description' => __( 'Configure the display of the payment method logo on the checkout page', 'morkva-monobank-extended' ),
            ),
            'monopay_image_type_black' => array(
                'title' => __( 'Image style', 'morkva-monobank-extended' ),
                'type' => 'checkbox',
                'label' => '<span></span><p style="padding: 20px;"><img src="' . MORKVAMONOGATEWAY_PATH . 'assets/images/plata_light_bg.png"></p>',
                'default' => 'yes'
            ),
            'monopay_image_type_white' => array(
                'title' => '',
                'type' => 'checkbox',
                'label' => '<span></span><p style="background: #676767; padding: 20px; border-radius: 10px;"><img src="' . MORKVAMONOGATEWAY_PATH . 'assets/images/plata_dark_bg.png" ></p>',
                'default' => 'no'
            ),
            'monopay_image_width' => array(
                'title' => __( 'Image width(px)', 'morkva-monobank-extended' ),
                'type' => 'number',
                'label' => '',
                'default' => ''
            ),
            'hide_image' => array(
                'title' => __( 'Hide logo', 'morkva-monobank-extended' ),
                'type' => 'checkbox',
                'label' => '',
                'default' => 'no' 
            ),
            'url_monobank_img' => array(
                'title'       => __( 'Custom logo url', 'morkva-monobank-extended' ),
                'type'        => 'text',
                'desc_tip'    => false,
                'description' => __( 'Enter full url to image', 'morkva-monobank-extended' ),
                'default'     => '',
            ),
            'title_test_mode' => array(
                'title' => __( 'Test mode Settings', 'morkva-monobank-extended' ),
                'type' => 'title',
                'description' => __( 'To test the method\'s functionality, use test mode', 'morkva-monobank-extended' ),
            ),
            'enabled_test_mode' => array(
                'title' => __( 'Test mode', 'morkva-monobank-extended' ),
                'type' => 'checkbox',
                'label' => '<span>' . __( 'Enable Test mode', 'morkva-monobank-extended' ) . '</span>',
                'default' => 'no'
            ),
            'enabled_test_mode_admin' => array(
                'title' => __( 'Test mode Admin', 'morkva-monobank-extended' ),
                'type' => 'checkbox',
                'label' => '<span>' . __( 'Enable Test Admin mode', 'morkva-monobank-extended' ) . '</span>',
                'default' => 'no'
            ),
            'TEST_API_KEY' => array(
                'title' => __( 'Test Api token', 'morkva-monobank-extended' ),
                'type' => 'text',
                'description' => __( 'You can find out your Test Token by the link: <a href="https://api.monobank.ua/" target="blank">api.monobank.ua</a>', 'morkva-monobank-extended' ),
                'default' => '',
            ),
            'mono_national_cashback' => array(
                'title' => __( 'National cashback', 'morkva-monobank-extended' ),
                'type' => 'title',
                'description' => __( 'Request Merchant ID and Terminal ID from Mono support', 'morkva-monobank-extended' ),
            ),
            'national_cashback_merchant_id' => array(
                'title'       => __( 'Merchant ID', 'morkva-monobank-extended' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __( 'Enter Merchant ID', 'morkva-monobank-extended' ),
                'default'     => '',
            ),
            'national_cashback_terminal_id' => array(
                'title'       => __( 'Terminal ID', 'morkva-monobank-extended' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __( 'Enter Terminal ID', 'morkva-monobank-extended' ),
                'default'     => '',
            )
        );
    }

    public function admin_options() {
        $back_link = admin_url( 'admin.php?page=wc-settings&tab=checkout' );
        ?>
        <div class="morkva-settings-wrapper" style="display: flex; justify-content: space-between; align-items: flex-start; margin-top: 20px;">
            
            <div class="morkva-settings-main" style="flex: 3;">
                <h2 class="wc-admin-header">
                    <small>
                        <a href="<?php echo esc_url( $back_link ); ?>" aria-label="<?php esc_attr_e( 'Return to payments', 'morkva-monobank-extended' ); ?>">
                            <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                        </a>
                    </small>
                    <?php echo esc_html( $this->method_title ); ?>
                </h2>

                <?php echo wp_kses_post( wpautop( $this->method_description ) ); ?>
                
                <table class="form-table">
                    <?php $this->generate_settings_html(); ?>
                </table>
            </div>

            <?php do_action('mrkv_mono_plata_settings_sidebar'); ?>
        </div>
        <?php
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID
     * @return array Result query
     */
    public function process_payment( $order_id ) 
    {
        $mrkv_mono_token = $this->mrkv_mono_getToken();
        $order = wc_get_order( $order_id );
        $basket_info = [];
        $global_discounts = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $product_id = $item->get_variation_id() ?: $item->get_product_id();
            $unit_sum = round(($item->get_subtotal() / $item->get_quantity()) * 100);
            
            $item_data = [
                "name"    => mb_substr(wp_strip_all_tags($item->get_name()), 0, 250),
                "qty"     => (int) $item->get_quantity(),
                "sum"     => $unit_sum,
                "total"   => round($item->get_subtotal() * 100),
                "code"    => $product->get_meta('mrkv_mono_product_code') ?: (string)$product_id,
                "barcode" => get_post_meta($product_id, '_barcode', true) ?: '',
                "unit"    => "шт.",
                "discounts" => []
            ];

            $image_id = $product->get_image_id();
            if ($image_id) {
                $item_data["icon"] = wp_get_attachment_image_url($image_id, 'full');
            }

            $line_discount = $item->get_subtotal() - $item->get_total();
            if ($line_discount > 0) {
                $item_data["discounts"] = [[
                    "type"  => "DISCOUNT",
                    "mode"  => "VALUE",
                    "value" => round($line_discount * 100)
                ]];
            }
            elseif($line_discount < 0)
            {
                $item_data["discounts"] = [[
                    "type"  => "EXTRA_CHARGE",
                    "mode"  => "VALUE",
                    "value" => round(abs($line_discount) * 100)
                ]];
            }

            $basket_info[] = $item_data;
        }

        if ( $this->should_include_shipping_to_monobank( $order ) || $order->get_shipping_total() > 0 ) {
            $shipping_total = round($order->get_shipping_total() * 100);
            $basket_info[] = [
                "name"    => mb_substr(wp_strip_all_tags($order->get_shipping_method()), 0, 250) ?: 'Доставка',
                "qty"     => 1,
                "sum"     => $shipping_total,
                "total"   => $shipping_total,
                "code"    => "shipping",
                "barcode" => "",
                "unit"    => "посл.",
                "discounts" => []
            ];
        }

        foreach ($order->get_fees() as $fee) {
            if ($fee->get_total() == 0) continue;
            $fee_total = round($fee->get_total() * 100);
            $basket_info[] = [
                "name"    => mb_substr(wp_strip_all_tags($fee->get_name()), 0, 250),
                "qty"     => 1,
                "sum"     => $fee_total,
                "total"   => $fee_total,
                "code"    => "fee",
                "barcode" => "",
                "unit"    => "посл.",
                "discounts" => []
            ];
        }

        $mrkvmonoOrder = new Morkva_Mono_Order();
        $mrkvmonoOrder->mrkv_mono_setCurrency($order->get_currency());
        $mrkvmonoOrder->mrkv_mono_setId($order->get_id());
        $mrkvmonoOrder->mrkv_mono_setReference($order->get_id());
        $mrkvmonoOrder->mrkv_mono_setAmount(round($order->get_total() * 100));
        $mrkvmonoOrder->mrkv_mono_setBasketOrder($basket_info);

        if (!empty($global_discounts)) {
            $mrkvmonoOrder->mrkv_mono_setDiscounts($global_discounts);
        }

        $destination = $this->mrkv_mono_render_destination($order);
        if ($destination) {
            $mrkvmonoOrder->mrkv_mono_setDestination($destination);
        }

        $is_hold = wc_string_to_bool($this->get_option('use_holds'));
        $mrkvmonoOrder->mrkv_mono_setHolds($is_hold);
        $order->update_meta_data('mrkv_mopay_accuiring_use_holds', $mrkvmonoOrder->mrkv_mono_hold_payment());

        $web_url = get_site_url();
        $mrkvmonoOrder->mrkv_mono_setRedirectUrl($this->get_return_url($order));
        $mrkvmonoOrder->mrkv_mono_setWebHookUrl($web_url . '/?wc-api=morkva-monopay');
    
        $mrkv_mono_payment = new Morkva_Mono_Payment($mrkv_mono_token);
        $mrkv_mono_payment->mrkv_mono_setOrder($mrkvmonoOrder);

        try {
            $mrkv_mono_invoice = $mrkv_mono_payment->mrkv_mono_create(true);
            if (!empty($mrkv_mono_invoice->pageUrl)) {
                if ($order->get_status() !== 'pending') {
                    $note = sprintf(__('Status changed to: %s', 'morkva-monobank-extended'), wc_get_order_status_name('pending'));
                    $order->update_status('pending', '[morkva] ' . $note, true);
                }
                $order->save();

                return [
                    'result'   => 'success',
                    'redirect' => $mrkv_mono_invoice->pageUrl,
                ];
            } else {
                throw new \Exception("Empty response from Monobank API");
            }
        } catch (\Exception $e) {
            wc_add_notice('Payment error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Add custom gateway icon
     * 
     * @var string Icon
     * @var string Payment id
     * */
    function morkva_monopay_gateway_icon( $icon, $id ) {
        if ( $id === 'morkva-monopay' && !is_admin()) {
            if($this->get_option( 'hide_image' ) == 'no'){

                $img_url = $this->get_icon_url();
                if ( ! $img_url ) return '';

                $width_btn = '';
                if($this->get_option( 'monopay_image_width' ) != 'no' && $this->get_option( 'monopay_image_width' ) != '') {
                    $width_btn = 'style="width: 100%; max-width: ' . $this->get_option( 'monopay_image_width' ) . 'px; padding-top: 0.6%;"';
                } else {
                    $width_btn = 'style="width: 100%; max-width: 100px; padding-top: 0"';
                }

                return '<img class="mrkv_plata_checkout" ' . $width_btn . ' src="' . $img_url . '" >';
            }
            return '';
        }
        elseif ($id === 'morkva-monopay' && is_admin() ) {
            $admin_url = $this->get_admin_icon_url();
            // Return the image tag specifically for the admin table
            return '<img src="' . esc_url( $admin_url ) . '" alt="' . esc_attr( $this->title ) . '" style="max-width: 100px; height: auto;" />';
        }
        return $icon;
    }

    /**
     * Get gateway icon url
     * */
    public function get_icon_url()
    {
        if($this->get_option( 'hide_image' ) == 'no'){
            if($this->get_option( 'url_monobank_img' )){
                return $this->get_option( 'url_monobank_img' ); 
            }
            else{
                if($this->get_option( 'monopay_image_type_white' ) != 'no')
                {
                    return plugins_url( '../assets/images/plata_dark_bg.png', __FILE__ );    
                }
                else{
                    return plugins_url( '../assets/images/plata_light_bg.png', __FILE__ ); 
                }
            }
        }

        return '';
    }

    public function get_admin_icon_url()
    {
        return plugins_url( '../assets/images/morkva-monopay-logo.svg', __FILE__ );
    }

    /**
     * Add Callback function. Handle
     * */
    public function return_handler() 
    {
        # Main callback
        $this->mrkv_mono_callback_success();
    }

    /**
     * Callback success function
     * */
    public function mrkv_mono_callback_success() 
    {   
        # Get content
        $mrkv_mono_callback_json = @file_get_contents('php://input');

        # Get callback data
        $mrkv_mono_callback = json_decode($mrkv_mono_callback_json, true);

        
        $logger = wc_get_logger();
        $context = array( 'source' => 'morkva-monobank-extended' );

        $log_message = "--- Monobank Callback ---\n";
        $log_message .= "Answer: " . wp_json_encode( $mrkv_mono_callback, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n";
        $log_message .= "------------------------";

        $logger->debug( $log_message, $context );
        

        # Check callback data
        if($mrkv_mono_callback){
            # Get response
            $mrkv_mono_response = new \MorkvaMonoGateway\Morkva_Mono_Response($mrkv_mono_callback);

            if(isset($mrkv_mono_callback['reference']))
            {
                $mrkv_mono_order_id = (int)$mrkv_mono_response->mrkv_mono_getOrderId();
                $mrkv_mono_order = wc_get_order( $mrkv_mono_order_id );

                if(!$mrkv_mono_order)
                {
                    return;
                }

                $mrkv_mono_order->update_meta_data( 'mrkv_mopay_payment_method', 'morkva-monopay');
                update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_payment_method', 'morkva-monopay' );

                do_action('mrkv_mono_plata_callback', $mrkv_mono_order, $mrkv_mono_response, 'morkva-monopay');

                if(isset($mrkv_mono_callback['status']))
                {
                    $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_status',  $mrkv_mono_callback['status']);
                    update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_status',  $mrkv_mono_callback['status'] );
                }
                if(isset($mrkv_mono_callback['reference']))
                {
                    $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_reference',  $mrkv_mono_callback['reference']);
                    update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_reference',  $mrkv_mono_callback['reference'] );
                }
                if(isset($mrkv_mono_callback['invoiceId']))
                {
                    $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_invoice_id',  $mrkv_mono_callback['invoiceId']);
                    update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_invoice_id',  $mrkv_mono_callback['invoiceId'] );
                }
                if(isset($mrkv_mono_callback['failureReason']))
                {
                    $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_failure_reason',  $mrkv_mono_callback['failureReason']);
                    update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_failure_reason',  $mrkv_mono_callback['failureReason'] );
                }
                if(isset($mrkv_mono_callback['paymentInfo']))
                {
                    if(isset($mrkv_mono_callback['paymentInfo']['maskedPan']))
                    {
                        $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_masked_pan',  $mrkv_mono_callback['paymentInfo']['maskedPan']);
                        update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_masked_pan',  $mrkv_mono_callback['paymentInfo']['maskedPan'] );
                    }
                    if(isset($mrkv_mono_callback['paymentInfo']['approvalCode']))
                    {
                        $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_approval_code',  $mrkv_mono_callback['paymentInfo']['approvalCode']);
                        update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_approval_code',  $mrkv_mono_callback['paymentInfo']['approvalCode'] );
                    }
                    if(isset($mrkv_mono_callback['paymentInfo']['rrn']))
                    {
                        $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_rrn',  $mrkv_mono_callback['paymentInfo']['rrn']);
                        update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_rrn',  $mrkv_mono_callback['paymentInfo']['rrn'] );
                    }

                    if($this->get_option( 'national_cashback_merchant_id' ))
                    {
                        $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_tran_id',  $this->get_option( 'national_cashback_merchant_id' ));
                        update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_tran_id',  $this->get_option( 'national_cashback_merchant_id' ) );
                    }
                    elseif(isset($mrkv_mono_callback['paymentInfo']['tranId']))
                    {
                        $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_tran_id',  $mrkv_mono_callback['paymentInfo']['tranId']);
                        update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_tran_id',  $mrkv_mono_callback['paymentInfo']['tranId'] );
                    }

                    if($this->get_option( 'national_cashback_terminal_id' )){
                        $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_terminal',  $this->get_option( 'national_cashback_terminal_id' ));
                        update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_terminal',  $this->get_option( 'national_cashback_terminal_id' ) );
                    }
                    elseif(isset($mrkv_mono_callback['paymentInfo']['terminal']))
                    {
                        $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_terminal',  $mrkv_mono_callback['paymentInfo']['terminal']);
                        update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_terminal',  $mrkv_mono_callback['paymentInfo']['terminal'] );
                    }
                    if(isset($mrkv_mono_callback['paymentInfo']['paymentSystem']))
                    {
                        $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_payment_system',  $mrkv_mono_callback['paymentInfo']['paymentSystem']);
                        update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_payment_system',  $mrkv_mono_callback['paymentInfo']['paymentSystem'] );
                    }
                    if(isset($mrkv_mono_callback['paymentInfo']['paymentMethod']))
                    {
                        $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_payment_method',  $mrkv_mono_callback['paymentInfo']['paymentMethod']);
                        update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_payment_method',  $mrkv_mono_callback['paymentInfo']['paymentMethod'] );
                    }
                    if(isset($mrkv_mono_callback['paymentInfo']['fee']))
                    {
                        $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_fee',  $mrkv_mono_callback['paymentInfo']['fee']);
                        update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_fee',  $mrkv_mono_callback['paymentInfo']['fee'] );
                    }
                }

                $mrkv_mono_order->save();
            }

            $invoice_final_amount = isset($mrkv_mono_callback['finalAmount']) ? $mrkv_mono_callback['finalAmount'] : 0;
            $amount = isset($mrkv_mono_callback['amount']) ? $mrkv_mono_callback['amount'] : 0;

            # Check status
            if($mrkv_mono_response->mrkv_mono_isComplete()) {
                global $woocommerce;

                $mrkv_mono_order_id = (int)$mrkv_mono_response->mrkv_mono_getOrderId();
                $mrkv_mono_order = wc_get_order( $mrkv_mono_order_id );

                if(!$mrkv_mono_order)
                {
                    return;
                }

                if ($invoice_final_amount != $amount) {
                    // translators: %1$s: Formatted invoice finalization amount
                    $mrkv_mono_order->add_order_note(
                        // translators: %1$s: Formatted invoice finalization amount
                        sprintf( __( 'Hold finalization amount %1$s UAH', 'morkva-monobank-extended' ), sprintf( '%.2f', $invoice_final_amount / 100 ) )
                    );
                }

                $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_payment_amount',  $invoice_final_amount);
                update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_payment_amount',  $invoice_final_amount );
                $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_payment_amount_refunded',  0);
                update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_payment_amount_refunded',  0 );
                $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_payment_amount_final',  $invoice_final_amount);
                update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_payment_amount_final',  $invoice_final_amount );

                $mrkv_mono_order->save();

                $woocommerce->cart->empty_cart();

                $new_order_status = ($this->get_option( 'monopay_order_status' ) && $this->get_option( 'monopay_order_status' ) != '') ? $this->get_option( 'monopay_order_status' ) : 'processing';

                $new_status_name = wc_get_order_status_name($new_order_status);
                $note_status = '[morkva plata] ' . __('Status changed to: ', 'morkva-monobank-extended') . $new_status_name;
                # Update order status
                $mrkv_mono_order->update_status($new_order_status, $note_status, true);

                if (!empty($mrkv_mono_callback['invoiceId'])) {
                    $mrkv_mono_order->set_transaction_id($mrkv_mono_callback['invoiceId']);
                    $mrkv_mono_order->save(); 
                }

                $mrkv_mono_order->payment_complete($mrkv_mono_response->mrkv_mono_getInvoiceId());
            }
            elseif($mrkv_mono_response->mrkv_mono_isHold())
            {
                $new_status_name = wc_get_order_status_name('on-hold');
                $note_status = '[morkva plata] ' . __('Status changed to: ', 'morkva-monobank-extended') . $new_status_name;
                $mrkv_mono_order->update_status('on-hold', $note_status, true);
                $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_payment_amount',  $amount);
                update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_payment_amount',  $amount );

                $mrkv_mono_order->save();

                global $woocommerce;

                $woocommerce->cart->empty_cart();


            }
        }
    }

    /**
     * Function status
     * @return string Status
     * */
    public function mrkv_mono_get_order_status_success() 
    {
        # Get status
        $new_order_status = ($this->get_option( 'monopay_order_status' ) && $this->get_option( 'monopay_order_status' ) != '') ? $this->get_option( 'monopay_order_status' ) : 'processing';
        # Return data
        return $new_order_status;

    }

    /**
     * Function can refund
     * @param object Order data
     * @return mixed Data
     * */
    public function mrkv_mono_can_refund_order( $order, $mrkv_mono_transaction_id ) 
    {
        # Get api key
        $mrkv_mono_has_api_creds = $this->mrkv_mono_getToken();
        # Return data
        return $order && $mrkv_mono_transaction_id && $mrkv_mono_has_api_creds;

    }

    /**
     * Function process refund
     * @var integer Order id
     * @var integer Order total
     * @var string Reason
     * @return Result 
     * */
    public function process_refund( $order_id, $amount = null, $reason = '' ) 
    {

        $mrkv_mono_order = wc_get_order( $order_id );
        $mrkv_mono_transaction_id = $mrkv_mono_order->get_transaction_id();

        $mono_subscribe_transaction_id = $mrkv_mono_order->get_meta('mrkv_mono_subscribe_invoice');

        if(!$mrkv_mono_transaction_id && $mono_subscribe_transaction_id){
            $mrkv_mono_transaction_id = $mono_subscribe_transaction_id;
        }

        if ( ! $this->mrkv_mono_can_refund_order( $mrkv_mono_order, $mrkv_mono_transaction_id ) ) {
            return new WP_Error( 'error', __( 'Refund failed.', 'morkva-monobank-extended' ) );
        }

        $mrkv_mono_token = $this->mrkv_mono_getToken();
        $mrkv_mono_payment = new Morkva_Mono_Payment($mrkv_mono_token);
        $mrkv_mono_refund_order = array(
            "invoiceId" => $mrkv_mono_transaction_id,
            "amount" => $amount*100
        );
        
        $mrkvmonoOrder = new Morkva_Mono_Order();
        $mrkvmonoOrder->mrkv_mono_setId($mrkv_mono_order->get_id());

        $mrkv_mono_payment->mrkv_mono_setRefundOrder($mrkv_mono_refund_order);
        $mrkv_mono_payment->mrkv_mono_setOrder($mrkvmonoOrder);

        try {
            $mrkv_mono_result = $mrkv_mono_payment->mrkv_mono_cancel();
            if ( is_wp_error( $mrkv_mono_result ) ) {
                //$this->log( 'Refund Failed: ' . $result->get_error_message(), 'error' );
                return new WP_Error( 'error', $mrkv_mono_result->get_error_message() );
            }
            if ($mrkv_mono_result->stage == "c") {
                // translators: %1$s: Refund amount, %2$s: Monobank transaction refund reference ID
                $mrkv_mono_order->add_order_note(
                    // translators: %1$s: Refund amount, %2$s: Monobank transaction refund reference ID
                    sprintf( __( 'Refunded %1$s - Refund ID: %2$s', 'morkva-monobank-extended' ), $amount, $mrkv_mono_result->cancelRef )
                );
                return true;
            }
        } catch (\Exception $e) {
            wc_add_notice('Request error (' . $e->getMessage() . ')', 'error');
            return false;
        }
        return false;
    }

    /**
     * Return settigs mono token
     * @return string Token
     * */
    protected function mrkv_mono_getToken() 
    {
        # Check test mode
        if($this->get_option( 'enabled_test_mode' ) == 'yes' && $this->get_option( 'enabled_test_mode_admin' ) != 'yes')
        {
            # Return monopay token
            return $this->get_option( 'TEST_API_KEY' );
        }
        elseif($this->get_option( 'enabled_test_mode_admin' ) == 'yes' && ( current_user_can('editor') || current_user_can('administrator') ))
        {
            # Return monopay test token
            return $this->get_option( 'TEST_API_KEY' );
        }
        else
        {   
            # Return monopay test token
            return $this->mrkv_mono_token;
        }
    }

    /**
     * Add styles to settings
     * */
    public function mrkv_mono_style_settings(){
        # Add styles
        wp_enqueue_style( 'monopay-setting-style', MORKVAMONOGATEWAY_PATH . 'assets/css/monopay-setting-style.css', array(), MORKVAMONOGATEWAY_VERSION );
    }

    /**
     * Add scripts to settings
     * */
    public function mrkv_mono_scripts_settings(){
        wp_enqueue_script('monopay-setting-script', MORKVAMONOGATEWAY_PATH . 'assets/js/monopay-setting-script.js', array('jquery'), MORKVAMONOGATEWAY_VERSION, true);
    }

    /**
     * Return settigs mono token
     * @return string Token
     * */
    public function get_mrkv_mono_getToken() 
    {
        # Check test mode
        if($this->get_option( 'enabled_test_mode' ) == 'yes' && $this->get_option( 'enabled_test_mode_admin' ) != 'yes')
        {
            # Return monopay token
            return $this->get_option( 'TEST_API_KEY' );
        }
        elseif($this->get_option( 'enabled_test_mode_admin' ) == 'yes' && ( current_user_can('editor') || current_user_can('administrator') ))
        {
            # Return monopay test token
            return $this->get_option( 'TEST_API_KEY' );
        }
        else
        {   
            # Return monopay test token
            return $this->mrkv_mono_token;
        }
    }

    /**
     * Return hold status final
     * @return string hold status final
     * */
    public function get_mrkv_mono_hold_status_final()
    {
        if($this->get_option( 'hold_finale_status' ))
        {
            return $this->get_option( 'hold_finale_status' );
        }
        else
        {
            return '';
        }
    }

    /**
     * Return hold status cancel
     * @return string hold status cancel
     * */
    public function get_mrkv_mono_hold_cancel_status()
    {
        if($this->get_option( 'hold_cancel_status' ))
        {
            return $this->get_option( 'hold_cancel_status' );
        }
        else
        {
            return '';
        }
    }

    /**
     * Return hold enabled
     * @return string hold enabled
     * */
    public function get_mrkv_mono_hold_enabled()
    {
        if($this->get_option( 'use_holds' ) && $this->get_option( 'use_holds' ) == 'yes')
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Subscription
     * @param object Order
     * */
    public function mrkv_mono_wps_subscription_payment($renewal_order, $wps_sfw_renewal_parent_order)
    {
        $parent_order = wc_get_order( $wps_sfw_renewal_parent_order );
        if ($parent_order && $parent_order->get_meta('mrkv_mopay_accuiring_card_token')) 
        {
            $mrkv_mono_basket_info = array();

            $order_main_amount = 0;

            # Loop all Cart data
            foreach ($renewal_order->get_items() as $item_id => $item) 
            {
                $product = $item->get_product();
                $_wps_sfw_product = get_post_meta( $product->get_id(), '_wps_sfw_product', true );

                if( 'yes' == $_wps_sfw_product ) 
                {
                    $image = wp_get_attachment_image_src( get_post_thumbnail_id( $product->get_id() ), 'single-post-thumbnail' );

                    # Set product data
                    $mrkv_mono_basket_info[] = [
                        "name" => $item->get_name(),
                        "qty"  => $item->get_quantity(),
                        "sum"  => round($item->get_total()*100) / intval($item->get_quantity()),
                        "total"  => round($item->get_total()*100),
                        "icon" => $image[0],
                        "code" => "" . $product->get_id()
                    ];

                    $order_main_amount += intval($item->get_total()*100);
                }
            }

            # Set order data to send query
            $mrkvmonoOrder = new Morkva_Mono_Order();

            # Set data
            $mrkvmonoOrder->mrkv_mono_setCurrency($renewal_order->get_currency());
            $mrkvmonoOrder->mrkv_mono_setId($renewal_order->get_id());
            $mrkvmonoOrder->mrkv_mono_setReference($renewal_order->get_id());
            $mrkvmonoOrder->mrkv_mono_setAmount($order_main_amount);
            $mrkvmonoOrder->mrkv_mono_setBasketOrder($mrkv_mono_basket_info);

            $mrkv_mono_destination = $this->mrkv_mono_render_destination( $renewal_order );
            if ( $mrkv_mono_destination === '' ) {
                $mrkv_mono_destination = $renewal_order->get_billing_last_name() . ' ' .
                    $renewal_order->get_billing_first_name() . ' - ' .
                    $renewal_order->get_id();
            }
            $mrkvmonoOrder->mrkv_mono_setDestination( $mrkv_mono_destination );

            # Check 
            $web_url = get_site_url();
            if($web_url){
                $mrkvmonoOrder->mrkv_mono_setRedirectUrl($web_url . '/checkout/order-received/' . $renewal_order->get_id() . '/?key=' . $renewal_order->get_order_key());
                $mrkvmonoOrder->mrkv_mono_setWebHookUrl($web_url . '/?wc-api=morkva-monopay-subscribe');
            }

            # Get user token
            $mrkv_mono_token = $this->mrkv_mono_getToken();

            # Create Payment object 
            $mrkv_mono_payment = new Morkva_Mono_Payment($mrkv_mono_token);

            $mrkvmonoOrder->mrkv_mono_setCardToken($parent_order->get_meta('mrkv_mopay_accuiring_card_token'));

            $mrkv_mono_payment->mrkv_mono_setOrder($mrkvmonoOrder);

            # Create invoice
            $mrkv_mono_invoice = $mrkv_mono_payment->mrkv_mono_create_subscribe(true);
            
            if(isset($mrkv_mono_invoice->status) && $mrkv_mono_invoice->status == 'success')
            {
                return true;
            }

            if(isset($mrkv_mono_invoice->status) && $mrkv_mono_invoice->status == 'processing')
            {
                $renewal_order->update_meta_data( 'mrkv_mono_subscribe_invoice', $mrkv_mono_invoice->invoiceId );
                $renewal_order->update_meta_data( 'mrkv_mono_subscribe_status', 'processing' );

                $renewal_order->save();

                sleep(15);

                $mrkv_mono_url = 'https://api.monobank.ua/api/merchant/invoice/status?invoiceId=' . $mrkv_mono_invoice->invoiceId;

                # Create request header
                $mrkv_mono_headers = array(
                    'Content-type'  => 'application/json',
                    'X-Token' => $this->mrkv_mono_getToken(),
                    'X-Cms' => 'morkva'
                );

                # Create request args
                $mrkv_mono_args = array(
                    'method'      => 'GET',
                    'headers'     => $mrkv_mono_headers,
                    'user-agent'  => 'WooCommerce/' . WC()->version,
                );

                # Send request
                $mrkv_mono_request = wp_safe_remote_post($mrkv_mono_url, $mrkv_mono_args);

                do_action('mrkv_mono_plata_callback', $renewal_order, $mrkv_mono_request, 'subscription');

                $new_status_mono = '';

                # Check request status
                if ($mrkv_mono_request === false) 
                {
                    $renewal_order_new = wc_get_order( $renewal_order->get_id() );
                    $new_status_mono = $renewal_order_new->get_meta('mrkv_mono_subscribe_status');
                }
                else
                {
                    $result_invoice = json_decode($mrkv_mono_request['body'], true);
                    $new_status_mono = $result_invoice['status'];

                    if(isset($mrkv_mono_request['failureReason']))
                    {
                        # Add message to order
                        $renewal_order->add_order_note('failureReason: ' . wp_json_encode($mrkv_mono_request['failureReason'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), $is_customer_note = 0, $added_by_user = false);
                    }

                    if(isset($mrkv_mono_request['errText']))
                    {
                        # Add message to order
                        $renewal_order->add_order_note('errText: ' . wp_json_encode($mrkv_mono_request['errText'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), $is_customer_note = 0, $added_by_user = false);
                    }
                }
                

                if($new_status_mono == 'success')
                {
                    return true;
                }
                else{
                    return false;
                }
            }

            if(isset($mrkv_mono_invoice->failureReason))
            {
                # Add message to order
                $renewal_order->add_order_note('failureReason: ' . wp_json_encode($mrkv_mono_invoice->failureReason, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), $is_customer_note = 0, $added_by_user = false);
            }

            if(isset($mrkv_mono_invoice->errText))
            {
                # Add message to order
                $renewal_order->add_order_note('errText: ' . wp_json_encode($mrkv_mono_invoice->errText, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), $is_customer_note = 0, $added_by_user = false);
            }
        }
        
        return false;
    }

    /**
     * Hide payment method if "Test mode for administrator" is enabled and user is not an admin.
     * 
     * @param array $available_gateways
     * @return array
     */
    public function mrkv_filter_gateway_by_admin_test_mode($available_gateways)
    {
        if (is_admin() || !isset($available_gateways[$this->id])) {
            return $available_gateways;
        }

        $test_admin_only = $this->get_option('enabled_test_mode_admin');

        if ($test_admin_only === 'yes') {
            if (!current_user_can('manage_options') && !current_user_can('administrator')) {
                unset($available_gateways[$this->id]);
            }
        }

        return $available_gateways;
    }

    /**
    * Render payment destination template against an order.
    * Returns empty string if template is not set — caller decides fallback.
    *
    * @param WC_Order|WC_Subscription $order
    * @return string
    */
    private function mrkv_mono_render_destination( $order ): string {
        $template = (string) $this->get_option( 'payment_destination_template', '' );
        $template = trim( $template );

        if ( $template === '' ) {
            return '';
        }

        $replacements = array(
            '{order_id}'           => (string) $order->get_id(),
            '{billing_first_name}' => (string) $order->get_billing_first_name(),
            '{billing_last_name}'  => (string) $order->get_billing_last_name(),
        );

        return strtr( $template, $replacements );
    }

    /**
     * Verifies if shipping cost should be included in the Monobank payload.
     * Prevents logic errors when using WCUkrShipping with 'add_cost_to_order' => 'no'.
     *
     * @param \WC_Order $order
     * @return bool
     */
    private function should_include_shipping_to_monobank( $order ) 
    {
        $shipping_total = $order->get_shipping_total();
        if ( $shipping_total <= 0 ) {
            return false;
        }

        $shipping_methods = $order->get_shipping_methods();
        $shipping_method  = reset( $shipping_methods ); 
        
        if ( $shipping_method ) {
            $method_id   = $shipping_method->get_method_id(); 
            $instance_id = $shipping_method->get_instance_id();

            $wcus_methods = [
                'nova_poshta_shipping',
                'wcus_nova_poshta', 
                'wcus_ukrposhta', 
                'wcus_ukrposhta_address', 
                'wcus_rozetka_delivery', 
                'wcus_meest', 
                'wcus_meest_address', 
                'wcus_nova_post', 
                'wcus_nova_global_address'
            ];

            if ( in_array( $method_id, $wcus_methods, true ) ) {
                $shipping_options = get_option( 'woocommerce_' . $method_id . '_' . $instance_id . '_settings', [] );
                $add_cost_to_order = isset( $shipping_options['add_cost_to_order'] ) ? $shipping_options['add_cost_to_order'] : 'no';

                if ( 'no' === $add_cost_to_order ) {
                    return false;
                }
            }
        }

        return $order->get_shipping_total() > 0;
    }
}
