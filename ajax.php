<?php
defined( 'ABSPATH' ) || exit;

require_once "lib/Keccak/Keccak.php";
require_once "lib/Elliptic/EC.php";
require_once "lib/Elliptic/Curves.php";

use Elliptic\EC;
use kornrunner\Keccak;

// Check if the message was signed with the same private key to which the public address belongs
/**
 * @throws Exception
 */
function copper_payment_gateway_pub_key_to_address( $pubkey ): string {
	return "0x" . substr( Keccak::hash( substr( hex2bin( $pubkey->encode( "hex" ) ), 1 ), 256 ), 24 );
}

function copper_payment_gateway_verify_signature( $message, $signature, $address ): bool {
	$msglen = strlen( $message );
	try {
		$hash = Keccak::hash( "\x19Ethereum Signed Message:\n{$msglen}{$message}", 256 );
	} catch ( Exception $e ) {
		return false;
	}
	$sign  = [
		"r" => substr( $signature, 2, 64 ),
		"s" => substr( $signature, 66, 64 )
	];
	$recid = ord( hex2bin( substr( $signature, 130, 2 ) ) ) - 27;
	if ( $recid != ( $recid & 1 ) ) {
		return false;
	}

	$ec = new EC( 'secp256k1' );
	try {
		$pubkey = $ec->recoverPubKey( $hash, $sign, $recid );
	} catch ( Exception $e ) {
		return false;
	}

	try {
		return $address == copper_payment_gateway_pub_key_to_address( $pubkey );
	} catch ( Exception $e ) {
		return false;
	}
}

add_action( 'wp_ajax_copper_payment_gateway_add_eth_address_to_account', 'copper_payment_gateway_add_eth_address_to_account' );
function copper_payment_gateway_add_eth_address_to_account() {
	if ( check_ajax_referer( 'copper_payment_gateway_security', 'security' ) !== 1 ) {
		$response = [
			"action" => 'copper_payment_gateway_add_eth_address_to_account',
			"done"   => false,
			"error"  => __( 'Weak security!', 'cu-copper-payment-gateway' ),
		];

		echo json_encode( $response );
		die;
	}

	$user_id        = get_current_user_id();
	$message        = get_user_meta( $user_id, 'copper_payment_gateway_eth_token', true );
	$sign           = sanitize_text_field( $_POST['sign'] );
	$sender_address = sanitize_text_field( $_POST['sender'] );
	if ( ! copper_payment_gateway_verify_signature( $message, $sign, $sender_address ) ) {
		$response = [
			"action" => 'copper_payment_gateway_add_eth_address_to_account',
			"done"   => false,
			"error"  => __( 'Incorrect signature!', 'cu-copper-payment-gateway' ),
		];

		echo json_encode( $response );
		die;
	}

	$user_addresses = get_user_meta( $user_id, 'copper_payment_gateway_eth_addresses', true );
	if ( ! is_array( $user_addresses ) ) {
		$user_addresses = [];
	}
	if ( in_array( $sender_address, $user_addresses ) ) {
		$response = [
			"action" => 'copper_payment_gateway_add_eth_address_to_account',
			"done"   => false,
			"error"  => __( 'Already added!', 'cu-copper-payment-gateway' ),
		];

		echo json_encode( $response );
		die;
	}
	$user_addresses[]       = $sender_address;
	$user_addresses_updated = update_user_meta( $user_id, 'copper_payment_gateway_eth_addresses', $user_addresses );
	if ( ! $user_addresses_updated ) {
		$response = [
			"action" => 'copper_payment_gateway_add_eth_address_to_account',
			"done"   => false,
			"error"  => __( 'Internal error!', 'cu-copper-payment-gateway' ),
		];

		echo json_encode( $response );
		die;
	}

	$response = [
		"action"  => 'copper_payment_gateway_add_eth_address_to_account',
		"done"    => true,
		"success" => __( 'Account added!', 'cu-copper-payment-gateway' ),
		"account" => $sender_address
	];

	echo json_encode( $response );
	die;
}

// add_action('init', function() {
// 	update_user_meta( 2, 'copper_payment_gateway_eth_addresses', '' );
// });

add_action( 'wp_ajax_copper_payment_gateway_remove_eth_address_from_account', 'copper_payment_gateway_copper_payment_gateway_remove_eth_address_from_account' );
function copper_payment_gateway_copper_payment_gateway_remove_eth_address_from_account() {
	if ( check_ajax_referer( 'copper_payment_gateway_security', 'security' ) !== 1 ) {
		$response = [
			"action" => 'copper_payment_gateway_remove_eth_address_from_account',
			"done"   => false,
			"error"  => __( 'Weak security!', 'cu-copper-payment-gateway' ),
		];

		echo json_encode( $response );
		die;
	}

	$user_id = get_current_user_id();
	$address = sanitize_text_field( $_POST['address'] );

	$user_addresses = get_user_meta( $user_id, 'copper_payment_gateway_eth_addresses', true );
	if ( ! is_array( $user_addresses ) || ! in_array( $address, $user_addresses ) ) {
		$response = [
			"action" => 'copper_payment_gateway_remove_eth_address_from_account',
			"done"   => false,
			"error"  => __( 'Didn\'t exist!', 'cu-copper-payment-gateway' ),
		];

		echo json_encode( $response );
		die;
	}

	$array_needle_index = array_search( $address, $user_addresses );
	array_splice($user_addresses, $array_needle_index, 1);
	$user_addresses_updated = update_user_meta( $user_id, 'copper_payment_gateway_eth_addresses', $user_addresses );
	if ( ! $user_addresses_updated ) {
		$response = [
			"action" => 'copper_payment_gateway_remove_eth_address_from_account',
			"done"   => false,
			"error"  => __( 'Internal error!', 'cu-copper-payment-gateway' ),
		];

		echo json_encode( $response );
		die;
	}

	$response = [
		"action"  => 'copper_payment_gateway_remove_eth_address_from_account',
		"done"    => true,
		"success" => __( 'Account removed!', 'cu-copper-payment-gateway' ),
		"account" => $address
	];

	echo json_encode( $response );
	die;
}

add_action( 'wp_ajax_copper_payment_gateway_check_transaction', 'copper_payment_gateway_copper_payment_gateway_check_transaction' );
function copper_payment_gateway_copper_payment_gateway_check_transaction() {
	if ( check_ajax_referer( 'copper_payment_gateway_security', 'security' ) !== 1 ) {
		$response = [
			"action" => 'copper_payment_gateway_check_transaction',
			"done"   => false,
			"error"  => __( 'Weak security!', 'cu-copper-payment-gateway' ),
		];

		echo json_encode( $response );
		die;
	}

	$order_id = sanitize_text_field( $_POST['order_id'] );
	$tx       = sanitize_text_field( $_POST['tx'] );

	$payment = new Copper_Payment_Gateway_Payment();
	if ( ! $payment->check_transaction( $tx, $order_id ) ) {
		$response = [
			"action" => 'copper_payment_gateway_check_transaction',
			"done"   => false,
			"error"  => __( 'Unknown error!', 'cu-copper-payment-gateway' ),
		];
		if ( $payment->error ) {
			$response["error"] = $payment->error;
		}

		echo json_encode( $response );
		die;
	}

	$response = [
		"action"  => 'copper_payment_gateway_check_transaction',
		"done"    => true,
		"success" => __( 'Order Payed!', 'cu-copper-payment-gateway' ),
	];

	echo json_encode( $response );
	die;
}