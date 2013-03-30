<?php


/**
 * Tracking of users
 * Boosts open a popup box allow users to choose to be tracked
 * 
 * @copyright Copyright (c), Ryan Hellyer
 * @author Ryan Hellyer <ryanhellyer@gmail.com>
 * @since 1.0
 */
class Spam_Destroyer_Tracking {

	/**
	 * Class constructor
	 */
	public function __construct() {
		//delete_option( 'spam-destroyer-tracking' );
		
		// Bail out immediately if not in admin panel
		if ( ! is_admin() )
			return;

		// Bail out now if tracking turned off already
		if ( 'off' == get_option( 'spam-destroyer-tracking' ) ) {
			return;
		}

		// Hook in tracking check
		add_action( 'admin_print_footer_scripts',   array( $this, 'tracking_request_popup' ) );
		add_action( 'admin_enqueue_scripts',        array( $this, 'admin_enqueue' ) );
		add_action( 'admin_init',                   array( $this, 'process_request' ) );
	}

	/*
	 * Process request
	 */
	public function process_request() {
		if ( ! isset( $_GET['spam_destroyer_allow_tracking'] ) )
			return;

		if (  ! wp_verify_nonce( $_GET['spam_destroyer_nonce'], 'spam-destroyer-tracking' ) ) {
			wp_die( '<strong>Error:</strong> Invalid nonce for "Spam Destroyer allow tracking"' );
		}

		if ( 'yes' == $_GET['spam_destroyer_allow_tracking'] ) {
			add_option( 'spam-destroyer-tracking', 'yes', '', 'yes' );
		} else {
			add_option( 'spam-destroyer-tracking', 'off', '', 'yes' );
		}
	}

	/*
	 * Scripts and styles used in admin panel
	 * Used for admin pointers
	 */
	public function admin_enqueue() {

		// Bail out if user has already chosen whether or not to allow tracking
		if ( '' != get_option( 'spam-destroyer-tracking' ) )
			return;

		// Add pointers
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'jquery-ui' );
		wp_enqueue_script( 'wp-pointer' );
		wp_enqueue_script( 'utils' );
	}

	/**
	 * Shows a popup that asks for permission to allow tracking.
	 *
	 * Code adapted from WordPress SEO by Joose de Valk (http://yoast.com/)
	 */
	function tracking_request_popup() {
		$nonce = wp_create_nonce( 'spam-destroyer-tracking' );
		?>
	<script type="text/javascript">
		//<![CDATA[
		(function ($) {
			var spamdestroyer_pointer_options = {"content":"<h3><?php _e( 'Help improve Spam Destroyer', 'spam-destroyer' ); ?><\/h3><p><?php _e( 'Please allow us to gather usage stats so we can help make Spam Destroyer better.', 'spam-destroyer' ); ?><\/p><p><?php _e( 'Data collected includes website URL and number of spam killed, spam submitted and false positives.', 'spam-destroyer' ); ?><\/p>","position":{"edge":"top","align":"center"}}, setup;

			spamdestroyer_pointer_options = $.extend(spamdestroyer_pointer_options, {
				buttons:function (event, t) {
					button = jQuery('<a id="pointer-close" style="margin-left:5px" class="button-secondary">' + '<?php _e( 'Do not allow tracking', 'spam-destroyer' ); ?>' + '</a>');
					button.bind('click.pointer', function () {
						t.element.pointer('close');
					});
					return button;
				},
				close:function () {
				}
			});

			setup = function () {
				$('#wpadminbar').pointer(spamdestroyer_pointer_options).pointer('open');
					jQuery('#pointer-close').after('<a id="pointer-primary" class="button-primary">' + '<?php _e( 'Allow tracking', 'spam-destroyer' ); ?>' + '</a>');
					jQuery('#pointer-primary').click(function () {
						document.location="<?php echo admin_url(); ?>?spam_destroyer_allow_tracking=yes&spam_destroyer_nonce=<?php echo $nonce; ?>";
					});
					jQuery('#pointer-close').click(function () {
						document.location="<?php echo admin_url(); ?>?&spam_destroyer_allow_tracking=no&spam_destroyer_nonce=<?php echo $nonce; ?>";
					});
				};

			if (spamdestroyer_pointer_options.position && spamdestroyer_pointer_options.position.defer_loading)
				$(window).bind('load.wp-pointers', setup);
			else
				$(document).ready(setup);
		})(jQuery);
		//]]>
	</script><?php
		return;
	}

}
