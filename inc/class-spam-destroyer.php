<?php

/**
 * The main Spam Destroyer class
 * This handles all of the heavy work involved in fighting spam
 * 
 * @copyright Copyright (c), Ryan Hellyer
 * @author Ryan Hellyer <ryanhellyer@gmail.com>
 * @since 1.0
 */
class Spam_Destroyer {

	public $spam_key; // Key used for confirmation of bot-like behaviour
	public $captcha_question; // Question for CAPTCHA
	public $captcha_value; // Value of CAPTCHA
	public $speed = 5; // Will be killed as spam if posted faster than this

	/**
	 * Preparing to launch the almighty spam attack!
	 * Spam, prepare for your imminent death!
	 */
	public function __construct() {

		// Add filters
		add_filter( 'preprocess_comment',                   array( $this, 'check_for_comment_evilness' ) ); // Support for regular post/page comments
		add_filter( 'wpmu_validate_blog_signup',            array( $this, 'check_for_post_evilness' ) ); // Support for multisite site signups
		add_filter( 'wpmu_validate_user_signup',            array( $this, 'check_for_post_evilness' ) ); // Support for multisite user signups
		add_filter( 'bbp_new_topic_pre_content',            array( $this, 'check_for_post_evilness' ), 1 ); // Support for bbPress topics
		add_filter( 'bbp_new_reply_pre_content',            array( $this, 'check_for_post_evilness' ), 1 ); // Support for bbPress replies

		// Add to hooks
		add_action( 'init',                                 array( $this, 'set_key' ) );
		add_action( 'init',                                 array( $this, 'set_captcha' ) );
		
		add_action( 'comment_form_before',                  array( $this, 'start_hide_form_for_noscript' ) );
		add_action( 'comment_form_after',                   array( $this, 'end_hide_form_for_noscript' ) );
		add_action( 'comment_form_after_fields',            array( $this, 'extra_input_field' ) ); // WordPress comments page

		add_action( 'signup_hidden_fields',                 array( $this, 'extra_input_field' ) ); // WordPress multi-site signup page
		add_action( 'bp_after_registration_submit_buttons', array( $this, 'extra_input_field' ) ); // BuddyPress signup page
		add_action( 'bbp_theme_before_topic_form_content',  array( $this, 'extra_input_field' ) ); // bbPress signup page
		add_action( 'bbp_theme_before_reply_form_content',  array( $this, 'extra_input_field' ) ); // bbPress signup page
		add_action( 'register_form',                        array( $this, 'extra_input_field' ) ); // bbPress user registration page

	}

	/*
	 * Hides the form when JS not enabled
	 */
	public function start_hide_form_for_noscript() {

		// Ignore if user is logged in
		if ( is_user_logged_in() )
			return;

		// If high spam protection is set, then non JS users are blocked
		if ( SPAM_DESTROYER_PROTECTION == 'high' ) {
			echo '<noscript><div style="display: none;"></noscript>';
		}
	}

	/*
	 * Hides the form when JS not enabled
	 * Provides error message to alert users as to why they can't comment
	 */
	public function end_hide_form_for_noscript() {

		// Ignore if user is logged in
		if ( is_user_logged_in() )
			return;

		// If high spam protection is set, then non JS users are blocked
		if ( SPAM_DESTROYER_PROTECTION == 'high' ) {
			echo '<noscript></div>' . 
			__( 'Sorry, but you are required to use a javascript enabled brower to comment here.', 'spam-killer' ) .
			'</noscript>';
		}
	}

	/**
	 * Set CAPTCHA question
	 * 
	 * Uses day of the week and numerical representation
	 * of a nonce to create a simple math question
	 */
	public function set_captcha() {

		// Ignore if user is logged in
		if ( is_user_logged_in() )
			return;

		// First number from date
		$number1 = date( 'N' ); // Grab day of the week
		$number1 = $number1 / 2;
		$number1 = (int) $number1;

		// Second number from nonce
		$string = wp_create_nonce( 'spam-killer' );
		$number2 = ord( $string ); // Convert string to numerical number
		$number2 = $number2 / 20;
		$number2 = (int) $number2;

		$this->captcha_value = $number1 + $number2;
		$this->captcha_question = $number1 . ' + ' . $number2;
	}

