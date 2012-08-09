<?php

/**
 * Preparing to launch the spam attack
 * Deploys cookie and sets hidden input field via dynamically generated javascript file
 *
 * This file should never be cached
 * 
 * @since 1.0
 * @author Ryan Hellyer <ryan@pixopoint.com>
 */


/*
 * Quick security check to make sure they aren't up to mischief
 *
 * @since 1.2.1
 * @author Ryan Hellyer <ryan@pixopoint.com>
 */
$key = $_GET['key'];
if ( preg_match( '/[^a-z_\-0-9]/i', $key ) ) {
	die( "Errr, that wasn't a valid key. Ping me via <a href='http://twitter.com/ryanhellyer/'>@ryanhellyer</a> if you believe this should not have occurred" );
}


/*
 * Deploy cookie payload
 *
 * @since 1.0
 * @author Ryan Hellyer <ryan@pixopoint.com>
 */
if ( ! isset( $_COOKIE[ $key ] ) ) {
	@setcookie(
		$key,   // The payload :)
		time(),         // Cookie creation time
		time() + ( 60 * 10 ), // Expiry time, set to 10 mins to prevent the nonce expiry (used to generate the key) from interfering
		'/'             // Available across whole domain
	);
}


/*
 * Execute hidden input field payload
 * This could probably be done without jQuery, but I suck at JS so have no idea how - feel free to provide better code!
 *
 * Code kindly provided by Bjørn Johansen (https://twitter.com/bjornjohansen)
 *
 * @since 1.2.2
 * @author Ryan Hellyer <ryan@pixopoint.com>
 */
echo "jQuery(document).ready(function($) {
	$('input#killer_value').val('" . $key . "');
});
";

