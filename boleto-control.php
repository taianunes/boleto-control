<?php
/**
 * Plugin Name: Boleto Control
 * Plugin URI: https://taianunes.com
 * Description: Simple Payment control using WooCommerce and WooCommerce-Boleto
 * Author: Taian Nunes
 * Author URI: https://taianunes.com
 * Version: 0.0.1
 * License: GPLv2 or later
 * Text Domain: boleto-control
 */

// Don't load directly
defined( 'WPINC' ) or die;

require_once 'src/class-bc-asaas.php';

class WC_Boleto_Control {

	private static $instance;

	public static function instance(){
		return self::$instance ? self::$instance : self::$instance = new self;
	}

	private function __construct(){
		// Load plugin text domain
		//add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		//autoload
		require 'vendor/autoload.php';


		// Checks with WooCommerce and WC Boleto are installed.
		if ( !class_exists( 'WC_Payment_Gateway' ) || !class_exists( 'WC_Boleto' ) ) {
			add_action( 'admin_notices', array( $this, 'plugins_missing_notice' ) );
			return false;
		}

		// frontend actions
		if (! is_admin() ) {
			add_action( 'template_redirect', array( $this, 'redirect_single_order' ) );
			add_action( 'template_redirect', array( $this, 'update_order_meta_exp_date' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'bc_add_frontend_sripts' ) );
			return false;
		}

		// admin actions
		add_action( 'current_screen',    array( $this, 'redirect_admin_group_order' ) );
		add_action( 'current_screen',    array( $this, 'redirect_admin_single_order' ) );
		add_action( 'admin_menu',        array( $this, 'add_menu_page' ) );
		add_action( 'admin_notices',     array( $this, 'action_single_order_notice' ) );
		add_action( 'admin_notices',     array( $this, 'action_group_order_notice' ) );

		// admin filters
		add_filter( 'manage_shop_order_posts_columns', 	array( $this, 'filter_manage_shop_order_posts_columns' ), 11, 1 );
		add_filter( 'post_date_column_time', 			array( $this, 'filter_format_products_time_column' ) );
	}

	public function add_menu_page() {
		$this->home_page_id = add_menu_page( 'Boleto Control', 'Boleto Control', 'edit_posts', 'bc-home', array( $this, 'render_home_page' ), 'dashicons-media-default' );
		// $this->payments_page_id = add_submenu_page( 'bc-home','Controle Pagamentos', 'Controle Pagamentos', 'manage_options', 'bc-payments', array( $this, 'render_page' ) );
		// $this->admin_page_id = add_submenu_page( 'bc-home','BC Admin', 'Admin', 'manage_options', 'bc-admin', array( $this, 'render_admin_page' ) );
		$this->settings_id = add_submenu_page( 'bc-home','Settings', 'Settings', 'manage_options', 'bc-settings', array( $this, 'render_page' ) );

	}

	/**
	 * Missing plugins fallback notice
	 *
	 * @return string
	 */
	public function plugins_missing_notice() {
		include_once 'inc/views/html-notice-plugins-missing.php';
	}

	/**
	 * Home Page View
	 *
	 * @return string
	 */
	public function render_home_page() {
		include_once 'inc/views/html-render-home-page.php';
	}

	/**
	 * Admin Page View
	 *
	 * @return string
	 */
	public function render_admin_page() {
		include_once 'inc/views/html-render-admin-page.php';
	}

	public function bc_add_frontend_sripts()
	{

	    if ( ! is_page( 'assinatura' ) ) {
			return;
		}

	    // Register the style like this for a plugin:
	    wp_register_style( 'bc-default-style', plugins_url( '/assets/verticaltimeline/css/default.css', __FILE__ ), array(), false, 'all' );
	    wp_register_style( 'bc-component-style', plugins_url( '/assets/verticaltimeline/css/component.css', __FILE__ ), array(), false, 'all'  );

		// Register custom js for plugin:
		wp_register_script( 'bc-vertical-js', plugins_url( '/assets/verticaltimeline/js/modernizr.custom.js', __FILE__ ), array( 'jquery' ) );

	    // enqueue custom style and scripts
	    wp_enqueue_style( 'bc-default-style' );
	    wp_enqueue_style( 'bc-component-style' );
	    wp_enqueue_script( 'bc-vertical-js' );
	}


	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-boleto' );

