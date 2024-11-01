<?php
defined( 'ABSPATH' ) || exit;
/**
 * Custom Logs
 */
function copper_payment_gateway_log( $log_msg, $is_error = false ) {
	$log_message = "[" . date( 'Y-m-d H:i:s' ) . "] ";
	if ( $is_error ) {
		$log_message .= "!!!ERROR!!! ";
	}
	$log_message .= $log_msg . "\n";

	$log_dir = __DIR__ . "/log";
	if ( ! file_exists( $log_dir ) && ! mkdir( $log_dir, 0777, true ) && ! is_dir( $log_dir ) ) {
		throw new \RuntimeException( sprintf( 'Directory "%s" was not created', $log_dir ) );
	}
	$log_file_name = $log_dir . '/' . date( 'Y-m-d' ) . '.log';
	file_put_contents( $log_file_name, $log_message, FILE_APPEND );
}

function copper_payment_gateway_log_dump( $log_msg, $title = false, $is_error = false ) {
	if ( $title ) {
		copper_payment_gateway_log( $title );
	}
	ob_start();
	var_dump( $log_msg );
	$log_dump = ob_get_clean();
	copper_payment_gateway_log( $log_dump, $is_error );
}

function copper_payment_gateway_log_export( $log_msg, $title = false, $is_error = false ) {
	if ( $title ) {
		copper_payment_gateway_log( $title );
	}
	ob_start();
	var_export( $log_msg );
	$log_dump = ob_get_clean();
	copper_payment_gateway_log( $log_dump, $is_error );
}