	/**
	 * Set spam key
	 * Needs set at init due to using nonces
	 */
	public function set_key() {

		// Ignore if user is logged in
		if ( is_user_logged_in() )
			return;

		// set spam key using home_url() and new nonce as salt
		$string = home_url() . wp_create_nonce( 'spam-killer' );
		$this->spam_key = md5( $string );

	}

	/**
	 * Loading the javascript payload
	 */
	public function load_payload() {

		// Ignore if user is logged in
		if ( is_user_logged_in() )
			return;

		// Load the payload
		wp_enqueue_script(
			'kill_it_dead',
			SPAM_DESTROYER_URL . 'kill.js',
			'',
			'1.2',
			true
		);

		// Set the key as JS variable for use in the payload
		wp_localize_script(
			'kill_it_dead',
			'spam_destroyer',
			array(
				'key' => $this->spam_key
			)
		);

	}

	/**
	 * An extra input field, which is intentionally filled with garble, but will be replaced dynamically with JS later
	 */
	public function extra_input_field() {

		// Ignore if user is logged in
		if ( is_user_logged_in() )
			return;

		echo '<input type="hidden" id="killer_value" name="killer_value" value="' . md5( rand( 0, 999 ) ) . '"/>';
		
		// If low spam protection is set, then only display math question for noscript
		if ( SPAM_DESTROYER_PROTECTION == 'low' ) {
			echo '<noscript>';
		}

		echo '<p class="spam-killer">';
		echo '<label>' . __( 'What is', 'spam-killer' ) . ' ' . $this->captcha_question . '</label>';
		echo '<input type="text" id="killer_captcha" name="killer_captcha" value="" />';
		echo '</p>';

		// If low spam protection is set, then only display math question for noscript
		if ( SPAM_DESTROYER_PROTECTION == 'low' ) {
			echo '</noscript>';
		}

		// Enqueue the payload - placed here so that it is ONLY used when on a page utilizing the plugin
		$this->load_payload();
	}

	/**
	 * Kachomp! Be gone evil demon spam!
	 * Checks if the user is doing something evil
	 * If they're detected as being evil, then the little bastards are killed dead in their tracks!
	 * 
	 * @param array $comment The comment
	 * @return array The comment
	 */
	public function check_for_comment_evilness( $comment ) {

		// If the user is logged in, then they're clearly trusted, so continue without checking
		if ( is_user_logged_in() )
			return $comment;

		$type = $comment['comment_type'];

		// Process trackbacks and pingbacks
		if ( $type == "trackback" || $type == "pingback" ) {

			// Check the website's IP against the url it's sending as a trackback, mark as spam if they don't match
			$server_ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
			$web_ip = gethostbyname( parse_url( $comment['comment_author_url'], PHP_URL_HOST ) );
			if ( $server_ip != $web_ip ) {
				$this->kill_spam_dead( $comment ); // Patchooo! Website IP doesn't match server IP, therefore kill it dead as a pancake :)
			}

			// look for our link in the page itself
			// Work out the link we're looking for
			$permalink = get_permalink( $comment['comment_post_ID'] );
			$permalink = preg_replace( '/\/$/', '', $permalink );

			// Download the trackback/pingback
			$response = wp_remote_get( $comment['comment_author_url'] );
			if ( 200 == $response['response']['code'] ) {
				$page_body = $response['body'];
			} else {
				// BAM! Suck on that sploggers! Page doesn't exist, therefore kill the little bugger dead in it's tracks
				$this->kill_spam_dead( $comment );
			}

			// Look for permalink in trackback/pingback page body
			$pos = strpos( $page_body, $permalink );
			if ( $pos === false ) {
			} else {
				// Whammo! They didn't even mention us, so killing the blighter since it's of no interest to us anyway
				$this->kill_spam_dead( $comment );
			}

		} else {

			// Check for cookies presence
			if ( isset( $_COOKIE[ $this->spam_key ] ) ) {
				// If time not set correctly, then assume it's spam
				if ( $_COOKIE[$this->spam_key] > 1 && ( ( time() - $_COOKIE[$this->spam_key] ) < $this->speed ) ) {
					$this->kill_spam_dead( $comment ); // Something's up, since the commenters cookie time frame doesn't match ours
				}
			} else {
				$this->kill_spam_dead( $comment ); // Ohhhh! Cookie not set, so killing the little dick before it gets through!
			}

			// Set the captcha variable
			if ( isset( $_POST['killer_captcha'] ) ) {
				$killer_captcha = $_POST['killer_captcha'];
			} else {
				$killer_captcha = '';
			}

			// If spam protection set to low, then block comment if captcha and key are not set
			if ( SPAM_DESTROYER_PROTECTION == 'low' ) {

				if (
					$killer_captcha == $this->captcha_value ||
					$_POST['killer_value'] == $this->spam_key
				) {
					// Schweeet! Looks like they're not spam :)
				} else {
					$this->double_check_suspicious_comment( $comment );
				}

			}

			// If spam protection set to low, then block comment if captcha or key are not set
			if ( SPAM_DESTROYER_PROTECTION == 'high' ) {

				if ( $killer_captcha != $this->captcha_value ) {
					$this->failed_captcha( $comment ); // Woops! They failed the captcha, but best not kill it in case they're a real person!
				}
				if ( $_POST['killer_value'] != $this->spam_key ) {
					$this->kill_spam_dead( $comment ); // Ding dong the spam is dead!
				}

			}
		}

		// YAY! It's a miracle! Something actually got listed as a legit comment :) W00P W00P!!!
		return $comment;
	}

