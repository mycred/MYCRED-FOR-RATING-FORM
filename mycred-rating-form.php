<?php
/**
 * Plugin Name: myCRED for Rating Form
 * Plugin URI: http://mycred.me
 * Description: Allows you to reward users points for ratings.
 * Version: 1.0.1
 * Tags: mycred, points, rate
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 * Author Email: support@mycred.me
 * Requires at least: WP 4.0
 * Tested up to: WP 4.6
 * Text Domain: mycred_ratingform
 * Domain Path: /lang
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
if ( ! class_exists( 'myCRED_RatingForm' ) ) :
	final class myCRED_RatingForm {

		// Plugin Version
		public $version             = '1.0.1';

		// Instnace
		protected static $_instance = NULL;

		// Current session
		public $session             = NULL;

		public $slug                = '';
		public $domain              = '';
		public $plugin              = NULL;
		public $plugin_name         = '';
		protected $update_url       = 'http://mycred.me/api/plugins/';

		/**
		 * Setup Instance
		 * @since 1.0
		 * @version 1.0
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Not allowed
		 * @since 1.0
		 * @version 1.0
		 */
		public function __clone() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' ); }

		/**
		 * Not allowed
		 * @since 1.0
		 * @version 1.0
		 */
		public function __wakeup() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' ); }

		/**
		 * Define
		 * @since 1.0
		 * @version 1.0
		 */
		private function define( $name, $value, $definable = true ) {
			if ( ! defined( $name ) )
				define( $name, $value );
		}

		/**
		 * Require File
		 * @since 1.0
		 * @version 1.0
		 */
		public function file( $required_file ) {
			if ( file_exists( $required_file ) )
				require_once $required_file;
		}

		/**
		 * Construct
		 * @since 1.0
		 * @version 1.0
		 */
		public function __construct() {

			$this->slug        = 'mycred-rating-form';
			$this->plugin      = plugin_basename( __FILE__ );
			$this->domain      = 'mycred_ratingform';
			$this->plugin_name = 'myCRED for Rating Form';

			$this->define_constants();
			$this->plugin_updates();

			add_filter( 'mycred_setup_hooks',    array( $this, 'register_hook' ) );
			add_action( 'mycred_init',           array( $this, 'load_textdomain' ) );
			add_action( 'mycred_all_references', array( $this, 'add_badge_support' ) );
			add_action( 'mycred_load_hooks',    'mycred_rating_form_load_hook' );

		}

		/**
		 * Define Constants
		 * @since 1.0
		 * @version 1.0
		 */
		public function define_constants() {

			$this->define( 'MYCRED_RATINGFORM_SLUG',  $this->slug );
			$this->define( 'MYCRED_DEFAULT_TYPE_KEY', 'mycred_default' );

		}

		/**
		 * Includes
		 * @since 1.0
		 * @version 1.0
		 */
		public function includes() { }

		/**
		 * Load Textdomain
		 * @since 1.0
		 * @version 1.0
		 */
		public function load_textdomain() {

			// Load Translation
			$locale = apply_filters( 'plugin_locale', get_locale(), $this->domain );

			load_textdomain( $this->domain, WP_LANG_DIR . '/' . $this->slug . '/' . $this->domain . '-' . $locale . '.mo' );
			load_plugin_textdomain( $this->domain, false, dirname( $this->plugin ) . '/lang/' );

		}

		/**
		 * Plugin Updates
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_updates() {

			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ), 380 );
			add_filter( 'plugins_api',                           array( $this, 'plugin_api_call' ), 380, 3 );
			add_filter( 'plugin_row_meta',                       array( $this, 'plugin_view_info' ), 380, 3 );

		}

		/**
		 * Register Hook
		 * @since 1.0
		 * @version 1.0
		 */
		public function register_hook( $installed ) {

			$installed['rating-form'] = array(
				'title'       => __( 'Rating Form', 'mycred_ratingform' ),
				'description' => __( 'Awards %_plural% to users who rate posts', 'mycred_ratingform' ),
				'callback'    => array( 'myCRED_Hook_Rating_Form' )
			);

			return $installed;

		}

		/**
		 * Add Badge Support
		 * @since 1.0
		 * @version 1.0
		 */
		public function add_badge_support( $references ) {

			$references['rate_post'] = __( 'Rating (Rating Form)', 'mycred_ratingform' );

			return $references;

		}

		/**
		 * Plugin Update Check
		 * @since 1.0
		 * @version 1.0
		 */
		public function check_for_plugin_update( $checked_data ) {

			global $wp_version;

			if ( empty( $checked_data->checked ) )
				return $checked_data;

			$args = array(
				'slug'    => $this->slug,
				'version' => $this->version,
				'site'    => site_url()
			);
			$request_string = array(
				'body'       => array(
					'action'     => 'version', 
					'request'    => serialize( $args ),
					'api-key'    => md5( get_bloginfo( 'url' ) )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
			);

			// Start checking for an update
			$response = wp_remote_post( $this->update_url, $request_string );

			if ( ! is_wp_error( $response ) ) {

				$result = maybe_unserialize( $response['body'] );

				if ( is_object( $result ) && ! empty( $result ) )
					$checked_data->response[ $this->slug . '/' . $this->slug . '.php' ] = $result;

			}

			return $checked_data;

		}

		/**
		 * Plugin View Info
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_view_info( $plugin_meta, $file, $plugin_data ) {

			if ( $file != $this->plugin ) return $plugin_meta;

			$plugin_meta[] = sprintf( '<a href="%s" class="thickbox" aria-label="%s" data-title="%s">%s</a>',
				esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $this->slug .
				'&TB_iframe=true&width=600&height=550' ) ),
				esc_attr( __( 'More information about this plugin', 'mycred_ratingform' ) ),
				esc_attr( $this->plugin_name ),
				__( 'View details', 'mycred_ratingform' )
			);

			return $plugin_meta;

		}

		/**
		 * Plugin New Version Update
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_api_call( $result, $action, $args ) {

			global $wp_version;

			if ( ! isset( $args->slug ) || ( $args->slug != $this->slug ) )
				return $result;

			// Get the current version
			$args = array(
				'slug'    => $this->slug,
				'version' => $this->version,
				'site'    => site_url()
			);
			$request_string = array(
				'body'       => array(
					'action'     => 'info', 
					'request'    => serialize( $args ),
					'api-key'    => md5( get_bloginfo( 'url' ) )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
			);

			$request = wp_remote_post( $this->update_url, $request_string );

			if ( ! is_wp_error( $request ) )
				$result = maybe_unserialize( $request['body'] );

			return $result;

		}

	}
endif;

function mycred_ratingform_plugin() {
	return myCRED_RatingForm::instance();
}
mycred_ratingform_plugin();

/**
 * Rating Form Hook
 * @since 1.0
 * @version 1.0.1
 */
if ( ! function_exists( 'mycred_rating_form_load_hook' ) ) :
	function mycred_rating_form_load_hook() {

		if ( class_exists( 'myCRED_Hook_Rating_Form' ) ) return;

		class myCRED_Hook_Rating_Form extends myCRED_Hook {

			/**
			 * Construct
			 */
			function __construct( $hook_prefs, $type = MYCRED_DEFAULT_TYPE_KEY ) {

				parent::__construct( array(
					'id'       => 'rating-form',
					'defaults' => array(
						'creds'    => 1,
						'log'      => '%plural% for rating',
						'limit'    => '0/x'
					)
				), $hook_prefs, $type );

			}

			/**
			 * Run
			 * This class method is fired of by myCRED when it's time to load all hooks.
			 * It should be used to "hook" into the plugin we want to add support for or the
			 * appropriate WordPress instances. Anything that must be loaded for this hook to work
			 * needs to be called here.
			 * @since 1.0
			 * @version 1.0
			 */
			public function run() {

				if ( $this->prefs['creds'] != 0 )
					add_action( 'rating_form_new_rating', array( $this, 'new_rating' ), 10, 3 );

			}

			/**
			 * New Rating
			 * @since 1.0
			 * @version 1.0.1
			 */
			public function new_rating( $rate_id, $post_id, $user_id ) {

				$data = array( 'ref_type' => 'post', 'rating' => $rate_id );

				// Award rating
				if ( $this->prefs['creds'] != 0 ) {

					// Can not award guests
					if ( ! is_user_logged_in() ) return;

					// Check for exclusion
					if ( $this->core->exclude_user( $user_id ) ) return;

					// Make sure this is unique event
					if ( $this->core->has_entry( 'rate_post', $post_id, $user_id, $data, $this->mycred_type ) ) return;

					// Execute
					if ( ! $this->over_hook_limit( '', 'rate_post', $user_id ) )
						$this->core->add_creds(
							'rate_post',
							$user_id,
							$this->prefs['creds'],
							$this->prefs['log'],
							$post_id,
							$data,
							$this->mycred_type
						);

				}

			}

			/**
			 * Preferences
			 * If the hook has settings, it has to be added in using this class method.
			 * @since 1.0
			 * @version 1.0.1
			 */
			public function preferences() {

				$prefs = $this->prefs;

?>
<label class="subheader" for="<?php echo $this->field_id( 'creds' ); ?>"><?php _e( 'New Rating', 'mycred_ratingform' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'creds' ); ?>" id="<?php echo $this->field_id( 'creds' ); ?>" value="<?php echo $this->core->number( $prefs['creds'] ); ?>" size="8" /></div>
	</li>
	<li>
		<label for="<?php echo $this->field_id( 'limit' ); ?>"><?php _e( 'Limit', 'mycred_ratingform' ); ?></label>
		<?php echo $this->hook_limit_setting( $this->field_name( 'limit' ), $this->field_id( 'limit' ), $prefs['limit'] ); ?>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( 'log' ); ?>"><?php _e( 'Log Template', 'mycred_ratingform' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" value="<?php echo esc_attr( $prefs['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general', 'post' ) ); ?></span>
	</li>
</ol>
<?php

			}

			/**
			 * Sanitise Preferences
			 * While myCRED does some basic sanitization of the data you submit in the settings,
			 * we do need to handle our hook limits since 1.6. If your settings contain a checkbox (or multiple)
			 * then you should also use this method to handle the submission making sure the checkbox values are
			 * taken care of.
			 * @since 1.0
			 * @version 1.0
			 */
			function sanitise_preferences( $data ) {

				if ( isset( $data['limit'] ) && isset( $data['limit_by'] ) ) {
					$limit = sanitize_text_field( $data['limit'] );
					if ( $limit == '' ) $limit = 0;
					$data['limit'] = $limit . '/' . $data['limit_by'];
					unset( $data['limit_by'] );
				}

				return $data;

			}

		}

	}
endif;
