<?php
defined( 'ABSPATH' ) || exit;
?>
<script>
	let copperPaymentGatewayAddresses = [];
	<?php
	$copper_payment_gateway_addresses = get_user_meta( get_current_user_id(), 'copper_payment_gateway_eth_addresses', true );
	$copper_payment_gateway_no_addresses_txt = __( 'You didn\'t add any address', 'cu-copper-payment-gateway' );
	if ( is_array( $copper_payment_gateway_addresses ) ) : ?>
	copperPaymentGatewayAddresses = <?php echo json_encode( $copper_payment_gateway_addresses ) ?>;
	<?php endif; ?>

	let copperPaymentGatewayHasButton = false;
	const copperPaymentGatewayData = {
		"security": "<?php echo wp_create_nonce( "copper_payment_gateway_security" ); ?>",
		"message": "<?php echo esc_attr(get_user_meta( get_current_user_id(), 'copper_payment_gateway_eth_token', true )) ?>",
		"addresses": copperPaymentGatewayAddresses,
		"ajaxurl": "/wp-admin/admin-ajax.php",
		"displayMessages": {
			"install-metamask": "<?php _e( 'Please Install MetaMask at First', 'cu-copper-payment-gateway' ) ?>",
			"no-addresses": "<?php echo esc_attr( $copper_payment_gateway_no_addresses_txt ) ?>",
			"connect-metamask-btn": "<?php _e( 'Connetc MetaMask', 'cu-copper-payment-gateway' ) ?>",
			"install-metamask-btn": "<?php _e( 'Install MetaMask', 'cu-copper-payment-gateway' ) ?>",
			"bound-account-btn": "<?php _e( 'Bound Account', 'cu-copper-payment-gateway' ) ?>",
			"pay-order-btn": "<?php _e( 'Pay Order', 'cu-copper-payment-gateway' ) ?>"
		}
	}

	<?php if ($order_id) :
	$order = wc_get_order( $order_id );
	?>
	copperPaymentGatewayHasButton = true;
	copperPaymentGatewayData.amount = <?php echo esc_html( $order->get_total() ) ?>;
	copperPaymentGatewayData.orderId = <?php echo esc_html( $order_id ) ?>;
	copperPaymentGatewayData.contractAddress = "<?php echo esc_attr(get_option( 'copper_payment_gateway_copper_contract_address' )) ?>";
	copperPaymentGatewayData.abiArray = <?php echo esc_attr(get_option( 'copper_payment_gateway_copper_abi_array' )) ?>;
	copperPaymentGatewayData.targetAddress = "<?php echo esc_attr(get_option( 'copper_payment_gateway_copper_target_address' )) ?>";


	jQuery(window).load(() => {
		if (window.ethereum) {
			copperPaymentGatewayShowCurrentAccount();
		}
		copperPaymentGatewaySetButtonText();

		if (window.ethereum) {
			window.ethereum.on('accountsChanged', function (accounts) {
				copperPaymentGatewayShowCurrentAccount();
				copperPaymentGatewaySetButtonText();
			});
		}
	});
	<?php endif; ?>
</script>
<div class="copper-payment-gateway" id="copper-payment-gateway">

	<?php if ( $order_id ) : ?>
        <h5 class="copper-payment-gateway__current-provider">
            <span class="copper-payment-gateway__current-provider-title"><?php _e( 'Current account', 'cu-copper-payment-gateway' ) ?>:</span>
            <span class="copper-payment-gateway__current-provider-account"
                  id="copper-payment-gateway__current-provider-account">...</span>
        </h5>

        <h6 class="cu-connected-addresses__gas-notice"><?php echo esc_html( get_option( 'copper_payment_gateway_gas_notice' ) ) ?></h6>

        <button class="copper-payment-gateway__pay-button" id="copper-payment-gateway__pay-button"
                onclick="copperPaymentGatewayPay(copperPaymentGatewayData)">
			<?php _e( 'Connect MetaMask', 'cu-copper-payment-gateway' ) ?>
        </button>
	<?php endif; ?>

    <div class="copper-payment-gateway__logs" id="copper-payment-gateway__logs"></div>

    <div class="cu-connected-addresses" id="cu-connected-addresses">
        <h3 class="cu-connected-addresses__title"><?php _e( 'Bonded addresses', 'cu-copper-payment-gateway' ) ?></h3>

		<?php if ( is_array( $copper_payment_gateway_addresses ) && count( $copper_payment_gateway_addresses ) > 0 ) : ?>
            <ul class="cu-connected-addresses__list">
				<?php foreach ( $copper_payment_gateway_addresses as $address ) : ?>
                    <li class="cu-connected-addresses__list" id="cu-address-<?php echo esc_html( $address ) ?>"
                        data-cu-address="<?php echo esc_html( $address ) ?>">
                        <span class="cu-connected-addresses__span"><?php echo esc_html( $address ) ?></span>
                        <button class="cu-connected-addresses__delete-button"
                                onclick="copperPaymentGatewayRemoveAddress('<?php echo esc_html( $address ) ?>',copperPaymentGatewayData)">
                            X
                        </button>
                    </li>
				<?php endforeach; ?>
            </ul>
		<?php else : ?>
            <div class="cu-connected-addresses__empty"><?php echo esc_html( $copper_payment_gateway_no_addresses_txt ) ?></div>
		<?php endif; ?>
    </div>

</div>