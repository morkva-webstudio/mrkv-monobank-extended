<?php
if ( ! defined( 'ABSPATH' ) ) exit;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;
# Include namespaces
use MorkvaMonoGateway\Morkva_Mono_Order;
use MorkvaMonoGateway\Morkva_Mono_Payment;

# Check if class exist
if (!class_exists('MorkvaMonopayOrders'))
{
	/**
	 * Class for add widget orders
	 */
	class MorkvaMonopayOrders
	{
		/**
		 * Constructor for add orders data
		 * */
		function __construct()
		{
			add_action('add_meta_boxes', array( $this, 'mrkv_monopay_add_meta_boxes' ), 10, 2);
			add_action('woocommerce_order_status_changed', array($this, 'mrkv_monopay_finalize_hold'), 10, 4);

			add_action( 'wp_ajax_mrkv_mono_accuiring_status_check', array( $this, 'mrkv_mono_accuiring_status_check_func' ) );
			add_action( 'wp_ajax_nopriv_mrkv_mono_accuiring_status_check', array( $this, 'mrkv_mono_accuiring_status_check_func' ) );

			add_action( 'wp_ajax_mrkv_mono_cancel_payment_hold', array( $this, 'mrkv_mono_cancel_payment_hold_func' ) );
			add_action( 'wp_ajax_nopriv_mrkv_mono_cancel_payment_hold', array( $this, 'mrkv_mono_cancel_payment_hold_func' ) );

			add_action( 'wp_ajax_mrkv_mono_final_payment_hold', array( $this, 'mrkv_mono_final_payment_hold_func' ) );
			add_action( 'wp_ajax_nopriv_mrkv_mono_final_payment_hold', array( $this, 'mrkv_mono_final_payment_hold_func' ) );

			add_action('mrkv_mono_plata_settings_sidebar', [$this, 'mrkv_mono_plata_settings_sidebar_func']);

			if ( ! wp_next_scheduled( 'mrkv_mono_delete_old_logs_event' ) ) {
				wp_schedule_event( time(), 'twicedaily', 'mrkv_mono_delete_old_logs_event' );
			}

			add_action( 'mrkv_mono_delete_old_logs_event', [$this, 'mrkv_mono_clean_logs'] );
		}

		public function mrkv_mono_clean_logs() {
			$log_dir = defined( 'WC_LOG_DIR' ) ? WC_LOG_DIR : WP_CONTENT_DIR . '/uploads/wc-logs/';
			$source  = 'mrkv-monobank-extended';
			$files = glob( $log_dir . $source . '*.log' );

			if ( ! empty( $files ) && is_array( $files ) ) {
				$three_days_ago = time() - ( 3 * DAY_IN_SECONDS ); 

				foreach ( $files as $file ) {
					if ( file_exists( $file ) ) {
						$file_modified_time = filemtime( $file );

						if ( $file_modified_time < $three_days_ago ) {
							@unlink( $file );
						}
					}
				}
			}
		}

		public function mrkv_mono_plata_settings_sidebar_func()
		{
			?>
				<div class="morkva-settings-sidebar" style="flex: 1; min-width: 250px;">
					<div class="morkva-settings-sidebar_inner" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;margin-bottom:15px;">
						<h3 style="margin-top: 0;"><?php echo esc_html__( 'Like this plugin?', 'morkva-monobank-extended' ); ?></h3>
						<p>
							<?php echo esc_html__( 'Support our efforts with a', 'morkva-monobank-extended' ) . ' '; ?>
							<img src="<?php echo esc_url( plugins_url( '../assets/images/star.svg', __FILE__ ) ); ?>" alt="Star" alt="Star">
							<img src="<?php echo esc_url( plugins_url( '../assets/images/star.svg', __FILE__ ) ); ?>" alt="Star" alt="Star">
							<img src="<?php echo esc_url( plugins_url( '../assets/images/star.svg', __FILE__ ) ); ?>" alt="Star" alt="Star">
							<img src="<?php echo esc_url( plugins_url( '../assets/images/star.svg', __FILE__ ) ); ?>" alt="Star" alt="Star">
							<img src="<?php echo esc_url( plugins_url( '../assets/images/star.svg', __FILE__ ) ); ?>" alt="Star" alt="Star">
							<?php echo esc_html__( 'review at', 'morkva-monobank-extended' ) . ' <a href="https://wordpress.org/plugins/mrkv-monobank-extended/" target="blanc">WordPress.org</a>'; ?>
						</p>
						<a class="button button-primary" href="https://wordpress.org/plugins/mrkv-monobank-extended/" target="blanc">
							<?php echo esc_html__( 'Leave', 'morkva-monobank-extended' ) . ' '; ?>
							<img src="<?php echo esc_url( plugins_url( '../assets/images/star.svg', __FILE__ ) ); ?>" alt="Star" alt="Star">
							<img src="<?php echo esc_url( plugins_url( '../assets/images/star.svg', __FILE__ ) ); ?>" alt="Star" alt="Star">
							<img src="<?php echo esc_url( plugins_url( '../assets/images/star.svg', __FILE__ ) ); ?>" alt="Star" alt="Star">
							<img src="<?php echo esc_url( plugins_url( '../assets/images/star.svg', __FILE__ ) ); ?>" alt="Star" alt="Star">
							<img src="<?php echo esc_url( plugins_url( '../assets/images/star.svg', __FILE__ ) ); ?>" alt="Star" alt="Star">
						</a>
						<p>
							<?php echo esc_html__( 'Isn’t good enough for a 5', 'morkva-monobank-extended' ) . ' '; ?>
							<img src="<?php echo esc_url( plugins_url( '../assets/images/star.svg', __FILE__ ) ); ?>" alt="Star" alt="Star">? 
							<?php echo esc_html__( 'Contact us via the widget on our website, or check out', 'morkva-monobank-extended' ) . ' <a href="https://docs.morkva.co.ua/uk?utm_source=plugin&utm_medium=sidebar&utm_campaign=plata_free" target="blanc">' . esc_html__( 'documantation', 'morkva-monobank-extended' ) . '</a>'; ?>
						</p>
						<div class="mrkv-btns-line-sidebar" style="display: flex;gap: 4px;">
							<a class="button button-primary" href="https://morkva.co.ua/?utm_source=plugin&utm_medium=sidebar&utm_campaign=plata_free" target="blanc">
								<?php echo esc_html__( 'Go to the website', 'morkva-monobank-extended' ); ?>
							</a>
							<a class="button" href="https://docs.morkva.co.ua/uk?utm_source=plugin&utm_medium=sidebar&utm_campaign=plata_free" target="blanc">
								<?php echo esc_html__( 'Documantation', 'morkva-monobank-extended' ); ?>
							</a>
						</div>
					</div>
					<div class="morkva-settings-sidebar_inner" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;margin-bottom:15px;">
						<h3 style="margin-top: 0;"><?php echo esc_html__( 'Check out pro-version', 'morkva-monobank-extended' ); ?></h3>
						<ul>
							<li>
								<img src="<?php echo esc_url( plugins_url( '../assets/images/check.svg', __FILE__ ) ); ?>" alt="Check" alt="Check">
								<?php echo esc_html__( 'Pay by Parts', 'morkva-monobank-extended' ); ?>
							</li>
							<li>
								<img src="<?php echo esc_url( plugins_url( '../assets/images/check.svg', __FILE__ ) ); ?>" alt="Check" alt="Check">
								<?php echo esc_html__( 'Payment status validation', 'morkva-monobank-extended' ); ?>
							</li>
							<li>
								<img src="<?php echo esc_url( plugins_url( '../assets/images/check.svg', __FILE__ ) ); ?>" alt="Check" alt="Check">
								<?php echo esc_html__( 'Prepay', 'morkva-monobank-extended' ); ?>
							</li>
							<li><?php echo esc_html__( 'and more', 'morkva-monobank-extended' ); ?></li>
						</ul>
						<a class="button button-primary" href="https://morkva.co.ua/shop/woocommerce-plata-by-mono-extended-pro?utm_source=plugin&utm_medium=sidebar&utm_campaign=plata_free" target="blanc">
							<?php echo esc_html__( 'Buy Pro-version', 'morkva-monobank-extended' ); ?>
						</a>
					</div>
					<div class="morkva-settings-sidebar_inner" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;margin-bottom:15px;">
						<h3 style="margin-top: 0;"><?php echo esc_html( __( 'Other free plugins', 'morkva-monobank-extended' ) ); ?></h3>
						<p><?php echo esc_html__( 'All our plugins are cross-compatible', 'morkva-monobank-extended' ); ?></p>
						<?php
							$response = wp_remote_get( 'https://morkva.co.ua/wp-json/pluginManagement/v2', array(
								'headers' => array(
								),
								'timeout' => 30,
								'redirection' => 5,
								'httpversion' => '1.1',
								'sslverify' => true
							));

							$mrkv_mono_response_data = $response['body'] ? json_decode( $response['body'], true ) : null;
							$mrkv_mono_plugins = $mrkv_mono_response_data['plugins'] ?? [];

							if(!empty($mrkv_mono_plugins))
							{
								?>
									<ul style="list-style: disc;padding-left: 17px;">
										<?php
											foreach($mrkv_mono_plugins as $plugin_slug => $plugin_data)
											{
												if($plugin_slug == 'morkva-monobank-extended'){ continue; }
												?>
													<li>
														<a style="margin-bottom:5px;" href="<?php echo esc_url( $plugin_data['url'] ?? '' ); ?>" target="blanc" class="plugin_line"><?php echo esc_html( $plugin_data['label'] ?? '' ); ?></a>
														<span>- 
														<?php 
															$current_desc = (strpos(get_user_locale(), 'uk') === 0) 
																? ($plugin_data['description'] ?? '') 
																: ($plugin_data['description_en'] ?? '');
																
															echo esc_html( $current_desc ); 
														?>
														</span>
													</li>
												<?php
											}
										?>
									</ul>
								<?php
							}
						?>
					</div>
				</div>
			<?php
		}

		public function mrkv_monopay_finalize_hold($order_id, $old_status, $new_status, $order)
		{
			# Get payment method
            $payment_method = $order->get_payment_method();

            # Check monopay method
            if('morkva-monopay' == $payment_method)
            {
            	$wc_gateways      = WC()->payment_gateways();
	    		$payment_gateways = $wc_gateways->get_available_payment_gateways();

	    		if ( !isset( $payment_gateways['morkva-monopay'] ) ) {
	    			return;
	    		}

	    		$mono_payment_gateway = $payment_gateways['morkva-monopay'];

	    		if($mono_payment_gateway && $mono_payment_gateway->get_mrkv_mono_hold_enabled())
	    		{
	    			$status_hold = $mono_payment_gateway->get_mrkv_mono_hold_status_final();
	    			$is_finalize_hold = false;

	    			if($status_hold)
	    			{
	    				if ($new_status == $status_hold) 
	    				{
					        $is_finalize_hold = true;
					    }
	    			}
	    			elseif($new_status == 'completed')
	    			{
	    				$is_finalize_hold = true;
	    			}

	    			if($is_finalize_hold)
	    			{
	    				$mrkv_mono_token = $mono_payment_gateway->get_mrkv_mono_getToken();

	    				$payment_amount = intval($order->get_meta('mrkv_mopay_accuiring_payment_amount'));
			        	$payment_amount = sprintf('%.2f', $payment_amount / 100);
	    				$finalization_amount = floatval($payment_amount);

	    				$mrkvmonoOrder = new Morkva_Mono_Order();
						$mrkvmonoOrder->mrkv_mono_setId($order->get_id());

						$mrkv_mono_payment = new Morkva_Mono_Payment($mrkv_mono_token);
						$mrkv_mono_payment->mrkv_mono_setOrder($mrkvmonoOrder);

				        try {
				            $result = $mrkv_mono_payment->mrkv_mono_finalize_hold(array(
				                "invoiceId" => $order->get_meta('mrkv_mopay_accuiring_invoice_id'),
				                "amount" => (int)((float)$finalization_amount * 100 + 0.5),
				            ));

				            if (is_wp_error($result)) {
				                return new WP_Error('error', $result->get_error_message());
				            }
				            if (isset($result) && is_object($result) && isset($result->errText)) {
				                $order->add_order_note(__('Failed to finalize invoice: ', 'morkva-monobank-extended') . $result->errText);
				            }
				            else
				            {
				            	$order->add_order_note(__('Hold finalized', 'morkva-monobank-extended'));
								$status_force = function() use ($new_status) { return $new_status; };
    							add_filter('woocommerce_payment_complete_order_status', $status_force, 999);
				            	$order->payment_complete($order->get_meta('mrkv_mopay_accuiring_invoice_id'));
								remove_filter('woocommerce_payment_complete_order_status', $status_force, 999);
				            }
				        } catch (\Exception $e) {
				            $order->add_order_note(__('Hold cancellation error: ', 'morkva-monobank-extended') . $e->getMessage());
				            return;
				        }
	    			}

					$status_cancel_hold = $mono_payment_gateway->get_mrkv_mono_hold_cancel_status();
	    			$is_cancel_hold = false;

	    			if($status_cancel_hold)
	    			{
	    				if ($new_status == $status_cancel_hold) 
	    				{
					        $is_cancel_hold = true;
					    }
	    			}
	    			elseif($new_status == 'cancelled')
	    			{
	    				$is_cancel_hold = true;
	    			}
					
					if($is_cancel_hold)
	    			{
						$mrkv_mono_token = $mono_payment_gateway->get_mrkv_mono_getToken();

						$mrkvmonoOrder = new Morkva_Mono_Order();
						$mrkvmonoOrder->mrkv_mono_setId($order->get_id());

						$mrkv_mono_payment = new Morkva_Mono_Payment($mrkv_mono_token);
						$mrkv_mono_payment->mrkv_mono_setOrder($mrkvmonoOrder);

						$mrkv_mono_payment->mrkv_mono_hold_cancel(array("invoiceId" => $order->get_meta('mrkv_mopay_accuiring_invoice_id'),
							"extRef" => (string)$order_id,));

						$order->add_order_note(__('Hold canceled', 'morkva-monobank-extended'));
					}
	    		}
            }
		}

		public function mrkv_mono_cancel_payment_hold_func()
		{
			$order_id = isset($_POST['order_id']) ? sanitize_text_field( wp_unslash($_POST['order_id'])) : 0;

			if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field( wp_unslash($_POST['nonce'])), 'mrkv_mono_nonce')) {
		        wp_send_json_error(__('Invalid nonce.', 'morkva-monobank-extended'), 403);
		        wp_die();
		    }
			
			if (!current_user_can('edit_shop_orders')) {
				wp_send_json_error(__('You do not have permission to edit orders.', 'morkva-monobank-extended'), 403);
			}
			if(!$order_id)
			{
	            wp_send_json_error(__('Invalid order ID.', 'morkva-monobank-extended'), 400);
	            wp_die();
            }

	        $order = wc_get_order($order_id);
	        if ($order) 
	        {
	            $order_status = $order->get_status();
		        if ($order_status == 'on-hold') 
		        {
		        	# Get token by mono gateway
		    		$wc_gateways      = WC()->payment_gateways();
		    		$payment_gateways = $wc_gateways->get_available_payment_gateways();
		    		$mono_payment_gateway = $payment_gateways['morkva-monopay'];
		    		$mrkv_mono_token = $mono_payment_gateway->get_mrkv_mono_getToken();

					$mrkvmonoOrder = new Morkva_Mono_Order();
					$mrkvmonoOrder->mrkv_mono_setId($order->get_id());

					$mrkv_mono_payment = new Morkva_Mono_Payment($mrkv_mono_token);
					$mrkv_mono_payment->mrkv_mono_setOrder($mrkvmonoOrder);

		    		$mrkv_mono_payment->mrkv_mono_hold_cancel(array("invoiceId" => $order->get_meta('mrkv_mopay_accuiring_invoice_id'),
                		"extRef" => (string)$order_id,));

		    		$order->add_order_note(__('Hold canceled', 'morkva-monobank-extended'));
		        }
	        }
		}

		public function mrkv_mono_final_payment_hold_func()
		{
			$order_id = isset($_POST['order_id']) ? sanitize_text_field( wp_unslash($_POST['order_id'])) : 0;

			if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field( wp_unslash($_POST['nonce'])), 'mrkv_mono_nonce')) {
		        wp_send_json_error(__('Invalid nonce.', 'morkva-monobank-extended'), 403);
		        wp_die();
		    }
			
			if (!current_user_can('edit_shop_orders')) {
				wp_send_json_error(__('You do not have permission to edit orders.', 'morkva-monobank-extended'), 403);
			}
			if(!$order_id)
			{
	            wp_send_json_error(__('Invalid order ID.', 'morkva-monobank-extended'), 400);
	            wp_die();
            }

	        $order = wc_get_order($order_id);
	        if ($order) 
	        {
	            $order_status = $order->get_status();
		        if ($order_status == 'on-hold') 
		        {
		        	# Get token by mono gateway
		    		$wc_gateways      = WC()->payment_gateways();
		    		$payment_gateways = $wc_gateways->get_available_payment_gateways();
		    		$mono_payment_gateway = $payment_gateways['morkva-monopay'] ?? null;

					if ( ! $mono_payment_gateway ) {
						return;
					}

		    		$mrkv_mono_token = $mono_payment_gateway->get_mrkv_mono_getToken();

		    		$mrkvmonoOrder = new Morkva_Mono_Order();
					$mrkvmonoOrder->mrkv_mono_setId($order->get_id());

					$mrkv_mono_payment = new Morkva_Mono_Payment($mrkv_mono_token);
					$mrkv_mono_payment->mrkv_mono_setOrder($mrkvmonoOrder);

		    		$finalization_amount = isset($_POST['finalization_amount']) ? floatval($_POST['finalization_amount']) : 0;
			        try {
			            $result = $mrkv_mono_payment->mrkv_mono_finalize_hold(array(
			                "invoiceId" => $order->get_meta('mrkv_mopay_accuiring_invoice_id'),
			                "amount" => (int)((float)$finalization_amount * 100 + 0.5),
			            ));

			            if (is_wp_error($result)) {
			                return new WP_Error('error', $result->get_error_message());
			            }
			            if (isset($result) && is_object($result) && isset($result->errText)) {
			                $order->add_order_note(__('Failed to finalize invoice: ', 'morkva-monobank-extended') . $result->errText);
			            }
			            else
			            {
			            	$order->add_order_note(__('Hold finalized', 'morkva-monobank-extended'));
			            	$order->payment_complete($order->get_meta('mrkv_mopay_accuiring_invoice_id'));
			            }
			        } catch (\Exception $e) {
			            $order->add_order_note(__('Hold cancellation error: ', 'morkva-monobank-extended') . $e->getMessage());
			            return;
			        }
		        }
	        }
		}

		/**
		 * Check mono accuiring status
		 * */
		public function mrkv_mono_accuiring_status_check_func()
		{
			if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field( wp_unslash($_POST['nonce'])), 'mrkv_mono_nonce')) {
		        wp_send_json_error(__('Invalid nonce.', 'morkva-monobank-extended'), 403);
		        wp_die();
		    }
			
			if (!current_user_can('edit_shop_orders')) {
				wp_send_json_error(__('You do not have permission to edit orders.', 'morkva-monobank-extended'), 403);
			}

			if(isset($_POST['order_id']))
			{
				# Get token by mono gateway
	    		$wc_gateways      = WC()->payment_gateways();
	    		$payment_gateways = $wc_gateways->get_available_payment_gateways();
	    		$mono_payment_gateway = $payment_gateways['morkva-monopay'] ?? null;

				if ( ! $mono_payment_gateway ) {
					return;
				}
				
	    		$mrkv_mono_token = $mono_payment_gateway->get_mrkv_mono_getToken();

				# Create request header
		        $mrkv_mono_headers = array(
		            'Content-type'  => 'application/json',
		            'X-Token' => $mrkv_mono_token,
		            'X-Cms' => 'morkva'
		        );

				# Create request args
		        $mrkv_mono_args = array(
		            'method'      => 'GET',
		            'headers'     => $mrkv_mono_headers,
		            'user-agent'  => 'WooCommerce/' . WC()->version,
		        );

		        $mrkv_mono_order_id = sanitize_text_field( wp_unslash($_POST['order_id']) );

		        $mrkv_mono_order = wc_get_order($mrkv_mono_order_id);

		        $invoice_number = $mrkv_mono_order->get_meta('mrkv_mopay_accuiring_invoice_id');

		        $response = wp_remote_get('https://api.monobank.ua/api/merchant/invoice/status?invoiceId=' . $invoice_number, $mrkv_mono_args);


		        if( 200 === wp_remote_retrieve_response_code( $response ) ) 
		        {
					$response = json_decode( wp_remote_retrieve_body( $response ), true );

					if(isset($response['status']))
					{
						$mrkv_mono_order->update_meta_data( 'mrkv_mopay_payment_method', 'morkva-monopay');
		                update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_payment_method', 'morkva-monopay' );

		                $mrkv_mono_callback = $response;

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
		                    if(isset($mrkv_mono_callback['paymentInfo']['tranId']))
		                    {
		                        $mrkv_mono_order->update_meta_data( 'mrkv_mopay_accuiring_tran_id',  $mrkv_mono_callback['paymentInfo']['tranId']);
		                        update_post_meta( $mrkv_mono_order_id, 'mrkv_mopay_accuiring_tran_id',  $mrkv_mono_callback['paymentInfo']['tranId'] );
		                    }
		                    if(isset($mrkv_mono_callback['paymentInfo']['terminal']))
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

		                if($response['status'] == 'success')
		                {
		                	$new_status_name = wc_get_order_status_name($mono_payment_gateway->mrkv_mono_get_order_status_success());
                    		$note_status = '[morkva plata] ' . __('Status changed to: ', 'morkva-monobank-extended') . $new_status_name;
		                	# Update order status
                			$mrkv_mono_order->update_status($mono_payment_gateway->mrkv_mono_get_order_status_success(), $note_status, true);
		                }

		                $mrkv_mono_order->save();
					}
				}
			}

			wp_die();
		}

		/**
	     * Generating meta boxes
	     *
	     * @since 1.0.0
	     */
	    public function mrkv_monopay_add_meta_boxes($post_type, $post)
	    {
	        # Check hpos
	        if(class_exists( CustomOrdersTableController::class )){
	            $screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
	            ? wc_get_page_screen_id( 'shop-order' )
	            : 'shop_order';
	        }
	        else{
	            $screen = 'shop_order';
	        }


	        if ( $post instanceof \WP_Post ) {
                $order_id = $post->ID;
            } elseif ( $post instanceof \WC_Order ) {
                $order_id = $post->get_id();
            } else {
                $order_id = false;
            }

	        if ($order_id) 
	        {
	    		 # Get order by id
	            $order = wc_get_order($order_id);

            	if($order)
            	{
            		# Get payment method
		            $payment_method = $order->get_payment_method();

		            $mrkv_mopay_payment_method = $order->get_meta('mrkv_mopay_payment_method');

		            # Check monopay method
		            if('morkva-monopay' == $payment_method)
		            {
		            	# Add metabox
		         		add_meta_box('mrkv_monopay_order', __('Plata by Mono (morkva)', 'morkva-monobank-extended'), array( $this, 'mrkv_monopay_add_plugin_meta_box' ), $screen, 'side', 'core');   
		            }

		            if ($order->get_meta('mrkv_mopay_accuiring_use_holds') == 'hold') 
		            {
				        add_meta_box('mrkv_mopay_hold_amount', __('Hold payment Plata by Mono', 'morkva-monobank-extended'), array($this, 'mrkv_mopay_holds'), $screen, 'side', 'core');
			        }
            	}
	    	}
	    }

	    public function mrkv_mopay_holds($post)
	    {
	    	if ( $post instanceof \WP_Post ) {
                $order_id = $post->ID;
            } elseif ( $post instanceof \WC_Order ) {
                $order_id = $post->get_id();
            } else {
                $order_id = false;
            }

			if (!$order_id) {
	            return;
	        }

	    	$order = wc_get_order($order_id);
	        if (!$order) {
	            return;
	        }

	        $order_status = $order->get_status();
	        if ($order_status == 'on-hold') 
	        {
	            $finalize_text = __('Finalize', 'morkva-monobank-extended');
		        $cancel_hold_text = __('Cancel hold', 'morkva-monobank-extended');
		        $enter_amount_text = __('Enter amount', 'morkva-monobank-extended');
		        $cancel_text = __('Cancel', 'morkva-monobank-extended');
		        $payment_amount = intval($order->get_meta('mrkv_mopay_accuiring_payment_amount'));
		        $payment_amount = sprintf('%.2f', $payment_amount / 100);

        		?>
		            <div id="hold_form_container">
		                <label for="mono_amount" class="label-on-top">
		                    <?php echo esc_html( $enter_amount_text ); ?>
		                </label>
		                <div class="col-sm">
		                    <div class="input-group">
		                        <input type="text" id="mono_amount" name="finalization_amount" required="required"
		                               value="<?php echo esc_html( $payment_amount ); ?>"/>
		                    </div>
		                </div>
		                <br/>
		                <div class="text-left">
		                    <a class="button button-danger" onclick="jQuery.ajax({
		                            url: '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>',
		                            type: 'POST',
		                            data: {
		                            	'action' : 'mrkv_mono_cancel_payment_hold',
		                                'order_id': '<?php echo esc_html( $post->ID ); ?>',
										'nonce': '<?php echo esc_js( wp_create_nonce( 'mrkv_mono_nonce' ) ); ?>'
		                            },
		                            success: function (response) {
		                                window.location.reload();
		                            },
		                        })"><?php echo esc_html( $cancel_hold_text ); ?></a>                                     

		                    <a class="button button-primary" onclick="jQuery.ajax({
		                            url: '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>',
		                            type: 'POST',
		                            data: {
		                            	'action' : 'mrkv_mono_final_payment_hold',
		                                'order_id': '<?php echo esc_html( $post->ID ); ?>',
		                                'finalization_amount': document.getElementById('mono_amount').value,
										'nonce': '<?php echo esc_js( wp_create_nonce( 'mrkv_mono_nonce' ) ); ?>'
		                            },
		                            success: function (response) {
		                                window.location.reload();
		                            },
		                        })"><?php echo esc_html( $finalize_text ); ?></a>
		                </div>
		            </div>
        		<?php
	        }
	        else
	        {
	        	echo esc_html__( 'Hold is not applied to the order.', 'morkva-monobank-extended' );
	        }
	    }

	    /**
	     * Add metabox content
	     * */
	    public function mrkv_monopay_add_plugin_meta_box($post)
	    {
	    	if ( $post instanceof \WP_Post ) {
                $order_id = $post->ID;
            } elseif ( $post instanceof \WC_Order ) {
                $order_id = $post->get_id();
            } else {
                $order_id = false;
            }

	        if ($order_id) 
	        {
	    		# Get order by id
	            $order = wc_get_order($order_id);

	            # Get payment method
	            $payment_method = $order->get_payment_method();

	            $mrkv_mopay_payment_method = $order->get_meta('mrkv_mopay_payment_method');

				do_action( 'mrkv_monobank_order_metabox_start', $order );

	            # Check monopay method
	            if('morkva-monopay' == $payment_method)
	            {
	            	# Get Acuiring status
            		$mrkv_mopay_payment_method = $order->get_meta('mrkv_mopay_payment_method');

	            	if('morkva-monopay' == $payment_method && $mrkv_mopay_payment_method == 'morkva-monopay')
	            	{
	            		# Get Acuiring status
	            		$mrkv_mopay_accuiring_status = $order->get_meta('mrkv_mopay_accuiring_status');

	            		# Get Acuiring reference
	            		$mrkv_mopay_accuiring_reference = $order->get_meta('mrkv_mopay_accuiring_reference');

	            		# Get Acuiring invoice id
	            		$mrkv_mopay_accuiring_invoice_id = $order->get_meta('mrkv_mopay_accuiring_invoice_id');

	            		# Get Acuiring error
	            		$mrkv_mopay_accuiring_failure_reason = $order->get_meta('mrkv_mopay_accuiring_failure_reason');

	            		?>
	            			<div class="monopay_metabox_line">
	            				<h4><?php echo esc_html__( 'Mono Acquiring', 'morkva-monobank-extended' ); ?></h4>
	            			</div>
	            		<?php
	            		if($mrkv_mopay_accuiring_status)
	            		{
	            			?>
	            			<div class="monopay_metabox_line">
	            				<b><?php echo esc_html__('Status', 'morkva-monobank-extended'); ?>:</b>
	            				<span>
	            					<?php 
	            						$order_status_text = '';

	            						switch($mrkv_mopay_accuiring_status)
	            						{
	            							case 'created':
	            								$order_status_text = __('Created', 'morkva-monobank-extended');
	            							break;
	            							case 'processing':
	            								$order_status_text = __('Processing', 'morkva-monobank-extended');
	            							break;
	            							case 'failure':
	            								$order_status_text = $mrkv_mopay_accuiring_failure_reason;
	            							break;
	            							case 'success':
	            								$order_status_text = __('Success', 'morkva-monobank-extended');
	            							break;
	            						}
	            						echo esc_html( $order_status_text );
	            					?>
	            				</span>
	            			</div>
	            			<?php
	            		}

	            		if($mrkv_mopay_accuiring_reference)
	            		{
	            			?>
	            				<div class="monopay_metabox_line">
	            					<b><?php echo esc_html__('Reference', 'morkva-monobank-extended'); ?>:</b>
	            					<span><?php echo esc_html( $mrkv_mopay_accuiring_reference ); ?></span>
	            				</div>
	            			<?php
	            		}

	            		if($mrkv_mopay_accuiring_invoice_id)
	            		{
	            			?>
	            				<div class="monopay_metabox_line">
	            					<b><?php echo esc_html__('Invoice ID', 'morkva-monobank-extended'); ?>:</b>
	            					<span><?php echo esc_html( $mrkv_mopay_accuiring_invoice_id ); ?></span>
	            				</div>
	            				<div style="margin-top: 15px;" class="monopay_metabox_line">
	            					<div class="mrkv_mono_accuiring-status-call button button-primary"><?php echo esc_html__('Checking the status of the order', 'morkva-monobank-extended'); ?></div>
	            					<svg style="display: none; position:absolute; right:0;" version="1.1" id="L9" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="30px" height="30px" x="0px" y="0px"
									  viewBox="0 0 100 100" enable-background="new 0 0 0 0" xml:space="preserve">
									    <path fill="#000" d="M73,50c0-12.7-10.3-23-23-23S27,37.3,27,50 M30.9,50c0-10.5,8.5-19.1,19.1-19.1S69.1,39.5,69.1,50">
									      <animateTransform 
									         attributeName="transform" 
									         attributeType="XML" 
									         type="rotate"
									         dur="1s" 
									         from="0 50 50"
									         to="360 50 50" 
									         repeatCount="indefinite" />
									  </path>
									</svg>
	            				</div>
	            				<script>
            					jQuery(window).on('load', function() {
	            					jQuery(document).on("click", ".mrkv_mono_accuiring-status-call", function(){
	            						var order_id = '<?php echo esc_attr( $order->get_id() ); ?>';

	            						if(order_id)
	            						{
	            							var data = {
	            								action: 'mrkv_mono_accuiring_status_check',
	            								order_id: order_id,
												nonce: '<?php echo esc_js( wp_create_nonce( 'mrkv_mono_nonce' ) ); ?>'
	            							};
	            							jQuery.ajax({
												url: '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>',
												type: 'POST',
												data: data,
												beforeSend: function( xhr ) {
									                jQuery('.monopay_metabox_line svg').show();
									            },
												success: function( data ) {
													location.reload();
												}
											});
	            						}
	            					});
            					});
            				</script>
	            			<?php
	            		}
	            	}
	            }

				do_action( 'mrkv_monobank_order_metabox_end', $order );
	    	}
	    }
	}
}