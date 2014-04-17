<?php
/**
 * Plugin Name: Informer
 * Plugin URI:  http://wordpress.org/extend/plugins/inform-about-content/
 * Text Domain: inform_about_content
 * Domain Path: /languages
 * Description: Informs all users of a blog about a new post and approved comments via email
 * Author:      Inpsyde GmbH
 * Version:     0.0.6-RC1
 * License:     GPLv3
 * Author URI:  http://inpsyde.com/
 */

/**
 * Informs all users of a blog about a new post and approved comments via email
 *
 * @author   fb, dn
 * @since    0.0.1
 * @version  05/02/2013
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

		add_filter( 'iac_post_message',    array( 'Inform_About_Content', 'sender_to_message' ), 10, 3 );
		add_filter( 'iac_comment_message', array( 'Inform_About_Content', 'sender_to_message' ), 10, 3 );

		# since 0.0.6
		add_filter( 'iac_post_message',    'strip_shortcodes' );
		add_filter( 'iac_comment_message', 'strip_shortcodes' );
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
		 * saved transit posts
		 *
		 * @since 2013-07-17
		 * @var array
		 */
		protected $transit_posts = array();

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
		public static function get_object() {

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
			
			// check for php 5.2
			// @see  http://www.php.net/manual/en/spl.installation.php
			if ( function_exists( 'spl_autoload_register' ) )
				spl_autoload_register( array( __CLASS__, 'load_class' ) );
			else
				$this->load_class( NULL );
			
			// change the default behaviour from outside
			self::$default_opt_in = apply_filters( 'iac_default_opt_in', FALSE );
			
			// set srings for mail
			$this->mail_string_new_comment_to = __( 'new comment to', $this->get_textdomain() );
			$this->mail_string_to             = __( 'to:', $this->get_textdomain() );
			$this->mail_string_by             = __( 'by', $this->get_textdomain() );
			$this->mail_string_url            = __( 'URL', $this->get_textdomain() );
			
			$Iac_Profile_Settings = Iac_Profile_Settings :: get_object();
			$settings = new Iac_Settings();
			$this->options = $settings->options;
			$this->options[ 'static_options' ] = array(
				'mail_string_to'             => $this->mail_string_to,
				'mail_string_by'             => $this->mail_string_by,
				'mail_string_url'            => $this->mail_string_url,
				'mail_string_new_comment_to' => $this->mail_string_new_comment_to
			);
			#apply a hook to get the current settings
			add_filter( 'iac_get_options', array( $this, 'get_options' ) );
			
			add_action( 'admin_init', array( $this, 'localize_plugin' ), 9 );
			
			if ( $this->inform_about_posts ) {
				add_action( 'transition_post_status', array( $this, 'save_transit_posts' ), 10, 3 );
				add_action( 'publish_post', array( $this, 'inform_about_posts' ) );
			}
			if ( $this->inform_about_comments )
				add_action( 'wp_insert_comment', array( $this, 'inform_about_comment' ) );
				// also possible is the hook comment_post
			
			// Disable the default core notification (filter ignores __return_false)
			add_filter( 'pre_option_comments_notify', '__return_zero' );
			
			// load additional features
			Iac_Threaded_Mails::get_instance();
			Iac_Attach_Media::get_instance();
		}
		
		/**
		 * Return Textdomain string
		 *
		 * @access  public
		 * @since   0.0.2
		 * @return  string
		 */
		public static function get_textdomain() {

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
		 * catch post status transition of each post
		 *
		 * @wp-hook transition_post_status
		 * @since 2013.07.17
		 * @param string $new_status
		 * @param string $old_status
		 * @param WP_Post $post
		 * @return void
		 */
		public function save_transit_posts( $new_status, $old_status, $post ) {

			$this->transit_posts[ $post->ID ] = array(
				'old_status' => $old_status,
				'new_status' => $new_status
			);
		}


		/**
		 * Send mail, if changes a status form not 'publish' to 'publish'
		 *
		 * @wp_hook publish_post
		 * @access  public
		 * @sinde   0.0.1
		 * @used    get_post, get_userdata, get_author_name, get_option, wp_mail, get_permalink
		 * @param   string $post_id
		 * @return  string $post_id
		 */
		public function inform_about_posts( $post_id = FALSE ) {
			
			if ( $post_id ) {
				
				if ( ! isset( $this->transit_posts[ $post_id ] ) )
					return $post_id;
				
				$transit = $this->transit_posts[ $post_id ];
				
				if ( 'publish' != $transit[ 'new_status' ] || 'publish' == $transit[ 'old_status' ] )
					return $post_id;
				
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
				$message = $post_data->post_content;

				# create header data
				$headers = array();
				# From:
				$headers[ 'From' ] =
					get_the_author_meta( 'display_name', $user->ID ) .
					' (' . get_bloginfo( 'name' ) . ')' .
					' <' . $user->data->user_email . '>';

				if ( $this->options[ 'send_by_bcc' ] ) {
					$bcc = $to;
					$to = empty( $this->options[ 'bcc_to_recipient' ] )
						? get_bloginfo( 'admin_email' )
						: $this->options[ 'bcc_to_recipient' ];
					$headers[ 'Bcc' ] = $bcc;
				}
				$to          = apply_filters( 'iac_post_to',          $to,      $this->options, $post_id );
				$subject     = apply_filters( 'iac_post_subject',     $subject, $this->options, $post_id );
				$message     = apply_filters( 'iac_post_message',     $message, $this->options, $post_id );
				$headers     = apply_filters( 'iac_post_headers',     $headers, $this->options, $post_id );
				$attachments = apply_filters( 'iac_post_attachments', array(),  $this->options, $post_id );
				$signature   = apply_filters( 'iac_post_signature',   '',       $this->options, $post_id );

				$this->send_mail(
					$to,
					$subject,
					$this->append_signature( $message, $signature ),
					$headers,
					$attachments
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

					// the commenter
					$commenter = array(
						'name'  => 'Annonymous',
						'email' => '',
						'url'   => ''
					);

					if ( 0 != $comment_data->user_id && $user = get_userdata( $comment_data->user_id ) ) {
						// the comment author
						$user = get_userdata( $comment_data->user_id );
						$commenter[ 'name' ]  = get_the_author_meta( 'display_name', $user->ID );
						$commenter[ 'email' ] = $user->data->user_email;
						$commenter[ 'url' ]   = $user->data->user_url;
					} else {
						if ( ! empty( $comment_data->comment_author ) )
							$commenter[ 'name' ] = $comment_data->comment_author;

						# don't propagate email-address of non-registered users by default
						if ( ! empty( $comment_data->comment_author_email ) ) {
							if ( TRUE === apply_filters( 'iac_comment_author_email_to_header', FALSE ) )
								$commenter[ 'email' ] = $comment_data->comment_author_email;
						}

						if ( ! empty( $comment_data->comment_author_url ) )
							$commenter[ 'url' ] = $comment_data->comment_author_url;
					}

					// email addresses
					$to = $this->get_members( $commenter[ 'email' ], 'comment' );
					if ( empty( $to ) )
						return $comment_id;

					// email subject
					$subject = get_bloginfo( 'name' ) . ': ' . get_the_title( $post_data->ID );
					// message content
					$message = $comment_data->comment_content;

					// create header data
					$headers = array();
					if ( ! empty( $commenter[ 'email' ] ) ) {
						$headers[ 'From' ] =
							$commenter[ 'name' ] .
							' (' . get_bloginfo( 'name' ) . ')' .
							' <' . $commenter[ 'email' ] . '>';
					} else {
						$headers[ 'From' ] =
							$commenter[ 'name' ] .
							' (' . get_bloginfo( 'name' ) . ')' .
							' <' . get_option( 'admin_email' ) . '>';
					}

					if ( $this->options[ 'send_by_bcc' ] ) {
						#copy list of recipients to 'bcc'
						$bcc = $to;
						# set a 'To' header
						$to = empty( $this->options[ 'bcc_to_recipient' ] )
							? get_bloginfo( 'admin_email' )
							: $this->options[ 'bcc_to_recipient' ];
						$headers[ 'Bcc' ] = $bcc;
					}

					$to          = apply_filters( 'iac_comment_to',          $to,      $this->options, $comment_id );
					$subject     = apply_filters( 'iac_comment_subject',     $subject, $this->options, $comment_id );
					$message     = apply_filters( 'iac_comment_message',     $message, $this->options, $comment_id );
					$headers     = apply_filters( 'iac_comment_headers',     $headers, $this->options, $comment_id );
					$attachments = apply_filters( 'iac_comment_attachments', array(),  $this->options, $comment_id );
					$signature   = apply_filters( 'iac_comment_signature',   '',       $this->options, $comment_id );

					$this->send_mail(
						$to,
						$subject,
						$this->append_signature( $message, $signature ),
						$headers,
						$attachments
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
		 * @param  array $attachments
		 * @return  bool
		 */
		public function send_mail( $to, $subject = '', $message = '', $headers = array(), $attachments = array() ) {

			foreach ( $headers as $k => $v ) {

				$headers[] = $k . ': ' . $v;
				unset( $headers[ $k ] );
			}
			
			return wp_mail(
				$to,
				$subject,
				$message,
				$headers,
				$attachments
			);
		}

		/**
		 * apply a signature-text to the email message
		 *
		 * @since 0.0.6 (2013.01.13)
		 * @param string $message,
		 * @param string $signature (Optional)
		 * @return string
		 */
		public function append_signature( $message, $signature = '' ) {

			if ( empty( $signature ) )
				return $message;

			$separator = apply_filters( 'iac_signature_separator', str_repeat( PHP_EOL, 2 ) . '--' . PHP_EOL );

			return $message . $separator . $signature;
		}

		/**
		 * add information about sender to the end of the
		 * content
		 *
		 * @wp_hook iac_post_message
		 * @wp_hook iac_comment_message
		 * @since 2013-07-18
		 * @param string $message
		 * @param array $options
		 * @param $id
		 * @return string
		 */
		public static function sender_to_message( $message, $options, $id ) {

			$author    = NULL;
			$commenter = NULL;
			$parts     = array();
			if ( 'iac_post_message' == current_filter() ) {
				$post = get_post( $id );
				$author = get_userdata( $post->post_author );
				if ( ! is_a( $author, 'WP_User' ) )
					return $message;

				$parts = array(
					'', # linefeed
					implode( ' ', array(
						$options[ 'static_options' ][ 'mail_string_by' ],
						$author->data->display_name
					) ),
					implode( ': ', array(
						$options[ 'static_options' ][ 'mail_string_url' ],
						get_permalink( $post )
					) )
				);
			} elseif ( 'iac_comment_message' == current_filter() ) {
				$comment   = get_comment( $id );
				$post      = get_post( $comment->comment_post_ID );
				$commenter = array(
						'name'  => 'Annonymous'
				);
				if ( 0 != $comment->user_id ) {
					$author  = get_userdata( $comment->user_id );
					$commenter[ 'name' ] = $author->data->display_name;
				} else {
					if ( ! empty( $comment->comment_author ) )
						$commenter[ 'name' ] = $comment->comment_author;
				}
				$parts = array(
					'',
					#author and title
					implode( ' ', array(
						$options[ 'static_options' ][ 'mail_string_by' ],
						$commenter[ 'name' ],
						$options[ 'static_options' ][ 'mail_string_to' ],
						get_the_title( $post->ID ),
					) ),
					# the posts permalink
					implode( ': ', array(
						$options[ 'static_options' ][ 'mail_string_url' ],
						get_permalink( $post )
					) )
				);
			}

			if ( ! empty( $parts ) )
				$message .= implode( PHP_EOL, $parts );

			return $message;
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
			// if spl_autoload_register not exist
			if ( NULL === $class_name ) {
				// load required classes
				foreach( glob( dirname( __FILE__ ) . '/inc/*.php' ) as $path )
					require_once $path;
			} else {
				// if param have a class string
				$path = dirname( __FILE__ ) . '/inc/class-' . $class_name . '.php';
				
				if ( file_exists( $path ) )
					require_once $path;
			}
		}
		
	} // end class Inform_About_Content

} // end if class exists
