<?php
if ( ! defined( 'ABSPATH' ) ) exit;
# Check if class exist
if (!class_exists('MorkvaMonopaySubscribe'))
{
	/**
	 * Class for add subscribe options
	 */
	class MorkvaMonopaySubscribe
	{
		/**
		 * Constructor for add subscribe options
		 * */
		function __construct()
		{
			add_filter('mrkv_mono_plata_body_args', array($this, 'mrkv_plata_add_subscrive'), 1, 4);

			# WPS Subscription
			add_filter( 'wps_sfw_supported_payment_gateway_for_woocommerce', array( $this, 'mrkv_mono_wps_payment_gateway_for_woocommerce' ), 10, 2 );
			add_action( 'wps_sfw_other_payment_gateway_renewal', array( $this, 'mrkv_mono_wps_sfw_process_subscription_payment' ), 10, 3 );
			add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'mrkv_mono_wps_sfw_add_order_statuses_for_payment_complete' ), 10, 2 );
		}

		/**
		 * This function is add paypal payment gateway.
		 *
		 * @name mrkv_mono_wps_payment_gateway_for_woocommerce
		 * @param array  $supported_payment_method supported_payment_method.
		 * @param string $payment_method payment_method.
		 * @since    1.0.2
		 */
		public function mrkv_mono_wps_payment_gateway_for_woocommerce( $supported_payment_method, $payment_method ) {

			if ( 'morkva-monopay' == $payment_method ) {
				$supported_payment_method[] = $payment_method;
			}

			return $supported_payment_method;
		}

		/**
		 * This function is add subscription order status.
		 *
		 * @name mrkv_mono_wps_sfw_add_order_statuses_for_payment_complete
		 * @param array  $order_status order_status.
		 * @param object $order order.
		 * @since    1.0.2
		 */
		public function mrkv_mono_wps_sfw_add_order_statuses_for_payment_complete( $order_status, $order ) {
			if ( $order && is_object( $order ) ) {

				$order_id = $order->get_id();

				$payment_method = $order->get_payment_method();

				$wps_sfw_renewal_order = $order->get_meta('wps_sfw_renewal_order');
				if ( 'morkva-monopay' == $payment_method && 'yes' == $wps_sfw_renewal_order ) {
					$order_status[] = 'wps_renewal';
				}
			}
			return $order_status;
		}

		/**
		 * Add subsribe field
		 * @param array Post fields
		 * @var integer Order ID
		 * 
		 * @return array Post fields
		 * */
		public function mrkv_plata_add_subscrive($post_fields, $order_id, $endpoint, $payment_type)
		{
			# Get order
			$order = wc_get_order( $order_id );

			# Loop all items
			foreach ( $order->get_items() as $item_id => $item ) 
			{
				# Get product
				$product = $item->get_product();

				$_wps_sfw_product = get_post_meta( $product->get_id(), '_wps_sfw_product', true );

				# Check product type
				if( 'yes' == $_wps_sfw_product)
				{
					# Add field
					$post_fields['saveCardData'] = array(
						'saveCard' => true
					);

					break;
				}
			}

			# Return params
			return $post_fields;
		}

	    /**
		 * Process subscription payment.
		 *
		 * @name mrkv_mono_wps_sfw_process_subscription_payment.
		 * @param object $order order.
		 * @param int    $subscription_id subscription_id.
		 * @param string $payment_method payment_method.
		 * @since    1.0.2
		 */
		public function mrkv_mono_wps_sfw_process_subscription_payment( $order, $subscription_id, $payment_method ) {
			if ( $order && is_object( $order ) ) {
				$order_id = $order->get_id();
				$wps_sfw_renewal_order = $order->get_meta('wps_sfw_renewal_order');
				if($wps_sfw_renewal_order == 'yes' && 'morkva-monopay' == $payment_method)
				{
					if ( function_exists( 'wps_sfw_check_valid_subscription' ) && wps_sfw_check_valid_subscription( $subscription_id )) {
						$wc_gateways      = WC()->payment_gateways();
			    		$payment_gateways = $wc_gateways->get_available_payment_gateways();
			    		$mono_payment_gateway = $payment_gateways['morkva-monopay'];

			    		$wps_sfw_renewal_parent_order = $order->get_meta('wps_sfw_parent_order_id');

			    		$result = $mono_payment_gateway->mrkv_mono_wps_subscription_payment($order, $wps_sfw_renewal_parent_order);

			    		if($result)
				        {
				        	$new_status_name = wc_get_order_status_name('processing');
                    		$note_status = '[morkva plata] ' . __('Status changed to: ', 'morkva-monobank-extended') . $new_status_name;
				            # Avoid creating 100s "processing" orders
				            $order->update_status( 'processing', $note_status, true );
				            $order->save();
				            $order->payment_complete();
				        }
				        else
				        {
				        	$new_status_name = wc_get_order_status_name('failed');
                    		$note_status = '[morkva plata] ' . __('Status changed to: ', 'morkva-monobank-extended') . $new_status_name;
				            $order->update_status( 'failed', $note_status, true );
				            $order->save();
				        }
					}
				}
			}
		}
	}
}