<?php
defined( 'ABSPATH' ) || exit;

/**
 * Woocommerce Copper payment gateway class
 */
class Copper_Payment_Gateway_WC_Gateway extends WC_Payment_Gateway {

	/**
	 * @var Copper_Payment_Gateway_WC_Gateway
	 */
	private static $_instance;
	private string $abi_array;
	private string $contract_address;
	private string $net;
	private string $gas_notice;
	private string $target_address;
	private string $api_id;
	private string $api_secret;
	private string $api_url;

	public function __construct() {
		$this->id                 = 'copper_payment_gateway_erc20';
		$this->title              = __( 'Pay with ERC20 peg63.546u Copper (Cu)', 'cu-copper-payment-gateway' );
		$this->method_title       = __( 'ERC20 peg63.546u Copper (Cu)', 'cu-copper-payment-gateway' );
		$this->order_button_text  = __( 'Pay with Copper', 'cu-copper-payment-gateway' );
		$this->method_description = __( 'Ethereum ERC20 Token peg63.546u Copper (Cu) payment gateway.', 'cu-copper-payment-gateway' );

		$this->supports = array(
			'products',
		);

		/**
		 * Initial setting and background setting interface
		 */
		$this->init_settings();
		$this->init_form_fields();

		// Use foreach to assign all settings to the object to facilitate subsequent calls.
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}


