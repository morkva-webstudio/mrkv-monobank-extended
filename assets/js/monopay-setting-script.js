jQuery(window).on('load', function() {
	/**
	 * Convert checkbox to radio
	 * */
	function check_radio_checkbox_image(){
		var black_checked =  jQuery('#woocommerce_morkva-monopay_monopay_image_type_black').is(':checked');
		var white_checked =  jQuery('#woocommerce_morkva-monopay_monopay_image_type_white').is(':checked');

		if(black_checked && white_checked){
			jQuery('#woocommerce_morkva-monopay_monopay_image_type_white').prop( "checked", false );
		}
	}

	// Convert
	check_radio_checkbox_image();

	jQuery('#woocommerce_morkva-monopay_monopay_image_type_black').change(function(){
		if(jQuery(this).is(':checked')){
			jQuery('#woocommerce_morkva-monopay_monopay_image_type_white').prop( "checked", false );
		}
		else{
			jQuery('#woocommerce_morkva-monopay_monopay_image_type_white').prop( "checked", true );
		}
	});
	jQuery('#woocommerce_morkva-monopay_monopay_image_type_white').change(function(){
		if(jQuery(this).is(':checked')){
			jQuery('#woocommerce_morkva-monopay_monopay_image_type_black').prop( "checked", false );
		}
		else{
			jQuery('#woocommerce_morkva-monopay_monopay_image_type_black').prop( "checked", true );
		}
	});

	if(jQuery('#woocommerce_morkva-monopay_mono_acquiring_title').length != 0){
		jQuery('.woocommerce-save-button').prop('disabled', false);
	}
	
});