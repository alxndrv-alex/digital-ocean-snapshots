<?php

	// For debug in browser console
	include 'FirePHPCore/fb.php';

	class DO_Volume_Backup {

		/* CONSTANTS SECTION
		 *
		 * A group of constants containing REST API paths and other important information
		 * */

		// API

		const API_PATH = 'https://api.digitalocean.com';

		const API_VOLUME = '/v2/volumes?name=%VOL_NAME%&region=%REGION%';
		const API_VOLUME_SNAPSHOTS = '/v2/volumes/%ID%/snapshots';
		const API_SNAPSHOT = '/v2/snapshots/';

		// LOGGING

		const LOG_PATH = './logs/errors.log';
		const LOG_ERROR_FORMAT = '[%s] ERROR %s' . "\n";
		const LOG_ERROR_DATE_FORMAT = 'n/j/Y h:i:s';

		const REQUIRED_ARGS = [
			'secret',
			'vol_name',
			'vol_region'
		];

		// Errors

		const ERRORS = [
			'#01' => 'The variable passed to the class is not a parameters array',
			'#02' => 'At least one of required parameters hasn\'t been passed to the class. Please check your parameters array',
			'#03' => 'No data returned by get_volume_by_name request. Please check the data and query',
			'#04' => 'No data returned by get_volume_snapshots request. Please check the data and query',
			'#05' => 'We could not create a snapshot. Please check passed parameters and curl logs'
		];

		/* VARIABLES SECTION
		 *
		 * A group of variables passed by caller script to class constructor
		 * */

		protected $secret = '';
		protected $vol_name = '';
		protected $vol_region = '';
		protected $total_snapshots = 5;
		protected $snapshot_name = '%VOL_NAME%-daily-%DATE_TIME%';

		// Error holder
		public $error = '';

		// Result holder
		public $success = true;

		public function __construct( $params = array() ) {

			// Checking if an array passed
			if( !is_array( $params )) :
				$this->set_error('#01');
				return 0;
			endif;

			// Making sure all required parameters are in place
			foreach ( $this::REQUIRED_ARGS as $arg ) :
					if ( !isset( $params[ $arg ] ) || empty( $params[ $arg ] ) ) :
						$this->set_error('#02');
						return 0;
					endif;
			endforeach;

			// Setting up parameters globally
			foreach ( $params as $k => $v ):
				$this->$k = $v;
			endforeach;

			// Begin the process
			$this->init();

			return $this->success;
		}

		/**
		 * @return int
		 *
		 *  Handles class methods execution in order to go through the process properly
		 */
		protected function init() {

			// Getting volume ID by name
			if( !$vol_id = $this->get_volume_id_by_name() ) return 0;

			// Getting a list of existing snapshots for the volume
			$list = $this->get_volume_snapshots( $vol_id );

			// If there are more than $total_snapshots snapshots, remove oldest ones to make total number of snapshots
			// after taking current snapshot to match $total_snapshots
			if ( count( $list ) > $this->total_snapshots ) $this->delete_extra_snapshots( $list );

			// Taking a new snapshot
			$this->create_snapshot( $vol_id );
		}

		/**
		 * @return int
		 *
		 * Retrieves volume ID by it's name passed to the class
		 */
		protected function get_volume_id_by_name() {

			$url = $this::API_PATH . htmlspecialchars( $this::API_VOLUME );
			$url = str_replace( [ '%VOL_NAME%', '%REGION%' ], [ $this->vol_name, $this->vol_region ], $url );
			$vol = json_decode( $this->curl( $url, 'GET' ) );

			if ( 0 == $vol->meta->total ) :
				$this->set_error( '#03');
				return 0;
			endif;

			return $vol->volumes[0]->id;
		}

		/**
		 * @param null $vol_id
		 *
		 * @return int
		 *
		 * Retrieves a list of volume snapshots sorted chronologically by volume ID
		 */
		protected function get_volume_snapshots( $vol_id = null ) {

			$url = $this::API_PATH . $this::API_VOLUME_SNAPSHOTS;
			$url = str_replace( '%ID%', $vol_id, $url );

			$data = json_decode( $this->curl( $url, 'GET' ) );

			if ( !$data ) :
				$this->set_error( '#04');
				return 0;
			endif;

			$list = $data->snapshots;

			usort( $list, function( $a, $b ) { return ( strtotime( $a->created_at ) - strtotime( $b->created_at )
			); } );

			return $list;
		}

		/**
		 * @param null $list
		 *
		 * @return bool|int
		 *
		 *  Deletes oldest volume snapshots to make total snapshot amount match defined total
		 */
		protected function delete_extra_snapshots( $list = null ) {

			$diff = count( $list ) - $this->total_snapshots;

			if ( $diff < 1 ) return 0;

			$url = $this::API_PATH . $this::API_SNAPSHOT;

			for( $i = 0; $i <= $diff; $i++ ) :

				$this->curl( $url . $list[ $i ]->id, 'DELETE' );
			endfor;

			return true;
		}

		/**
		 * @param null $vol_id
		 *
		 * @return int
		 *
		 *  Creates a new volume snapshot
		 */
		protected function create_snapshot( $vol_id = null ) {

			$url = $this::API_PATH . $this::API_VOLUME_SNAPSHOTS;
			$url = str_replace( '%ID%', $vol_id, $url );

			$name = str_replace(
				[ '%VOL_NAME%', '%DATE_TIME%' ],
				[ $this->vol_name, date( $this::LOG_ERROR_DATE_FORMAT ) ],
				$this->snapshot_name
			);

			$fields = '{"name": "' . $name . '"}';

			$create = json_decode( $this->curl( $url, 'POST', $fields ) );

			if ( !$create ) :
				$this->set_error( '#05');
				return 0;
			endif;

			return 1;
		}

		/**
		 * @param string $url
		 * @param string $method
		 * @param string $fields
		 *
		 * @return bool|int|string
		 *
		 *  Executes curl queries to the API
		 */
		protected function curl( $url = '', $method = '', $fields = '' ) {

			$req = curl_init();

			curl_setopt( $req, CURLOPT_URL, $url );
			curl_setopt( $req, CURLOPT_CUSTOMREQUEST, $method );
			curl_setopt( $req, CURLOPT_RETURNTRANSFER, 1 );

			$headers = [
				'Content-type: application/json',
				'Authorization: Bearer ' . $this->secret
			];

			if ( !empty( $fields ) ) curl_setopt( $req, CURLOPT_POSTFIELDS, $fields );

			curl_setopt( $req, CURLOPT_HTTPHEADER, $headers );

			$data = curl_exec($req);

			if ( curl_errno( $req ) ) :
				$this->set_error('CURL: ', curl_error( $req ) );
				return 0;
			endif;

			curl_close( $req );

			return $data;
		}

		/**
		 * @param string $code
		 * @param null   $custom_msg
		 *
		 * @return int
		 *
		 * Sets process status and latest error code and message to class properties
		 */
		protected function set_error( $code = '', $custom_msg = null ) {

			$date = date( $this::LOG_ERROR_DATE_FORMAT );
			$error = $code . ' : ' . ( $custom_msg ? $custom_msg : $this::ERRORS[$code] );

			$message = sprintf( $this::LOG_ERROR_FORMAT, $date, $error );

			$this->error = $message;
			$this->success = false;

			error_log( $message, 3, $this::LOG_PATH);

			return 1;
		}
	}