		$this->save_fields();
		$this->set_hooks();
	}

	/**
	 * Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function set_hooks(): void {
		/**
		 * Hooks
		 */
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );
		add_action( 'woocommerce_api_compete', [ $this, 'webhook' ] );
		add_action( 'admin_notices', [ $this, 'do_ssl_check' ] );
		add_action( 'woocommerce_thankyou', [ $this, 'thankyou_page' ], 5 );
		add_action( 'woocommerce_account_content', [ $this, 'account_ethereum_addresses' ], 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_payment_scripts' ], 20 );
	}

	/**
	 * Load JavaScript for payment at the front desk
	 */
	public function enqueue_payment_scripts(): void {
		wp_enqueue_style( 'copper_payment_gateway_style' );

		wp_enqueue_script( 'copper_payment_gateway_web3' );
		wp_enqueue_script( 'copper_payment_gateway_payment' );
	}

	/**
	 * Setup settings
	 */
	public function init_form_fields(): void {
		$main_net_url     = 'https://etherscan.io/address/0x36ed6a2556e1bad716e29747f9a1d4a790fa48aa';
		$main_net_link    = sprintf( wp_kses( __( 'In Production mode it usses Ethereum Main Network. You can see the token <a href="%s" target="_blank">contract here</a>.', 'cu-copper-payment-gateway' ), array( 'a' => array( 'href' => array() ) ) ), esc_url( $main_net_url ) );
		$ropsten_net_url  = 'https://ropsten.etherscan.io/address/0xe93B988735f39647F4c5Fca724E3CEe543B386A9';
		$ropsten_net_link = sprintf( wp_kses( __( 'In Test mode it usses Ethereum Ropsten Network. You can see the token <a href="%s" target="_blank">contract here</a>.', 'cu-copper-payment-gateway' ), array( 'a' => array( 'href' => array() ) ) ), esc_url( $ropsten_net_url ) );

		$information_description = $main_net_link . '<br>' . $ropsten_net_link;
		$uninstall_description   = wp_kses( __( '<b style="color:red">Be very carefull with this checkbox!</b>', 'cu-copper-payment-gateway' ), array( 'b' => array( 'style' => array() ) ) ) . ' ' . __( 'It will dlelete all saved configurations for this payment gateway. Also, it will delete all Ethereum Addresses bound to Accounts and TXs included in orders.', 'cu-copper-payment-gateway' );

		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __( 'Enable/Disable', 'cu-copper-payment-gateway' ),
				'label'   => __( 'Enable ERC20 peg63.546u Copper (Cu) Payment Gateway', 'cu-copper-payment-gateway' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'checkout__title' => array(
				'title'       => __( 'Apparance', 'cu-copper-payment-gateway' ),
				'type'        => 'title',
				'description' => __( 'Checkout and order', 'cu-copper-payment-gateway' ),
			),
			'title'           => array(
				'title'       => __( 'Title', 'cu-copper-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Title Shown on Checkout Page', 'cu-copper-payment-gateway' ),
				'default'     => __( 'Pay with ERC20 peg63.546u Copper (Cu)', 'cu-copper-payment-gateway' ),
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'       => __( 'Description', 'cu-copper-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Description  will be shown at Checkout page', 'cu-copper-payment-gateway' ),
				'default'     => __( 'Ethereum ERC20 Token peg63.546u Copper (Cu) payment gateway.', 'cu-copper-payment-gateway' ),
				'desc_tip'    => true,
			),
			'gas_notice'      => array(
				'title'       => __( 'Gas Notice', 'cu-copper-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Tell to the customer to set a high gas price for speed up transaction.', 'cu-copper-payment-gateway' ),
				'default'     => __( 'Set a High Gas Price to speed up your transaction.', 'cu-copper-payment-gateway' ),
				'desc_tip'    => true,
			),
			'gen_title'       => array(
				'title' => __( 'General', 'cu-copper-payment-gateway' ),
				'type'  => 'title',
			),
			'target_address'  => array(
				'title'       => __( 'Wallet Address', 'cu-copper-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Token will be transfered into this address', 'cu-copper-payment-gateway' ),
				'desc_tip'    => true,
			),
			'is_test_mode'    => array(
				'title'       => __( 'Test Mode', 'cu-copper-payment-gateway' ),
				'label'       => __( 'Enable', 'cu-copper-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => __( 'If checked will be used Ropsten network.', 'cu-copper-payment-gateway' ),
				'desc_tip'    => true,
			),
			'api_title'       => array(
				'title'       => __( 'API', 'cu-copper-payment-gateway' ),
				'type'        => 'title',
				'description' => __( 'infura.io API', 'cu-copper-payment-gateway' ),
			),
			'api_id'          => array(
				'title' => __( 'Project ID', 'cu-copper-payment-gateway' ),
				'type'  => 'text',
			),
			'api_secret'      => array(
				'title' => __( 'Project Secret', 'cu-copper-payment-gateway' ),
				'type'  => 'password',
			),
			'api_url'         => array(
				'title' => __( 'API URL', 'cu-copper-payment-gateway' ),
				'type'  => 'text',
			),
			'information'     => array(
				'title'       => __( 'Information', 'cu-copper-payment-gateway' ),
				'type'        => 'title',
				'description' => $information_description,
			),
			'uninstall'       => array(
				'title'       => __( 'Uninstall', 'cu-copper-payment-gateway' ),
				'label'       => __( 'Clean Data', 'cu-copper-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => $uninstall_description,
			)

		);
	}

	/**
	 * The next step on the user checkout page
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );
		/**
		 * Mark the order as unpaid.
		 */
		$order->add_order_note( __( 'Order created, wait for payment.', 'cu-copper-payment-gateway' ) );
		/**
		 * Set the order status to unpaid, and you can use needs_payments to monitor it later.
		 */
		$order->update_status( 'unpaid', __( 'Wait For Payment', 'cu-copper-payment-gateway' ) );
		/**
		 * Empty shopping cart
		 */
		WC()->cart->empty_cart();

		$customer = $order->get_user();

		// Set user secret password for Bound signature
		if ( $customer && get_user_meta( $customer->ID, 'copper_payment_gateway_eth_token', true ) === '' ) {
			update_user_meta( $customer->ID, 'copper_payment_gateway_eth_token', wp_generate_password( 6, false ) );
		}

		/**
		 * The payment is successful, enter the 'Thank You' page.
		 */
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Check whether SSL is used to ensure security.
	 */
	public function do_ssl_check(): void {
		if ( ( $this->enabled === "yes" ) && get_option( 'woocommerce_force_ssl_checkout' ) === "no" ) {
			echo "<div class=\"error\"><p>" . sprintf( __( '<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href="%s">forcing the checkout pages to be secured.</a>', 'cu-copper-payment-gateway' ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . "</p></div>";
		}
	}

	/**
	 * 'Thank You' page configuration
	 * The user needs to be reminded to pay here.
	 */
	public function thankyou_page( $order_id ): void {
		/**
		 * If no order_id is passed in, it returns.
		 */
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		/**
		 * Monitor whether the order needs to be paid
		 */
		if ( $order->needs_payment() ) {
			$shortcode = '[copper_payment_gateway_connect_addresses order-id="' . $order_id . '"]';
			echo do_shortcode( $shortcode );
		} else {
			include COPPER_PAYMENT_GATEWAY_ABSPATH . '/templates/order-payed.php';
		}

	}

	public function account_ethereum_addresses(): void {
		if ( is_edit_account_page() ) {
			echo do_shortcode( '[copper_payment_gateway_connect_addresses]' );
		}
	}

	public function save_fields(): void {
		if ( $_POST['_wp_http_referer'] !== '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=copper_payment_gateway_erc20' ) {
			return;
		}

		if ( $this->uninstall === 'yes' ) {
			copper_payment_gateway_uninstall();
			$this->settings['title']          = __( 'Pay with ERC20 peg63.546u Copper (Cu)', 'cu-copper-payment-gateway' );
			$this->settings['description']    = __( 'Ethereum ERC20 Token peg63.546u Copper (Cu) payment gateway.', 'cu-copper-payment-gateway' );
			$this->settings['gas_notice']     = __( 'Set a High Gas Price to speed up your transaction.', 'cu-copper-payment-gateway' );
			$this->settings['target_address'] = __( 'Token will be transfered into this address', 'cu-copper-payment-gateway' );
			$this->settings['is_test_mode']   = 'no';
			$this->settings['api_id']         = '';
			$this->settings['api_secret']     = '';
			$this->settings['api_url']        = '';
			$this->settings['uninstall']      = 'no';

			return;
		}

		if ( $this->is_test_mode === 'no' ) {
			/* ToDo To be changed*/
			$this->abi_array        = '[{"constant":true,"inputs":[],"name":"name","outputs":[{"name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"spender","type":"address"},{"name":"amount","type":"uint256"}],"name":"approve","outputs":[{"name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"totalSupply","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"sender","type":"address"},{"name":"recipient","type":"address"},{"name":"amount","type":"uint256"}],"name":"transferFrom","outputs":[{"name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"decimals","outputs":[{"name":"","type":"uint8"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"spender","type":"address"},{"name":"addedValue","type":"uint256"}],"name":"increaseAllowance","outputs":[{"name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"account","type":"address"},{"name":"amount","type":"uint256"}],"name":"mint","outputs":[{"name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[{"name":"account","type":"address"}],"name":"balanceOf","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"symbol","outputs":[{"name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"account","type":"address"}],"name":"addMinter","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[],"name":"renounceMinter","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"spender","type":"address"},{"name":"subtractedValue","type":"uint256"}],"name":"decreaseAllowance","outputs":[{"name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"recipient","type":"address"},{"name":"amount","type":"uint256"}],"name":"transfer","outputs":[{"name":"","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[{"name":"account","type":"address"}],"name":"isMinter","outputs":[{"name":"","type":"bool"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"owner","type":"address"},{"name":"spender","type":"address"}],"name":"allowance","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"inputs":[],"payable":false,"stateMutability":"nonpayable","type":"constructor"},{"anonymous":false,"inputs":[{"indexed":true,"name":"account","type":"address"}],"name":"MinterAdded","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"account","type":"address"}],"name":"MinterRemoved","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"from","type":"address"},{"indexed":true,"name":"to","type":"address"},{"indexed":false,"name":"value","type":"uint256"}],"name":"Transfer","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"owner","type":"address"},{"indexed":true,"name":"spender","type":"address"},{"indexed":false,"name":"value","type":"uint256"}],"name":"Approval","type":"event"}]';
			$this->net              = 'mainnet';
			$this->contract_address = '0x36ed6a2556e1bad716e29747f9a1d4a790fa48aa';
		} else {
			$this->abi_array        = '[{"inputs":[],"stateMutability":"nonpayable","type":"constructor"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"tokenOwner","type":"address"},{"indexed":true,"internalType":"address","name":"spender","type":"address"},{"indexed":false,"internalType":"uint256","name":"tokens","type":"uint256"}],"name":"Approval","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"_from","type":"address"},{"indexed":true,"internalType":"address","name":"_to","type":"address"}],"name":"OwnershipTransferred","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"from","type":"address"},{"indexed":true,"internalType":"address","name":"to","type":"address"},{"indexed":false,"internalType":"uint256","name":"tokens","type":"uint256"}],"name":"Transfer","type":"event"},{"inputs":[],"name":"_totalSupply","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"acceptOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"tokenOwner","type":"address"},{"internalType":"address","name":"spender","type":"address"}],"name":"allowance","outputs":[{"internalType":"uint256","name":"remaining","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"spender","type":"address"},{"internalType":"uint256","name":"tokens","type":"uint256"}],"name":"approve","outputs":[{"internalType":"bool","name":"success","type":"bool"}],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"spender","type":"address"},{"internalType":"uint256","name":"tokens","type":"uint256"},{"internalType":"bytes","name":"data","type":"bytes"}],"name":"approveAndCall","outputs":[{"internalType":"bool","name":"success","type":"bool"}],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"tokenOwner","type":"address"}],"name":"balanceOf","outputs":[{"internalType":"uint256","name":"balance","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"decimals","outputs":[{"internalType":"uint8","name":"","type":"uint8"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"name","outputs":[{"internalType":"string","name":"","type":"string"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"newOwner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"owner","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"uint256","name":"a","type":"uint256"},{"internalType":"uint256","name":"b","type":"uint256"}],"name":"safeAdd","outputs":[{"internalType":"uint256","name":"c","type":"uint256"}],"stateMutability":"pure","type":"function"},{"inputs":[{"internalType":"uint256","name":"a","type":"uint256"},{"internalType":"uint256","name":"b","type":"uint256"}],"name":"safeDiv","outputs":[{"internalType":"uint256","name":"c","type":"uint256"}],"stateMutability":"pure","type":"function"},{"inputs":[{"internalType":"uint256","name":"a","type":"uint256"},{"internalType":"uint256","name":"b","type":"uint256"}],"name":"safeMul","outputs":[{"internalType":"uint256","name":"c","type":"uint256"}],"stateMutability":"pure","type":"function"},{"inputs":[{"internalType":"uint256","name":"a","type":"uint256"},{"internalType":"uint256","name":"b","type":"uint256"}],"name":"safeSub","outputs":[{"internalType":"uint256","name":"c","type":"uint256"}],"stateMutability":"pure","type":"function"},{"inputs":[],"name":"symbol","outputs":[{"internalType":"string","name":"","type":"string"}],"stateMutability":"view","type":"function"},{"inputs":[],"name":"totalSupply","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},{"inputs":[{"internalType":"address","name":"to","type":"address"},{"internalType":"uint256","name":"tokens","type":"uint256"}],"name":"transfer","outputs":[{"internalType":"bool","name":"success","type":"bool"}],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"tokenAddress","type":"address"},{"internalType":"uint256","name":"tokens","type":"uint256"}],"name":"transferAnyERC20Token","outputs":[{"internalType":"bool","name":"success","type":"bool"}],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"from","type":"address"},{"internalType":"address","name":"to","type":"address"},{"internalType":"uint256","name":"tokens","type":"uint256"}],"name":"transferFrom","outputs":[{"internalType":"bool","name":"success","type":"bool"}],"stateMutability":"nonpayable","type":"function"},{"inputs":[{"internalType":"address","name":"_newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"stateMutability":"nonpayable","type":"function"}]';
			$this->net              = 'ropsten';
			$this->contract_address = '0xe93B988735f39647F4c5Fca724E3CEe543B386A9';
		}

		$options = [
			[ 'copper_payment_gateway_gas_notice', $this->gas_notice ],
			[ 'copper_payment_gateway_copper_target_address', $this->target_address ],
			[ 'copper_payment_gateway_copper_contract_address', $this->contract_address ],
			[ 'copper_payment_gateway_copper_abi_array', $this->abi_array ],
			[ 'copper_payment_gateway_ethereum_net', $this->net ],
			[ 'copper_payment_gateway_infura_api_id', $this->api_id ],
			[ 'copper_payment_gateway_infura_api_secret', $this->api_secret ],
			[ 'copper_payment_gateway_infura_api_url', $this->api_url ],
		];

		foreach ( $options as [$option_name, $value] ) {
			update_option( $option_name, $value );
		}
	}
}