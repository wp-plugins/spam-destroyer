<?php
/*

Plugin Name: Spam Destroyer
Plugin URI: http://geek.ryanhellyer.net/products/spam-destroyer/
Description: Kills spam dead in it's tracks
Author: Ryan Hellyer
Version: 1.3
Author URI: http://geek.ryanhellyer.net/

Copyright (c) 2013 Ryan Hellyer




Based on the following plugins ...

Cookies for Comments by Donncha O Caoimh
http://ocaoimh.ie/cookies-for-comments/

WP Hashcash by Elliot Back
http://wordpress-plugins.feifei.us/hashcash/



This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License version 2 as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
license.txt file included with this plugin for more information.

*/

/*
 *
 *
 ******    NEED TO SET SENSIBLE ERROR MESSAGE FOR WHEN USER FORGETS TO ADD ANSWER TO MATH PROBLEM *****
 *
 *
 *
 * === low ===
 * No JS = Only CAPTCHA
 * With JS = Only Cookie and JS input replacement
 *
 * === high ===
 * No JS = doesn't work
 * With JS = CAPTCHA, Cookie and JS input replacement
 */
//define( 'SPAM_DESTROYER_PROTECTION', 'low' ); // low, medium or high
define( 'SPAM_DESTROYER_PROTECTION', 'high' ); // low, medium or high

/**
 * Spam Destroyer class
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
		add_action( 'comment_form_after_fields',                         array( $this, 'extra_input_field' ) ); // WordPress comments page
		add_action( 'signup_hidden_fields',                 array( $this, 'extra_input_field' ) ); // WordPress multi-site signup page
		add_action( 'bp_after_registration_submit_buttons', array( $this, 'extra_input_field' ) ); // BuddyPress signup page
		add_action( 'bbp_theme_before_topic_form_content',  array( $this, 'extra_input_field' ) ); // bbPress signup page
		add_action( 'bbp_theme_before_reply_form_content',  array( $this, 'extra_input_field' ) ); // bbPress signup page
		add_action( 'register_form',                        array( $this, 'extra_input_field' ) ); // bbPress user registration page
		add_action( 'comment_form_top',                     array( $this, 'error_notice' ) );
	}

	/**
	 * Set CAPTCHA question
	 * 
	 * Uses day of the week and numerical representation
	 * of a nonce to create a simple math question
	 */
	public function set_captcha() {

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

		// set spam key using home_url() and new nonce as salt
		$string = home_url() . wp_create_nonce( 'spam-killer' );
		$this->spam_key = md5( $string );

	}

	/**
	 * Loading the javascript payload
	 */
	public function load_payload() {

		// Load the payload
		wp_enqueue_script(
			'kill_it_dead',
			plugins_url( 'kill.js',  __FILE__ ),
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

		// If high spam protection is set, then non JS users are blocked
		if ( SPAM_DESTROYER_PROTECTION == 'high' ) {
			echo '<noscript>' . __( 'Sorry, but you are required to use a javascript enabled brower to comment here.', 'spam-killer' ) . '</noscript>';
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
					$this->kill_spam_dead( $comment );
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
			return;

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
	 * Redirect spammers
	 * We need to redirect spammers so that we can serve a message telling
	 * them why their post will not appear.
	 *
	 * @param string $location URL for current location
	 * @return string New URL to redirect to
	 */
	function redirect_spam_commenters( $location ){
		$newurl = substr( $location, 0, strpos( $location, "#comment" ) );
		return $newurl . '?spammer=confirm#reply-title';
	}

	/*
	 * Output error notice for spammers
	 */
	public function error_notice() {

		if ( ! isset( $_GET['spammer'] ) )
			return;

		// Display notice
		echo '<div id="comments-error-message"><p>' . __( 'Sorry, but our system has recognised you as a spammer. If you believe this to be an error, please contact us so that we can rectify the situation.', 'spam-destroyer' ) . '</p></div>';
	}

	/**
	 * Deal with users who fail the math problem
	 * 
	 * @param array $comment The comment
	 */
	public function failed_captcha( $comment ) {
		$error = __( 'You forgot to enter the math problem<br /><br />"' . $comment['comment_content'] . '"', 'spam-destroyer' );
		wp_die( $error, '403 Forbidden', array( 'response' => 403 ) );
	}

	/**
	 * Be gone evil demon spam!
	 * Kill spam dead in it's tracks :)
	 * 
	 * @param array $comment The comment
	 * @return array The comment
	 */
	public function kill_spam_dead( $comment ) {

		// Set as spam
		add_filter( 'pre_comment_approved', create_function( '$a', 'return \'spam\';' ) );
		// add_filter( 'comment_post', create_function( '$id', 'wp_delete_comment( $id ); die( \'This comment has been deleted\' );' ) );
		// add_filter( 'pre_comment_approved', create_function( '$a', 'return 0;' ) );

		// If permalinks are on, then redirect to a query var (allows us to provide message in case of false positives - which should never happen, but best be on the safe side!)
		if ( '' != get_option( 'permalink_structure' ) ) {
			add_filter( 'comment_post_redirect', array( $this, 'redirect_spam_commenters' ) );
		}

		// Bypass Akismet since comment is spam
		if ( function_exists( 'akismet_auto_check_comment' ) ) {
			remove_filter( 'preprocess_comment', 'akismet_auto_check_comment', 10 );
		}

		// Return the comment anyway, since it's nice to keep it in the spam queue in case we messed up
		return $comment;

	}

}
$spam_destroyer = new Spam_Destroyer();
