<?php
defined( 'ABSPATH' ) || exit;

function copper_payment_gateway_uninstall() {
	/**
	 * Delete Payment options
	 * */
	$options = [
		'copper_payment_gateway_gas_notice',
		'copper_payment_gateway_copper_target_address',
		'copper_payment_gateway_copper_contract_address',
		'copper_payment_gateway_copper_abi_array',
		'copper_payment_gateway_ethereum_net',
		'copper_payment_gateway_infura_api_id',
		'copper_payment_gateway_infura_api_secret',
		'copper_payment_gateway_infura_api_url',
	];

	foreach ( $options as $option_name ) {
		delete_option( $option_name );
	}

	/**
	 * Delete User Metas
	 * */
	$args = array(
		'meta_key' => 'copper_payment_gateway_eth_addresses',
		'fields'   => 'ids'
	);

	$user_ids = get_users( $args );
	foreach ( $user_ids as $user ) {
		$user_id = (int) $user;
		delete_user_meta( $user_id, 'copper_payment_gateway_eth_addresses' );
	}

	/**
	 * Delete Order metadata
	 * */
	$query = new WC_Order_Query( [
		'limit'    => - 1,
		'return'   => 'ids',
		'meta_key' => 'copper_payment_gateway_tx'
	] );
	try {
		$order_ids = $query->get_orders();
		foreach ( $order_ids as $id ) {
			delete_post_meta( 73, 'copper_payment_gateway_tx' );
		}
	} catch ( Exception $e ) {
	}
}