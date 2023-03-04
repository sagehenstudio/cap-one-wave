<?php
/**
 * Plugin Name: Cap One to Wave
 * Description: This plugin helps enter Capital One charges into Wave Accounting transactions, saving on data entry time & cost
 * Version: 1.0
 * Author: Sagehen Studio
 * Text Domain: cap-one-wave
 * Domain path: /lang/
 * 
 * Cap One to Wave
 * Copyright: (c) 2022 Sagehen Studio
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Thank you for using Cap One to Wave!
 * If this plugin helped, please support my work with a donut
 *
 * Donate at https://paypal.me/SagehenStudio
 *
 */
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

if ( ! class_exists( 'Cap_One_Wave' ) ) :

	class Cap_One_Wave {

		/**
		 * Single instance of the Cap_One_Wave class
		 *
		 * @var Cap_One_Wave
		 */
		protected static $_instance = null;

		/**
		 *
		 * Instantiator
		 *
		 */
		public static function instance() {

			if ( ! isset( self::$_instance ) && ! ( self::$_instance instanceof Cap_One_Wave ) ) {
				self::$_instance = new Cap_One_Wave;
			}
			return self::$_instance;

		}

		/**
		 *
		 * Constructor
		 *
		 */
		public function __construct() {

			if ( is_admin() ) {

				// add link to Cap One Wave settings from WP plugins page
				add_filter( 'plugin_action_links_cap-one-wave/cap-one-wave.php', array( $this, 'plugins_settings_link' ) );

				// add link to Cap One Wave settings from admin menu
				add_action( 'admin_menu', array( $this, 'settings_link' ) );

				// register settings options
				add_action( 'admin_init', array( $this, 'register_option' ) );
				
				// give user feedback about settings
				add_action( 'admin_notices', array( $this, 'admin_notices' ) );

			}

			add_filter( 'wpwhpro/run/actions/custom_action/return_args', array( $this, 'cap_one_wave_webhook' ), 10, 3 );

			// for the alternate WP post creation method - could leave a helpful breadcrumb trail
			// add_action( 'transition_post_status', array( $this, 'capital_one_to_wave' ), 10, 3 );

		}

		/**
		 * Add settings link to WP plugin listing
		 *
		 * @param $links
		 * @return array
		 */
		public function plugins_settings_link( $links ) {

			$settings = sprintf( '<a href="%s" title="%s">%s</a>', admin_url( 'options-general.php?page=cap-one-wave-settings' ) , __( 'Go to the settings page', 'cap-one-wave' ) , __( 'Settings', 'cap-one-wave' ) );
			array_unshift( $links, $settings );
			return $links;

		}

		/**
		 * Get current plugin settings
		 *
		 * @param void
		 * @return array
		 */		
		public function get_settings() {

			$settings = array();
			$cow_settings = get_option( 'cow_settings', $settings );

			$settings['token'] = isset( $cow_settings['token'] ) ? sanitize_text_field( $cow_settings['token'] ) : 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
			$settings['biz_id'] = isset( $cow_settings['biz_id'] ) ? sanitize_text_field( $cow_settings['biz_id'] ) : 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
			$settings['liability'] = isset( $cow_settings['liability'] ) ? $cow_settings['liability'] : 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
			$settings['expense'] = isset( $cow_settings['expense'] ) ? $cow_settings['expense'] : 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
			$settings['headers'] = array(
				'Authorization' => 'Bearer ' . $settings['token'],
				'Content-Type' => 'application/json',
			);

			if ( empty( $cow_settings ) ) {
				update_option( 'cow_settings', $settings );
			}

			return $settings;

		}

		/**
		 * Add settings to the WP Admin menu (under Settings)
		 *
		 * @param void
		 * @return void
		 */        
		public function settings_link() {

			add_options_page( esc_html__( 'Cap One Wave', 'cap-one-wave' ), esc_html__( 'Cap One Wave', 'cap-one-wave' ), 'manage_options', 'cap-one-wave-settings', array( $this, 'cap_one_settings_page' ) );

		}

		/**
		 * Creates our settings in the options table
		 *
		 * @param void
		 * @return void
		 */
		public function register_option() {

			register_setting( 'cap_one_wave_settings', 'cow_settings', array( 'sanitize_callback' => array( $this, 'sanitize_cow' ) ) );

		}

		public function sanitize_cow( $new ) {

			// maybe sanitize Wave strings
			return $new;

		}

		/**
		 * Output a settings page
		 *
		 */
		public function cap_one_settings_page() {

			$settings = $this->get_settings();
			?>

			<div class="wrap">
				<h2><?php esc_html_e('Cap One Wave Settings'); ?></h2>
			
				<p><?php echo sprintf( __('<a href="%s" target="_blank" rel="noopener">Please check out the Cap One Wave documentation</a>.', 'cap-one-wave' ), 'https://web.little-package.com/cap-one-to-wave/documentation/' ); ?>
				
				<p><em><?php echo __( 'Have I helped?', 'cap-one-wave' ); ?></em></p>
				<a href=" https://paypal.me/SagehenStudio" target="_blank" rel="noopener">Make a small donation in thanks :)</a>

				<form method="post" action="options.php">

				<?php 
					settings_fields( 'cap_one_wave_settings' );
					do_settings_sections( 'cap_one_wave_settings' ); 
				?>

					<table class="form-table">
						<tr>
							<th>
								<label for="cow_token"><?php esc_html_e( 'Wave Full Access Token', 'cap-one-wave' ); ?></label>
							</th>
							<td>
								<input type="text" id="cow_token" name="cow_settings[token]" placeholder="<?php esc_attr_e( $settings['token'] ); ?>" value="<?php esc_attr_e( $settings['token'] ); ?>" class="widefat">
								<p><?php echo sprintf( __( '<a href="%s" target="_blank" rel="noopener">You gotta set up a Wave API connection to get a token</a>.', 'cap-one-wave' ), 'https://developer.waveapps.com/hc/en-us/articles/360020948171#application' ); ?></p>
							</td>
						</tr>
						<tr>
							<th>
								<label for="cow_business"><?php esc_html_e( 'Wave Business ID', 'cap-one-wave' ); ?></label>
							</th>
							<td>
								<input type="text" id="cow_business" name="cow_settings[biz_id]" placeholder="<?php esc_attr_e( $settings['biz_id'] ); ?>" value="<?php echo $settings['biz_id']; ?>" class="widefat">
								<p><?php echo sprintf( __( '<a href="%s" target="_blank" rel="noopener">How to find your business ID?</a>' , 'cap-one-wave' ), 'Learn more at: https://little-package.com/blog/2021/01/a-side-project/' ); ?></p>
							</td>
						</tr>
						<tr>
							<th>
								<label for="cow_liab"><?php esc_html_e( 'Liability Account', 'cap-one-wave' ); ?></label>
							</th>
							<td>
								<?php $accounts = $this->get_accounts( 'LIABILITY' );
								if ( ! empty( $accounts ) ) { ?>
								<select id="cow_liab" name="cow_settings[liability]" data-placeholder="Liability Account">
									<?php foreach ( $accounts as $id => $name ) { ?>
										<option value="<?php echo $id; ?>" <?php selected( $settings['liability'], $id ); ?>><?php echo $name; ?></option>
									<?php } ?>                       
								</select>
								<p><?php echo __( '(We recommend this be your Capital One liability account)', 'cap-one-wave' ); ?></p>
								<?php } else { ?>
									<input type="text" id="cow_liab" name="cow_settings[liability]" placeholder="<?php esc_attr_e( $settings['liability'] ); ?>" value="<?php esc_attr_e( $settings['liability'] ); ?>" class="widefat">
									<p><?php echo __( 'Once your token and business ID are entered correctly, a pulldown will show here with all liability accounts.' , 'cap-one-wave' ); ?></p>
								<?php } ?>
							</td>
						</tr>
						<tr>
							<th>
								<label for="cow_expense"><?php esc_html_e( 'Expense Account', 'cap-one-wave' ); ?></label>
							</th>
							<td>
								<?php $accounts = $this->get_accounts( 'EXPENSE' );
								if ( ! empty( $accounts ) ) { ?>
								<select id="cow_expense" name="cow_settings[expense]" data-placeholder="Expense Account">
									<?php foreach ( $accounts as $id => $name ) { ?>
										<option value="<?php echo $id; ?>" <?php selected( $settings['expense'], $id ); ?>><?php echo $name; ?></option>
									<?php } ?>                       
								</select>
								<p><?php echo __( '(We recommend you use Uncategorized Expense unless you have another plan. A lot can be done here using plugin hooks...)', 'cap-one-wave' ); ?></p>
								<?php } else { ?>
									<input type="text" id="cow_expense" name="cow_settings[expense]" placeholder="<?php esc_attr_e( $settings['expense'] ); ?>" value="<?php esc_attr_e( $settings['expense'] ); ?>" class="widefat">
									<p><?php echo __( 'Once your token and business ID are entered correctly, a pulldown will show here with all expense accounts.' , 'cap-one-wave' ); ?></p>
								<?php } ?>
							</td>
						</tr>
					</table>

				<?php submit_button(); ?>
				</form>
			</div>

		<?php }

		public function admin_notices(  ) {

			if ( defined( 'DISABLE_NAG_NOTICES' ) && DISABLE_NAG_NOTICES === TRUE ) {
                return;
			}

			$settings = $this->get_settings();

			if ( $settings['token'] === 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' ||
				$settings['biz_id'] === 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
			) {

				echo '<div class="error"><p>' . __( '👋 <strong>Capital One to Wave</strong> requires some complex setup before it will work as a Wordpress plugin.', 'cap-one-wave' ) . '<br />' . __( 'Your personal Wave API key and business account IDs must be provided in the code. Dig into the source code of capital-one-wave.php to enter missing PHP variable values.', 'cap-one-wave' ) . '<br />';
				echo __( 'Lost? Need this installed for you? Contact caroline@sagehen.studio to discuss rates.', 'cap-one-wave' ) . '</p></div>';

			}

		}

		/**
		 *
		 * Filter hook on WP Webhook custom action
		 * Gives us the opportunity to try to send data to Wave
		 *
		 * @param array $return_args
		 * @param string $identifier
		 * @param array $response_body
		 * @return array $return_args
		 */
		public function cap_one_wave_webhook( $return_args, $identifier, $response_body ){

			$my_identifier = apply_filters( 'cap_one_wave_identifier', 'cap-one-zap-wave' );
			
			// If the 'wpwh_identifier' identifier doesn't match, stop
			if ( $identifier !== $my_identifier ){
				error_log( 'identifier: ' . print_r( $data, true ) );
				return $return_args;
			}

			// Validate the incoming value from Zapier
			$date   = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'date' );
			$amt    = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'amt' );
			$payee  = WPWHPRO()->helpers->validate_request_value( $response_body['content'], 'payee' );

			// something has gone wrong, check the WP error logs
			if ( ! isset( $date ) || ! isset( $amt ) || ! isset( $payee ) ) {
				error_log( 'data: ' . print_r( $data, true ) );
				return $return_args;
			}

			// date arrives from Capital One in 1/31/2021 format, we need to adjust that for Wave
			$date = date_create_from_format('n/j/Y', $date );
			$date = date_format( $date, 'Y-m-d' );

			$data = apply_filters( 'cap_one_wave_filter_post_data', array ( 
				'date'  => $date,
				'amt'   => $amt,
				'payee' => $payee
			) );

			// make a payment
			$charge = $this->post_cap_one_charge( $data );

			// $charge is a boolean whether successfully sent to Wave or not
			// could do more here with that.

			// Let WP Webhooks finish its job
			return $return_args;

		}

		/**
		 *
		 * Enter a Capital One charge in Wave transactions
		 * Using Wave's GraphQL-based API
		 *
		 * @param array $data
		 * @return boolean
		 */
		private function post_cap_one_charge( $data ) {

			$settings = $this->get_settings();

			if ( apply_filters( 'we_have_cap_one_wave_expense_category_array', FALSE ) ) {
				// maybe you have no idea what\'s going on here
				// if not, I recommend you hire a developer!
				$expense_account_id = $this->expense_categorization( $data['payee'] );
			} else {
				$expense_account_id = $settings['uncategorized_id'];
			}

			$random_string = chr( rand(65,90)) . rand(65,90) . chr(rand(65,90)) . rand(65,90) . chr(rand(65,90) );

			// You could do more with this if you are concerned
			$external_id = 'uid:' . $random_string;
			$external_id = apply_filters( 'cap_one_wave_filter_external_id', $external_id, $data );

			$post = wp_json_encode([

				'query' => 'mutation ($inputMoneyTransactionCreate: MoneyTransactionCreateInput!) { moneyTransactionCreate(input: $inputMoneyTransactionCreate) { didSucceed, inputErrors { code, message, path } } }',
				'variables' => apply_filters( 'cap_one_wave_moneytransaction_variables', array(
					'inputMoneyTransactionCreate' => array(
						'businessId'        => $settings['biz_id'],
						'externalId'        => $external_id,
						'date'              => $data['date'],
						'description'       => $data['payee'],
						// THE ANCHOR
						// https://community.waveapps.com/discussion/6415/what-exactly-is-the-the-anchor-account-in-a-moneytransaction
						'anchor'            => array(
							'accountId'         => $settings['cap_one_id'], // Capital One liability
							'amount'            => $data['amt'],
							'direction'         => 'WITHDRAWAL'
						),
						// EXPENSED
						'lineItems'         => array(
							'accountId'         => $expense_account_id,
							'amount'            => $data['amt'],
							'balance'           => 'DEBIT'
						)

					)
				) )

			]);

			$response = $this->wp_remote_post( $post );

			if ( ! $response ) return false;

			$response = json_decode( $response['body'], true );

			if ( isset( $response['data'] ) ) {
				if ( $response['data']['moneyTransactionCreate']['didSucceed'] == TRUE ) {
					return true;
					}
			}

			error_log( 'Capital One charge recording failure ' . print_r( $response, true ) );
			return false;

		}

		/**
		 * Fetch business accounts from Wave
		 *
		 * @param string $type
		 * @return void
		 */
		public function get_accounts( $type ) {

			$settings = $this->get_settings();

			$post = wp_json_encode([ 
				'query' => 'query ($businessId: ID!, $page: Int!, $pageSize: Int!) { business(id: $businessId) { accounts( page: $page, pageSize: $pageSize, types: [' . $type . ']) { edges { node { id, name } } } } }',
				'variables' => array(
					'businessId' => $settings['biz_id'],
					'page' => 1,
					'pageSize' => apply_filters( 'cap_one_wave_max_pagesize', 100 ),
				)

			]);

			$response = $this->wp_remote_post( $post );

			if ( is_wp_error( $response ) ) {
				error_log( 'Cap One Wave: HTTP request error' . print_r( $response->get_error_message(), true) );
				return false;
			}

			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				error_log( 'Cap One Wave: HTTP status code was not 200' );
				return false;
			}

			$response = json_decode( $response['body'], true );

			if ( isset( $response['data'] ) ) {
				if ( isset( $response['data']['business']['accounts']['edges'] ) ) {
					$array = array();
					foreach ( $response['data']['business']['accounts']['edges'] as $edge ) {
						$array[$edge['node']['id']] = $edge['node']['name'];
					}
					return $array;
				}
			} else {
				error_log( 'Response error while getting Wave accounts: ' . print_r( $response, true ) );
				return array();
			}

		}

		/**
		 *
		 * The most important bit...
		 * MAKE HTTP REQUEST TO WAVE
		 *
		 */
		private function wp_remote_post( $data ) {

			$settings = $this->get_settings();
			$headers = $settings['headers'];

			$response = wp_remote_post( 'https://gql.waveapps.com/graphql/public', array(
					'method'      => 'POST',
					'timeout'     => 30,
					'blocking'    => true,
					'headers'     => $headers,
					'body'        => $data,
					'cookies'     => array()
					)
				);
 
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				error_log( 'Wave HTTP request error' . print_r( $error_message, true) );
				return false;
			} else {
				// error_log( 'Successful Wave HTTP request response: ' . print_r( $response, true ) );
			}
			return $response;

		}


		/**
		 * 
		 * Get an accurate expense category for Wave entry
		 *
		 * If you wanted to categorize frequent transactions automatically,
		 * You could flesh out this $array using the 'cap_one_wave_expense_array' hook,
		 * with the payee (lowercase) as key, and the Wave expense category ID as value
		 * It's worth the trouble now to save some data entry later.
		 *
		 * @param string $payee
		 * @return string
		 */
		private function expense_categorization( $payee ) {

			$payee = strtolower( $payee );
			$array = apply_filters( 'cap_one_wave_expense_array', array( 

				'verizon' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx==', // utilities account ID, for example

			) );

			// This is crude but it works
			if ( array_key_exists( $payee, $array ) ) {
				return $array[$payee];
			} else {
				return $this->uncategorized_expense_id; // uncategorized
			}

		}
		
		/**
		 *
		 * THIS FUNCTION USES A POST CREATED BY COMPLEX MEANS:
		 *
		 * 1) Capital one sends an alert to a Zapier robot email address (alphanumerical@robot.zapier.com)
		 * 2) Zapier sends POST via webhook to Wordpress (using a WP Webhook plugin)
		 * 3) WP Webhook creates a Wordpress post
		 * 4) We watch for the post using action hook 'publish post'
		 * 5) We use the data from the post to create a Wave entry in noteCharge() method
		 * 6) Successful or not, we delete the private WP post to keep the DB clean
		 * 
		 * @param object $entry
		 * @param object $form
		 *
		 * @return void 
		 *
		 */
		public function capital_one_to_wave( $new_status, $old_status, $post_obj ) {

			if ( $new_status != 'private' && $old_status != 'new' ) {
				return;
			}

			if ( get_the_excerpt( $post_obj ) != 'new Capital One charge' ) {
				return;
			}

			$date = get_the_date( 'Y-m-d', $post_obj );

			$data = array ( 
				'post'	=> 'post:' . $post_obj->ID,
				'date'	=> $date,
				'amt'	=> $post_obj->post_content, // charge amt is held in WP post content
				'payee'	=> $post_obj->post_title, // payee is held in WP post title
			);

			// error_log( 'data: ' . print_r( $data, true ) );

			// make a payment
			$charge = $this->enter_capital_one_charge( $data );

			if ( $charge ) {
				wp_delete_post( $post_obj->ID, TRUE ); // second param bypasses trash
			} else {
				// maybe try again?
			}

		}

	} // end class Capital_One_Wave

endif;

function RideZWave() {
	return Cap_One_Wave::instance();
}
RideZWave();