		load_textdomain( 'woocommerce-boleto', trailingslashit( WP_LANG_DIR ) . 'woocommerce-boleto/woocommerce-boleto-' . $locale . '.mo' );
		load_plugin_textdomain( 'woocommerce-boleto', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Removes undesired columns from Woocormmerce shop-page
	 *
	 * @param  array $columns WooCommerce shop-page columns
	 *
	 * @return array $columns Filtered columns
	 */
	public function filter_manage_shop_order_posts_columns($columns) {
		unset( $columns['order_notes'], $columns['order_items'], $columns['billing_address'], $columns['shipping_address'], $columns['customer_message'] );
		return $columns;
	}

	/**
	 * Change post-list date format for shop-order page
	 *
	 * @param  string $t_time 	WooCommerce Order date time
	 * @param  date   $post   	Shop-page Posts
	 * @return date 			Formatted date
	 */
	public function filter_format_products_time_column( $t_time) {
		global $post;

		if ( 'shop_order' !== $post->post_type ) {
			return $t_time;
		}
		return get_post_time( __( 'F, Y', 'woocommerce' ), $post );
	}

	//restrict the posts by the chosen post format
	public function action_change_post_meta_shop_page( $query ) {

		if (! is_admin()) {
			return false;
		}

		if ( ! 'admin.php' === $GLOBALS["pagenow"]) {
			return false;
		}

		if ( ! empty( $_GET['post_type'] ) && 'shop_order' !== $_GET['post_type'] ){
			return false;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		$user = wp_get_current_user();

		$meta = (array) $query->get( 'meta_query', array() );


		$meta[] = array(
			'key'   => '_billing_email',
			'value' => $user->user_email
		);

		$query->set( 'meta_query', $meta );

	}


	// /**
	//  * Checks if already exists an order for current month
	//  * @param  object $user WP_User for current user
	//  * @return boolean		True if exists
	//  */
	// public function check_existing_order($user){

	// 	$args = array(
	// 	 'post_type' 	=> 'shop_order',
	// 	 'post_status' 	=> 'any',
	// 	 'meta_key'		=> '_billing_email',
	// 	 'meta_value'	=> $user->user_email,
	// 	 'year'			=> date('Y'),
	// 	 'monthnum'  	=> date('n'),
	// 	 'orderby' 		=> 'date',
	// 	 'order' 		=> 'DESC'
	// 	 );

	// 	$order_exist =  new WP_Query( $args );
	// 	$order_post = $order_exist->post;
	// 	// var_dump($order_post->post_password);
	// 	// die;

	// 	return $order_post->post_password;
	// }

	public function redirect_admin_single_order( $screen ) {

		if ( ! $screen instanceof WP_Screen ) {
			return false;
		}

		if ( $this->home_page_id !== $screen->id  && ! is_page( 'assinatura' )) {
			return false;
		}

		if ( ! isset( $_POST['single_order'] )  && ! isset( $_POST['ab_single_order'] )) {
			return false;
		}

		//If email was not informed, current user will be used
		if ( isset( $_POST['user']['email'] )  && '' !== $_POST['user']['email'] ) {
			$args = array(
				'search'         => $_POST['user']['email'],
				'search_columns' => array( 'user_email' )
			);
			$user_query = new WP_User_Query( $args , $query_limit = 1);
			$get_user = $user_query->get_results();
			$user_data = get_userdata( $get_user[0]->ID );
		}
		else{
			$user_data = wp_get_current_user();
		}

		//Creates Single Order
		$order_response = $this->create_custom_order( $user_data );

		$url = add_query_arg(
			array(
				'page'          => 'bc-home',
				'order_created' => $order_response[0],
				'is_new'        => $order_response[1],
			),
			admin_url(  'admin.php' )
		);

		exit( wp_redirect( $url ) );
	}

	/**
	 * Creates single order for current user
	 * @return wp_redirect returns then redirects to url
	 */
	public function redirect_single_order( ) {


			if (! is_page( 'assinatura' ) ) {
				return false;
			}

			if ( ! isset( $_POST['ab_single_order'] )) {
				return false;
			}

			$user = wp_get_current_user();


		     // $this->create_asaas_customer( $user );
			$this->delete_asaas_customer( $user );


			// $user_list = $api_data->customer_delete( get_user_meta($user->ID,'_asass_customer_id',true), null );
			// $user_list = $api_data->customer_create( $dadosCliente );
			// $user_list = $api_data->get_all( 'customers' );
			// var_dump('1');
			// var_dump($api_data->get_all( 'customers' ));
			// var_dump($api_data->get_all( 'subscriptions' ));
			// var_dump($api_data->get_all( 'payments' ));
			// var_dump($api_data->get_all( 'cities' ));
			// var_dump('2');
			// var_dump($api_data->get_by_id( 'customers','cus_oYujbLqk0eE1' )); //subs
			// var_dump($api_data->get_by_id( 'subscriptions','sub_ybsTsYzDMk2C' )); //subs
			// var_dump($api_data->get_by_id( 'payments','pay_222668703398' )); //subs
			// var_dump($api_data->get_by_id( 'cities','15873' )); //subs
			// var_dump('3');
			// var_dump($api_data->get_by_customer( 'payments', 'cus_SZ8PSaNL7gvi' ));
			// var_dump($api_data->get_by_customer( 'subscriptions','cus_SZ8PSaNL7gvi' ));
			// var_dump('4');
			// var_dump($api_data->get_by_subscription( 'notifications' ));
			// var_dump('5');
			// var_dump($api_data->customer_get_by_email( 'tnunes.dev@gmail.com' ));

			// $user_list = $api_data->customer_get_by_email( 'tnunes.dev@gmail.com' );
			// var_dump($user_list);
			die;

			//Creates Single Order for current user
			//$order_response = $this->create_custom_order( $user );

			$url = add_query_arg(
				array(
					'order_created' => $order_response[0],
					'is_new'        => $order_response[1],
				)
			);

		return wp_redirect( $url );
	}

	/**
	 * If dont exists, creates customer via asaas api with wp_user info
	 *
	 * @param  Object $wp_user WP_User
	 *
	 * @return bool
	 */
	public function create_asaas_customer( $wp_user ) {

		$api_data = WC_Boleto_Control_Asaas::instance();

		//checks if user exists on Asaas billing
		if ( $api_data->get_by_email( $wp_user->user_email ) ) {
			return false;
		}

		$customer_data = array(
			'name' 			=> $wp_user->display_name,
			'email' 		=> $wp_user->user_email,
			'mobilePhone' 	=> '21 993475477',
			'cpfCnpj'	 	=> '11807030733',
			'postalCode' 	=> unserialize(get_user_meta($wp_user->ID,'cep_consultorio',true)),
			'address' 		=> unserialize(get_user_meta($wp_user->ID,'rua_consultorio',true)),
			'addressNumber' => '106',
			'complement' 	=> 'Apto 303',
			'province' 		=> 'Andarai',
			'city' 			=> '3550308'
		);

		//creates new customer
		$user_list = $api_data->create( 'customers', $customer_data );
		var_dump($user_list);

		//insert user_meta with some customer extra info
		update_user_meta( $wp_user->ID, '_asass_customer_id', $user_list->id );
		update_user_meta( $wp_user->ID, '_asass_customer_date', $user_list->dateCreated );

		return true;
	}

	/**
	 * If exists, delete customer via asaas api with wp_user info
	 *
	 * @param  Object $wp_user WP_User
	 *
	 * @return bool
	 */
	public function delete_asaas_customer( $wp_user ) {

		$api_data = WC_Boleto_Control_Asaas::instance();

		//checks if user exists on Asaas billing
		if ( ! $api_data->get_by_email( $wp_user->user_email ) ) {
			return false;
		}

		//if not have customer_id meta then return
		$cust_data = $api_data->get_by_email( $wp_user->user_email);

		if ( ! isset( $cust_data ) ) {
			return false;
		}

		//deletes existing customer
		$user_list = $api_data->delete_by_id( 'customers', $cust_data->id );

		//insert user_meta with some customer extra info
		delete_user_meta( $wp_user->ID, '_asass_customer_id' );
		delete_user_meta( $wp_user->ID, '_asass_customer_date' );

		return true;
	}

	/**
	 * [redirect_admin_group_order description]
	 * @param  [type] $screen [description]
	 * @return [type]         [description]
	 */
	public function redirect_admin_group_order( $screen ) {

		if ( ! $screen instanceof WP_Screen ) {
			return false;
		}

		if ( $this->home_page_id !== $screen->id ) {
			return false;
		}

		if ( ! isset( $_POST['bulk_order'] ) ) {
			return false;
		}

		// get user for listed roles
		$args = array(
			'role__in'       => array('contributor','editor')
		);
		$user_query = new WP_User_Query( $args );

		// Create Orders for Users Loop
		foreach ( $user_query->get_results() as $user ) {

			// $this->create_asaas_customer( $user );
			$this->delete_asaas_customer( $user );

			// $order_created = $this->create_custom_order( $user );
			if ( 'true' === $order_created[1] && 'user-disabled' !== $order_created[0] ) {
				$count_order+= 1;
			}

		}

		if ( ! isset($count_order) || $count_order < 1  ) {
			$count_order = 0;
		}

		$url = add_query_arg( array(
			'page' => 'bc-home',
			'count_order' => $count_order,
		), admin_url( 'admin.php' ) );

		exit( wp_redirect( $url ) );

	}

	/**
	 * Change Boleto expiration date
	 */
	public function update_order_meta_exp_date(){
	    if ( ! is_page( 'assinatura' ) ) {
			return;
		}

		if (! isset( $_POST['abepps_exp_date'] ) && empty( $_POST['abepps_exp_date'] ) ) {
			return;
		}

		if (! isset( $_POST['post_id'] ) && empty( $_POST['post_id'] ) ) {
			return;
		}

		$exp_date = sanitize_text_field( $_POST['abepps_exp_date'] );

		// make sure the date isn't empty and is in the format dd/mm/yyyy
		$valid_date = $this->date_validation( $exp_date );

		if (! $valid_date[0] ) {
			// Add data to URL
			$url = add_query_arg(
				array(
					'upd_exp' 	=> $valid_date[1],
				)
			);
			return wp_redirect( $url );
		}

		$post_id = $_POST['post_id'];

		// Gets ticket data.
		$boleto_data = get_post_meta( $post_id, 'wc_boleto_data', true );
		$boleto_data['data_vencimento'] = date( 'd/m/Y', strtotime($exp_date));

		// Update ticket data.
		update_post_meta( $post_id, 'wc_boleto_data', $boleto_data );

		// Gets order data.
		$order = new WC_Order( $post_id );

		// Add order note.
		$order->add_order_note( sprintf( __( 'Expiration date updated to: %s', 'woocommerce-boleto' ), $boleto_data['data_vencimento'] ) );

		// Send email notification.
		$this->email_notification( $order, $boleto_data['data_vencimento'] );

	}

	/**
	 * Send email for exp date change
	 */
	public function email_notification( $order, $expiration_date ) {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			$mailer = WC()->mailer();
		} else {
			global $woocommerce;
			$mailer = $woocommerce->mailer();
		}

		$subject = sprintf( __( 'New expiration date for the boleto your order %s', 'woocommerce-boleto' ), $order->get_order_number() );

		// Mail headers.
		$headers = array();
		$headers[] = "Content-Type: text/html\r\n";

		// Body message.
		$main_message = '<p>' . sprintf( __( 'The expiration date of your boleto was updated to: %s', 'woocommerce-boleto' ), '<code>' . $expiration_date . '</code>' ) . '</p>';
		$main_message .= '<p>' . sprintf( '<a class="button" href="%s" target="_blank">%s</a>', esc_url( wc_boleto_get_boleto_url( $order->order_key ) ), __( 'Pay the Ticket &rarr;', 'woocommerce-boleto' ) ) . '</p>';

		// Sets message template.
		$message = $mailer->wrap_message( __( 'New expiration date for your boleto', 'woocommerce-boleto' ), $main_message );

		// Send email.
		$mailer->send( $order->billing_email, $subject, $message, $headers, '' );
	}

	/**
	 * Validates informed date format
	 * @param  string  $date New Expire date for Boleto
	 * @return boolean       true if date is ok
	 */
	public function date_validation ( $date ) {

		if ( empty( $date ) || ! preg_match( '~\d{4,4}-\d{2,2}-\d{2,2}~', $date) ){
		    return array( false, 'invalid_date' );
		}

		//stores dates for comparisson
	    $input_date = strtotime( $date );
	    $today_str = date( 'Y-m-d' );
	    $today = strtotime( $today_str );

	    // return false case date is in past
		if ($today > $input_date) {
			$message = 'The date cannot be in the past. Today is ' . date('d/m/Y', strtotime( $today_str ) ) . ' and you entered ' . date('d/m/Y',$input_date);
			var_dump($message);
			return array( false, 'past_date' );
		}
		return array( true, 'success' );
	}

	/**
	 * create custom order based on $base_user prefs
	 * @param  object 	$base_user  WP_User for order user
	 * @return [type]             [description]
	 */
	public function create_custom_order($base_user){

		if (! $base_user instanceof WP_User) {
			return false;
		}

		// is user disabled?
		if ('1' === get_user_meta( $base_user->ID, 'ja_disable_user', true )) {
			return array('user-disabled','false');
		}

		//check if order exists for current month
		$args = array(
		 'post_type' 	=> 'shop_order',
		 'post_status' 	=> 'any',
		 'meta_key'		=> '_billing_email',
		 'meta_value'	=> $base_user->user_email,
		 'year'			=> date('Y'),
		 'monthnum'  	=> date('n'),
		 'orderby' 		=> 'date',
		 'order' 		=> 'DESC'
		 );

		$order_exist =  new WP_Query( $args );
		$order_post = $order_exist->post;

		if ($order_post instanceof WP_Post) {
			$this->asaas_api();
			return array(get_post_meta($order_post->ID,'_order_key',true),'false');
		}

		// get order example
		$origin_order = new WC_Order( '2370' );

		//create new order
		$order = wc_create_order();

		//new order add products
		foreach ( $origin_order->get_items() as $item ) {
			$productitem = apply_filters( 'woocommerce_order_item_product', $origin_order->get_product_from_item( $item ), $item );
			$order->add_product( get_product( $productitem->id), 1);
		}

		$gateway = WC_Payment_Gateways::instance();

		//get payment gateway
		$gateways = $gateway->get_available_payment_gateways();

		//set current pay method
		$order->set_payment_method(current($gateways));

		//set woocommerce-boleto meta data
		$this->generate_boleto_data( $order );

		// set order total
		//$order->set_total();

		//order address
		$address = array(
			'first_name' 		=> $base_user->display_name,
			'email'      		=> $base_user->user_email,
			// 'phone'      => $origin_order->billing_phone,
			// 'address_1'  => $origin_order->billing_address, //unserialize(get_user_meta($user->id,'rua_consultorio',true)),
			// 'city'       => $origin_order->billing_city,
			// 'state'      => $origin_order->billing_state,
			// 'postcode'   => $origin_order->billing_postcode,
			// 'country'    => 'BR'
		);

		//update new order address
		$order->set_address( $address, 'billing' );
		//var_dump($address);

		//update new order shipping
		$order->calculate_shipping();

		//update new order totals
		$order->calculate_totals();

		$order->update_status( 'on-hold', __( 'Awaiting boleto payment', 'woocommerce-boleto' ) );

		//create boleto and save token from API BoletoCloud(POST)
		//update_post_meta( $order->id, '_bc_token_boleto', $this->boletocloud_create_boleto( $order ) );

		//return boleto url key
		// return array(get_post_meta( $order->id, '_order_key', true ),'true');
		return array(get_post_meta( $order->id, '_order_key', true ),'true');
	}

	/**
	 * Generate ticket data.
	 *
	 * @param  object $order Order object.
	 */


	public function generate_boleto_data( $order ) {
		// Ticket data.
		$data                       = array();
		$data['nosso_numero']       = apply_filters( 'wcboleto_our_number', $order->id );
		$data['numero_documento']   = apply_filters( 'wcboleto_document_number', $order->id );
		$data['data_vencimento']    = date( 'd/m/Y', time() + ( absint( 5 * 86400)  ));
		$data['data_documento']     = date( 'd/m/Y' );
		$data['data_processamento'] = date( 'd/m/Y' );

		update_post_meta( $order->id, 'wc_boleto_data', $data );
	}

	/**
	 * Render notice message for a single action
	 * @return boolean
	 */
	public function action_single_order_notice() {
		$screen = get_current_screen();

		if ( ! $screen instanceof WP_Screen ) {
			return false;
		}

		if ( $this->home_page_id !== $screen->id ) {
			return false;
		}

		if ( ! isset( $_GET['is_new'] ) ) {
			return false;
		}

		// custom order create returns bad user
		if ( 'user-disabled' === $_GET['order_created']) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php printf( esc_html( _e( 'ERROR! Order NOT created, informed user is disabled!.', 'boleto-control'  ))); ?></p><br>
			</div>
			<?php
			return true;
		}

		if ( 'false' === $_GET['is_new']) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><strong><?php printf( esc_html( _e( 'ERROR! Duplicated Order Found for current month.', 'boleto-control'  ))); ?></strong></p>
				<a href="<?php echo esc_url( home_url( '/boleto/' . esc_attr( $_GET['order_created'] ) ) ); ?>"><?php echo _e( 'Check Boleto last created!', 'boleto-control'  ); ?></a><br>
			</div>
			<?php
			return true;
		}

