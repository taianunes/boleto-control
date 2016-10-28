<?php
// Don't load directly
defined( 'WPINC' ) or die;

class WC_Boleto_Control_Asaas {

	/**
	 * Static Singleton Holder
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Static Singleton Factory Method
	 *
	 * @return self
	 */
	public static function instance() {
		return self::$instance ? self::$instance : self::$instance = new self;
	}

	/**
	 * API varibles stored in a single Object
	 *
	 * @var array $api {
	 *     @type string     $key         License key for the API (PUE)
	 *     @type string     $version     Which version of we are dealing with
	 *     @type string     $domain      Domain in which the API lies
	 *     @type string     $path        Path of the API on the domain above
	 * }
	 */
	public $api = array(
		'key' 		=> '6f1beb274b8b85e613d66a88109d7b6d72dee679d50d62c46c67c6a356ed4445',
		'version' 	=> 'v2',
		'domain' 	=> 'https://homolog.asaas.com/',
		'path'		=> 'api/',
	);

	/**
	 * Constructor!
	 */
	private function __construct() {

		//turns api array into object
		$this->api = (object) $this->api;
	}

	/**
	 * Builds an endpoint URL
	 *
	 * @param string $endpoint  Endpoint for the Event Aggregator service
	 * @param array  $data      Parameters to add to the URL
	 *
	 * @return string|WP_Error
	 */
	public function build_url( $endpoint, $data = array() ) {

		return "{$this->api->domain}{$this->api->path}{$this->api->version}/{$endpoint}";
	}

	/**
	 * Performs a GET request against the Event Aggregator service
	 *
	 * @param string $endpoint   Endpoint for the Event Aggregator service
	 * @param array  $data       Parameters to send to the endpoint
	 *
	 * @return stdClass|WP_Error
	 */
	public function get( $endpoint, $data = array() ) {
		$url = $this->build_url( $endpoint, $data );

		// If we have an WP_Error we return it here
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		$headers = array(
			'access_token'  => $this->api->key,
		);

		$args = array(
			'timeout' 	=> 60,
			'limit' 	=> '50',
			'headers' 	=> $headers
		);

		//get api first response
		$response = wp_remote_get( esc_url_raw( $url ), $args );

		if ( is_wp_error( $response ) ) {
			if ( isset( $response->errors['http_request_failed'] ) ) {
				$response->errors['http_request_failed'][0] = __( 'Connection timed out while transferring the feed.', 'boleto-control' );
			}
			return $response;
		}

		// if the response is not an image, let's json decode the body
		$response = json_decode( wp_remote_retrieve_body( $response ) );

		// When having data, return it already
		// @todo consider pagination
		if ( ! empty( $response->data ) ) {
			return $response->data;
		}

		return $response;
	}

	/**
	 * Performs a POST request against the Event Aggregator service
	 *
	 * @param string $endpoint   Endpoint for the Event Aggregator service
	 * @param array  $data       Parameters to send to the endpoint
	 *
	 * @return stdClass|WP_Error
	 */
	public function post( $endpoint, $data = array() ) {
		$url = $this->build_url( $endpoint );

		// If we have an WP_Error we return it here
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		if ( empty( $data['body'] ) ) {
			$args = array( 'body' => json_encode( $data ) );
		} else {
			$args = $data;
		}

		$args['headers'] = array(
			'Content-Type' => 'application/json',
			'access_token' => $this->api->key,
		);

		// var_dump( $url, $args );

		$response = wp_remote_post( esc_url_raw( $url ), $args );

		// var_dump($response);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		// When having data, return it already
		// @todo consider pagination
		if ( ! empty( $response->data ) ) {
			return $response->data;
		}

		return $response;
	}

	public function delete( $endpoint, $data = array() ) {
		$url = $this->build_url( $endpoint );

		// If we have an WP_Error we return it here
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		if ( empty( $data['body'] ) ) {
			$args = array( 'body' => json_encode( $data ) );
		} else {
			$args = $data;
		}

		$args['method'] = 'DELETE';
		$args['headers'] = array(
			'Content-Type' 	=> 'application/json',
			'access_token' 	=> $this->api->key,
		);

		$response = wp_remote_request( esc_url_raw( $url ), $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		// When having data, return it already
		// @todo consider pagination
		if ( ! empty( $response->data ) ) {
			return $response->data;
		}

		return $response;
	}

	/**
	 * Returns a list of asaas object for informed endpoint
	 *
	 * @param  string $endpoint String for API endpoint
	 *
	 * @return stdClass|WP_Error
	 */
	public function get_all( $endpoint ) {
		return $this->get( $endpoint, null );
	}

	/**
	 * Returns an asaas object for informed id
	 *
	 * @param  string $endpoint String for API endpoint
	 * @param  string $obj_id   Asass object id
	 *
	 * @return stdClass|WP_Error
	 */
	public function get_by_id( $endpoint, $obj_id ) {
		return $this->get( $endpoint . '/' . $obj_id, null );
	}

	/**
	 * Returns a list of <endpoint> objets from specific  customers
	 * Possible Endpoints: subscriptions, payments, notifications
	 *
	 * @param  string $endpoint String for API endpoint
	 * @param  string $obj_id   Asass object id
	 *
	 * @return stdClass|WP_Error
	 */
	public function get_by_customer( $endpoint, $obj_id ) {
		return $this->get( 'customers/' . $obj_id . '/' . $endpoint, null );
	}

	/**
	 * Returns a list of <endpoint> objets from specific subscription
	 * Possible Endpoints: payments, notifications
	 *
	 * @param  string $endpoint String for API endpoint
	 * @param  string $obj_id   Asass object id
	 *
	 * @return stdClass|WP_Error
	 */
	public function get_by_subscription( $endpoint, $obj_id ) {
		return $this->get( 'subscriptions/' . $obj_id  . '/' . $endpoint, null );
	}

	/**
	 * Returns a list of payments objets from specific installment
	 *
	 * @param  string $endpoint String for API endpoint
	 * @param  string $obj_id   Asass object id
	 *
	 * @return stdClass|WP_Error
	 */
	public function get_by_installment( $obj_id ) {
		return $this->get( 'payments?installment=' . $obj_id . '/', null );
	}

	/**
	 * Returns an object of customer by email
	 *
	 * @param string $email Email from customer
	 *
	 * @return stdClass|WP_Error
	 */
	public function get_by_email( $email ) {
		$customers = $this->get_all( 'customers' );

		foreach ( $customers as $data ) {
			if ( ! empty($data->customer->email) && $data->customer->email === $email ) {
				return $data->customer;
			}
		}
		return false;
	}

	/**
	 * Create new entity
	 *
	 * @param  string $endpoint String for API endpoint
	 * @param  array  $data  	Entity Data
	 *
	 * @return stdClass|WP_Error
	 */
	public function create( $endpoint, $data = array() ) {
		return $this->post(  $endpoint, $data );
	}

	/**
	 * Update entity by id
	 *
	 * @param  string $endpoint String for API endpoint
	 * @param  string $obj_id   Asass object id
	 * @param  array  $data  	Entity Data
	 *
	 * @return stdClass|WP_Error
	 */
	public function update_by_id( $endpoint, $obj_id , $data = array() ) {
		return $this->post( $endpoint . '/' . $obj_id, $data );
	}

	/**
	 * Delete entity
	 *
	 * @param  string $endpoint String for API endpoint
	 * @param  string $obj_id   Asass object id
	 *
	 * @return stdClass|WP_Error
	 */
	public function delete_by_id( $endpoint, $obj_id ) {
		return $this->delete( $endpoint . '/' . $obj_id, null);
	}

}