	/**
	 * Kills splogger signups, BuddyPress posts and replies and bbPress spammers dead in their tracks
	 * This method is an alternative to pouring kerosine on sploggers and lighting a match.
	 * Checks both the cookie and input key payloads
	 *
	 * @param array $result The result of the registration submission
	 * @return array $result The result of the registration submission
	 */
	public function check_for_post_evilness( $result ) {

		// Ignore if user is logged in
		if ( is_user_logged_in() )
			return $result;

		// Check the hidden input field against the key
		if ( $_POST['killer_value'] != $this->spam_key ) {
			// BAM! And the spam signup is dead :)
			if ( isset( $_POST['bbp_topic_id'] ) ) {
				bbp_add_error('bbp_reply_content', __( 'Sorry, but you have been detected as spam', 'spam-destroyer' ) );
			}
			else {
				$result['errors']->add( 'blogname', '' );
			}
		}

		// Check for cookies presence
		if ( isset( $_COOKIE[ $this->spam_key ] ) ) {
			// If time not set correctly, then assume it's spam
			if ( $_COOKIE[$this->spam_key] > 1 && ( ( time() - $_COOKIE[$this->spam_key] ) < $this->speed ) ) {
				// Something's up, since the commenters cookie time frame doesn't match ours
				$result['errors']->add( 'blogname', '' );
			}
		} else {
			// Cookie not set therefore destroy the evil splogger
			$result['errors']->add( 'blogname', '' );
		}
		return $result;
	}

	/*
	 * Error message for spammers
	 */
	public function spam_commenter_error() {
		$error = __( 'Sorry, but our system automatically detected your message as spam. If you feel this is an error then please contact an administrator.', 'spam-destroyer' );
		wp_die( $error, '403 Forbidden', array( 'response' => 403 ) );
	}

	/**
	 * Error message for those who fail the CAPTCHA
	 */
	public function failed_captcha() {
		$error = __( '<strong>ERROR:</strong> please fill in the math problem.', 'spam-destroyer' );
		wp_die( $error, '403 Forbidden', array( 'response' => 403 ) );
	}

	/**
	 * Be gone evil demon spam!
	 * Kill spam dead in it's tracks :)
	 * 
	 * @param array $comment The comment
	 */
	public function kill_spam_dead( $comment ) {

		// Track statistics
		do_action( 'spam_killed_dead' );

		// Only store spam comments if auto-delete is turned off
		if ( SPAM_DESTROYER_AUTODELETE == false ) {
			$data = array(
				'comment_post_ID'      => (int) $_POST['comment_post_ID'],
				'comment_author'       => esc_html( $_POST['author'] ),
				'comment_author_email' => esc_html( $_POST['email'] ),
				'comment_author_url'   => esc_url( $_POST['url'] ),
				'comment_content'      => esc_html( $_POST['comment'] ),
				'comment_author_IP'    => esc_html( $_SERVER['REMOTE_ADDR'] ),
				'comment_agent'        => esc_html( $_SERVER['HTTP_USER_AGENT'] ),
				'comment_date'         => date( 'Y-m-d H:i:s' ),
				'comment_date_gmt'     => date( 'Y-m-d H:i:s' ),
				'comment_approved'     => 'spam',
			);
			$comment_id = wp_insert_comment( $data );
		}

		// Serve error message
		$this->spam_commenter_error();

	}

}
