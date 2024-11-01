<?php
defined( 'ABSPATH' ) || exit;

class Copper_Payment_Gateway_Payment {
	public string $erc20_method = "a9059cbb";
	public string $error;
	public string $tx;
	/**
	 * @var mixed
	 */
	public $block_hash = null;
	/**
	 * @var mixed
	 */
	public $transactions;
	/**
	 * @var mixed
	 */
	public $uncles;
	public int $transactions_minimum_count = 5;
	/**
	 * @var mixed
	 */
	public $order_timestamp;
	private bool $transaction_check_complete = false;

	private int $hash_counter = 0;
	private int $block_counter = 0;

	private bool $not_double_payment = false;

	public function send_infura_request( string $method, array $params = [] ) {
		$api_url = "https://" . get_option( 'copper_payment_gateway_ethereum_net' ) . ".infura.io/v3/" . get_option( 'copper_payment_gateway_infura_api_id' );
		$args    = array(
			'method'  => $method,
			'params'  => $params,
		);
		$response = wp_remote_get( $api_url, $args );
		$result = wp_remote_retrieve_response_code( $response );

		try {
			$res = json_decode( $result, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			return false;
		}
		if ( ! is_array( $res ) ) {
			return false;
		}
		$data = $res['result'];
		if ( $res['jsonrpc'] !== "2.0" || $res['id'] !== 1 || ! is_array( $data ) || empty( $data ) ) {
			return false;
		}

		return $data;
	}

	public function get_data_from_transfer_input( $input ) {
		if ( ! is_string( $input ) || strlen( $input ) !== 138 || substr( $input, 2, 8 ) !== $this->erc20_method ) {
			return false;
		}
		$receiver = '0x' . substr( $input, 34, 40 );
		$amount   = hexdec( substr( $input, 74 ) ) / 1E+18;

		return [
			'receiver' => $receiver,
			'amount'   => $amount,
		];
	}

	public function validate_transaction( $data, $order_id ) {
		if ( strtolower( $data['symbol'] ) !== strtolower( get_option( 'copper_payment_gateway_copper_contract_address' ) ) || strtolower( $data['receiver'] ) !== strtolower( get_option( 'copper_payment_gateway_copper_target_address' ) ) ) {
			return false;
		}
		$order = wc_get_order( $order_id );
		if ( (float) $data['amount'] !== (float) $order->get_total() ) {
			return false;
		}
		$buyer_addresses = get_user_meta( get_current_user_id(), 'copper_payment_gateway_eth_addresses', true );
		if ( ! in_array( $data['sender'], $buyer_addresses, true ) ) {
			return false;
		}

		return $order->get_date_created()->getOffsetTimestamp();
	}

	public function get_interval_seconds( $counter ): int {

		if ( $counter < 3 ) {
			return 10;
		} elseif ( $counter < 6 ) {
			return 30;
		} elseif ( $counter < 8 ) {
			return 100;
		} elseif ( $counter < 12 ) {
			return 300;
		} elseif ( $counter < 15 ) {
			return 1800;
		} elseif ( $counter < 17 ) {
			return 3600;
		} elseif ( $counter < 18 ) {
			return 21600;
		} else {
			return 43200;
		}
	}

	public function get_block_hash(): void {

		$this->hash_counter ++;
		$counter = $this->hash_counter;

		$transaction = $this->send_infura_request( 'eth_getTransactionByHash', [ $this->tx ] );
		if ( $transaction['blockHash'] !== null ) {
			$this->block_hash = $transaction['blockHash'];

			return;
		}

		if ( $counter >= 20 ) {
			return;
		}

		$interval = $this->get_interval_seconds( $counter );
		$loop     = React\EventLoop\Loop::get();
		$loop->addTimer( $interval, function() { $this->get_block_hash(); } );
		$loop->run();
	}

	public function check_block() {

		$this->block_counter ++;
		$counter = $this->block_counter;

		$block = $this->send_infura_request( 'eth_getBlockByHash', [ $this->block_hash, false ] );
		if ( ! is_array( $block ) || hexdec( $block['timestamp'] ) < $this->order_timestamp ) {
			return false;
		}
		$this->transactions = $block['transactions'];
		$this->uncles       = $block['uncles'];

		if ( ! $this->not_double_payment ) {

			$query = new WC_Order_Query( [
				'limit'     => - 1,
				'return'    => 'ids',
				'date_paid' => '>' . $block['timestamp'],
				'status'    => 'completed',
				'meta_key'  => 'copper_payment_gateway_tx'
			] );
			try {
				$order_ids = $query->get_orders();
				foreach ( $order_ids as $id ) {
					$order_tx = get_post_meta( $id, 'copper_payment_gateway_tx', true );
					if ( $order_tx === $this->tx ) {
						return false;
					}
				}
			} catch ( Exception $e ) {
			}

			$this->not_double_payment = true;
		}

		if ( is_array( $this->transactions ) && is_array( $this->uncles ) && count( $this->transactions ) >= $this->transactions_minimum_count && count( $this->transactions ) > count( $this->uncles ) ) {
			return true;
		}

		if ( $counter >= 20 ) {
			return false;
		}

		$interval = $this->get_interval_seconds( $counter );
		$loop     = React\EventLoop\Loop::get();
		$loop->addTimer( $interval, function() { $this->check_block(); } );
		$loop->run();
	}

	public function check_order( $tx, $order_id ): bool {

		if ( ! (int) $order_id || $order_id < 1 ) {
			$this->error = __( 'Incorrect Order ID', 'cu-copper-payment-gateway' );

			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! is_object( $order ) || $order->get_status() === 'completed' ) {
			$this->error = __( 'Incorrect Order', 'cu-copper-payment-gateway' );

			return false;
		}

		$tx_added_to_post = update_post_meta( $order_id, 'copper_payment_gateway_tx', $tx );
		if ( ! $tx_added_to_post ) {
			return false;
		}

		return true;
	}

	public function check_transaction( $tx, $order_id, $data = [] ): bool {
		/* Validate tx */
		if ( strlen( $tx ) !== 66 || strpos( $tx, '0x' ) !== 0 ) {
			$this->error = __( 'Incorrect TX', 'cu-copper-payment-gateway' );

			return false;
		}

		if ( ! $this->check_order( $tx, $order_id ) ) {
			return false;
		}

		/* Get transaction information */
		$transaction = $this->send_infura_request( 'eth_getTransactionByHash', [ $tx ] );
		if ( $transaction === false ) {
			return false;
		}
		$decoded_transfer_data = $this->get_data_from_transfer_input( $transaction['input'] );
		if ( $decoded_transfer_data === false ) {
			return false;
		}

		$transaction_data = [
			'receiver' => $decoded_transfer_data['receiver'],
			'amount'   => (float) $decoded_transfer_data['amount'],
			'symbol'   => $transaction['to'],
			'sender'   => $transaction['from'],
		];

		$this->order_timestamp = $this->validate_transaction( $transaction_data, $order_id );
		if ( $this->order_timestamp === false ) {
			return false;
		}

		$this->tx = $tx;
		if ( $transaction['blockHash'] !== null ) {
			$this->block_hash = $transaction['blockHash'];
		} else {
			$this->get_block_hash();
		}

		if ( $this->block_hash === null ) {
			return false;
		}

		if ( ! $this->check_block() ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order->payment_complete() ) {
			return false;
		}

		return true;
	}
}