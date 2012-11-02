<?php
/**
 * Plugin Name: Inform about Content
 * Plugin URI:  http://wordpress.org/extend/plugins/inform-about-content/
 * Text Domain: inform_about_content
 * Domain Path: /languages
 * Description: Informs all users of a blog about a new post and approved comments via email
 * Author:      Inpsyde GmbH
 * Version:     0.0.5
 * License:     GPLv3
 * Author URI:  http://inpsyde.com/
 */

/**
 * Informs all users of a blog about a new post and approved comments via email
 *
 * @author   fb
 * @since    0.0.1
 * @version  09/05/2012
 */

if ( ! class_exists( 'Inform_About_Content' ) ) {
	// add plugin to WP
	if ( function_exists( 'add_action' ) ) {

		# set the default behaviour
		add_filter( 'iac_default_opt_in', array( 'Inform_About_Content', 'default_opt_in' ) );
		add_action( 'plugins_loaded' ,    array( 'Inform_About_Content', 'get_object' ) );

		# some default filters
		add_filter( 'iac_post_message',    'strip_tags' );
		add_filter( 'iac_comment_message', 'strip_tags' );
	}

	class Inform_About_Content {

		/**
		 * Textdomain
		 *
		 * @const string
		 */
		const TEXTDOMAIN = 'inform_about_content';

		/**
		 * set the default behaviour
		 * TRUE    all users have to opt-out to the notification
		 * FALSE   all users have to opt-in to the notification
		 *
		 * @var bool
		 */
		static protected $default_opt_in = FALSE;

		static private $classobj         = NULL;

		/**
		 * hard values, alternative use the settings on user profile
		 * bool for active mail for a new post
		 */
		public $inform_about_posts       = TRUE;

		// bool for active mail for a new comment
		public $inform_about_comments    = TRUE;

		// strings for mail
		public $mail_string_new_comment_to;

		public $mail_string_to;

		public $mail_string_by;

		public $mail_string_url;

		/**
		 * plugin options
		 *
		 * @since 0.0.5
		 * @var array
		 */
		protected $options = array();

		/**
		 * set's the default behaviour of mail sending
		 * applied to the filter 'iac_default_opt_in'
		 *
		 * @since 0.0.5
		 * @param bool $default_opt_in
		 * @return FALSE
		 */
		public static function default_opt_in( $default_opt_in ) {

			return FALSE;
		}

		/**
		 * Handler for the action 'init'. Instantiates this class.
		 *
		 * @access public
		 * @since 0.0.1
		 * @return $classobj
		 */
		public function get_object() {

			if ( NULL === self :: $classobj ) {
				self :: $classobj = new self;
			}

			return self :: $classobj;
		}

		/**
		 * Constructor, init on defined hooks of WP and include second class
		 *
		 * @access  public
		 * @since   0.0.1
		 * @uses    add_action
		 * @return  void
		 */
		public function __construct() {
			spl_autoload_register( array( __CLASS__, 'load_class' ) );

			# change the default behaviour from outside
			self::$default_opt_in = apply_filters( 'iac_default_opt_in', FALSE );

			// set srings for mail
			$this->mail_string_new_comment_to = __( 'new comment to', $this->get_textdomain() );
			$this->mail_string_to             = __( 'to:', $this->get_textdomain() );
			$this->mail_string_by             = __( 'by', $this->get_textdomain() );
			$this->mail_string_url            = __( 'URL', $this->get_textdomain() );

			$Iac_Profile_Settings = Iac_Profile_Settings :: get_object();
			$settings = new Iac_Settings();
			$this->options = $settings->options;
			#apply a hook to get the current settings
			add_filter( 'iac_get_options', array( $this, 'get_options' )  );

			add_action( 'admin_init', array( $this, 'localize_plugin' ), 9 );

			if ( $this->inform_about_posts )
				add_action( 'publish_post', array( $this, 'inform_about_posts' ) );
			if ( $this->inform_about_comments )
				add_action( 'comment_post', array( $this, 'inform_about_comment' ) );
		}

		/**
		 * Return Textdomain string
		 *
		 * @access  public
		 * @since   0.0.2
		 * @return  string
		 */
		public function get_textdomain() {

			return self::TEXTDOMAIN;
		}

		/**
		 * localize_plugin function.
		 *
		 * @uses   load_plugin_textdomain, plugin_basename
		 * @access public
		 * @since  0.0.2
		 * @return void
		 */
		public function localize_plugin() {

			load_plugin_textdomain(
				$this->get_textdomain(),
				FALSE,
				dirname( plugin_basename( __FILE__ ) ) . '/languages'
			);

		}

		/**
		 * Get user-mails from all users of a blog by a meta key and the exclusion value ( `!=` operator )
		 *
		 * @access  public
		 * @since  0.0.1
		 * @used   get_users
		 * @param  string $current_user_email email of user
		 * @param  string $context should be 'comment' or 'post'
		 * @return array string $users
		 */
		public function get_members( $current_user_email = NULL, $context = '' ) {

			$meta_key      = $context . '_subscription';
			$meta_value    = '0';
			$meta_compare  = '!=';
			$include_empty = TRUE;

			if ( self::$default_opt_in ) {
				$meta_value = '1';
				$meta_compare = '=';
				$include_empty = FALSE;
			}

			$users = $this->get_users_by_meta(
				$meta_key, $meta_value, $meta_compare, $include_empty
			);
			$user_addresses = array();
			
			if ( ! is_array( $users ) || empty( $users ) )
				return '';

			foreach ( $users as $user ) {
				
				if ( $current_user_email === $user->data->user_email )
					continue;
					
				$user_addresses[] = $user->data->user_email;
			}
			
			return implode( ', ', $user_addresses );
		}

		/**
		 * get users by meta key
		 *
		 * @since 0.0.5
		 * @param string $meta_key
		 * @param string $meta_value (Optional)
		 * @param string $meta_compare (Optional) Out of '!=', '<>' OR '='
		 * @param bool $include_empty (Optional) Set this to TRUE to retreve Users where the meta-key has not been set yet
		 * @return array of user-objects
		 */
		public function get_users_by_meta( $meta_key, $meta_value = '', $meta_compare = '', $include_empty = FALSE ) {

			if ( $include_empty ) {
				#get all with the opposit value
				if ( in_array( $meta_compare, array( '<>', '!=' ) ) )
					$meta_compare = '=';
				else
					$meta_compare = '!=';

				$query = new WP_User_Query(
					array(
						'meta_key'     => $meta_key,
						'meta_value'   => $meta_value,
						'meta_compare' => $meta_compare,
						'fields'       => 'ID'
					)
				);
				$exclude_users = $query->get_results();

				# get all users
				$query = new WP_User_Query(
					array(
						'fields'  => 'all_with_meta',
						'exclude' => $exclude_users
					)
				);

				return $query->get_results();
			}

			$query = new WP_User_Query(
				array(
					'meta_key'      => $meta_key,
					'meta_value'    => $meta_value,
					'meta_compare'  => $meta_compare,
					'fields'        => 'all_with_meta'
				)
			);

			return $query->get_results();
		}

		/**
		 * Send mail, if publish a new post
		 *
		 * @access  public
		 * @sinde   0.0.1
		 * @used    get_post, get_userdata, get_author_name, get_option, wp_mail, get_permalink
		 * @param   string $post_id
		 * @return  string $post_id
		 */
		public function inform_about_posts( $post_id = FALSE ) {

			if ( $post_id ) {
				// get data from current post
				$post_data = get_post( $post_id );
				// get mail from author
				$user = get_userdata( $post_data->post_author );

				// email addresses
				$to = $this->get_members( $user->data->user_email, 'post' );
				if ( empty( $to ) )
					return $post_id;

				// email subject
				$subject = get_option( 'blogname' ) . ': ' . get_the_title( $post_data->ID );

				// message content
				$message = $post_data->post_content . ' ' . PHP_EOL .
					$this->mail_string_by . ' ' .
					get_the_author_meta( 'display_name', $user->ID ) . ' ' . PHP_EOL .
					$this->mail_string_url . ': ' .
					get_permalink( $post_id );

				# create header data
				$headers = array();
				# From:
				$headers[ 'From' ] =
					get_the_author_meta( 'display_name', $user->ID ) .
					' (' . get_bloginfo( 'name' ) . ')' .
					' <' . $user->data->user_email . '>';

				if ( $this->options[ 'send_by_bcc' ] ) {
					$bcc = $to;
					$to  = get_bloginfo( 'admin_email' );
					$headers[ 'Bcc' ] = $bcc;
				}
				$to      = apply_filters( 'iac_post_to',      $to,      $this->options );
				$subject = apply_filters( 'iac_post_subject', $subject, $this->options );
				$message = apply_filters( 'iac_post_message', $message, $this->options );
				$headers = apply_filters( 'iac_post_headers', $headers, $this->options );

				$this->send_mail(
					$to,
					$subject,
					$message,
					$headers
				);

			}

			return $post_id;
		}

		/**
		 * Send mail, if approved a new comment
		 *
		 * @access  public
		 * @sinde   0.0.1
		 * @used    get_comment, get_post, get_userdata, get_author_name, get_option, wp_mail, get_permalink
		 * @param   string $comment_id
		 * @param   boolean $comment_status
		 * @return  string $comment_id
		 */
		public function inform_about_comment( $comment_id = FALSE, $comment_status = FALSE ) {

			if ( $comment_id ) {
				// get data from current comment
				$comment_data = get_comment( $comment_id );
				// if comment status is approved
				if ( '1' === $comment_data->comment_approved || $comment_status ) {
					// get data from post to this comment
					$post_data = get_post( $comment_data->comment_post_ID );
					// the comment author
					$user = get_userdata( $comment_data->user_id );

					// email addresses
					$to = $this->get_members( $user->data->user_email, 'comment' );
					if ( empty( $to ) )
						return $comment_id;

					// email subject
					$subject = get_bloginfo( 'name' ) . ': ' . get_the_title( $post_data->ID );
					// message content
					$message = $comment_data->comment_content . ' ' . PHP_EOL .
						$this->mail_string_by . ' ' .
						get_the_author_meta( 'display_name', $user->ID ) . ' ' .
						$this->mail_string_to . ' ' .
						get_the_title( $post_data->ID ) . ' ' . PHP_EOL .
						$this->mail_string_url . ': ' .
						get_permalink( $post_data->ID );

					// create header data
					$headers = array();
					$headers[ 'From' ] =
						get_the_author_meta( 'display_name', $user->ID ) .
						' (' . get_bloginfo( 'name' ) . ')' .
						' <' . $user->data->user_email . '>';

					if ( $this->options[ 'send_by_bcc' ] ) {
						$bcc = $to;
						$to = get_bloginfo( 'admin_email' );
						$headers[ 'Bcc' ] = $bcc;
					}

					$to      = apply_filters( 'iac_comment_to',      $to,      $this->options );
					$subject = apply_filters( 'iac_comment_subject', $subject, $this->options );
					$message = apply_filters( 'iac_comment_message', $message, $this->options );
					$headers = apply_filters( 'iac_comment_headers', $headers, $this->options );

					// send mail
					$this->send_mail(
						$to,
						$subject, // email subject
						$message, // message content
						$headers // headers
					);
					
				}
			}

			return $comment_id;
		}

		/**
		 * builds the header and sends mail
		 *
		 * @since 0.0.5 (2012.09.03)
		 * @param string $to
		 * @param string $subject
		 * @param string $message
		 * @param  array $headers
		 * @return  bool
		 */
		public function send_mail( $to, $subject = '', $message = '', $headers = array() ) {

			foreach ( $headers as $k => $v ) {
				
				$headers[] = $k . ': ' . $v;
				unset( $headers[ $k ] );
			}
			$headers = implode( PHP_EOL, $headers ) . PHP_EOL;

			return wp_mail(
				$to,
				$subject,
				$message,
				$headers
			);

		}

		/**
		 * getter for the current settings
		 *
		 * @since 0.0.5
		 * @param mixed $default
		 * @return array
		 */
		public function get_options( $default = NULL ) {
			
			if ( ! empty( $this->options ) )
				return $this->options;
			
			return $default;
		}

		/**
		 * autoloader for the classes
		 *
		 * @since 0.0.5
		 * @param string $class_name
		 * @return void
		 */
		public static function load_class( $class_name ) {

			$file_name = dirname( __FILE__ ) . '/inc/class-' . $class_name . '.php';
			
			if ( file_exists( $file_name ) )
				require_once $file_name;
		}

	} // end class Inform_About_Content

} // end if class exists