		?>
		<div class="notice notice-success is-dismissible">
			<p><?php printf( esc_html( _e( 'Success! Order created.', 'boleto-control'  ))); ?></p>
			<a href="<?php echo esc_url( home_url( '/boleto/' . esc_attr( $_GET['order_created'] ) ) ); ?>"><?php echo _e( 'Check Boleto Created.', 'boleto-control'  ); ?></a>
		</div>
		<?php
	}

	/**
	 * Render notice message for group actions
	 * @return boolean
	 */
	public function action_group_order_notice( $screen ) {

		if ( ! $screen instanceof WP_Screen ) {
			return false;
		}

		if ( $this->home_page_id !== $screen->id ) {
			return false;
		}

		if ( ! isset( $_GET['count_order'] ) ) {
			return false;
		}

		if ( '0' === $_GET['count_order']) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><strong><?php printf( esc_html( _e( 'ERROR! No orders were created.', 'boleto-control'  ))); ?></strong></p>
			</div>
			<?php
			return true;
		}

		$count_order = esc_attr( $_GET['count_order'] );

		?>
		<div class="notice notice-success is-dismissible">
			<p><?php printf( esc_html( _n( 'Sucess! %d order created.', 'Sucess! %d orders created.', $count_order, 'boleto-control'  ) ), $count_order ); ?></p>
		</div>
		<?php
	}


}

add_action( 'plugins_loaded', array( 'WC_Boleto_Control', 'instance' ), 15 );