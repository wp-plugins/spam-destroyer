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
	die( "Errr, that wasn't a valid key." );
}


/*
 * Set appropriate mime-type
 *
 * @since 1.2.4
 * @author Ryan Hellyer <ryan@pixopoint.com>
 */
header( 'Content-Type: application/javascript' );


/*
 * Deploy cookie payload
 *
 * @since 1.0
 * @author Ryan Hellyer <ryan@pixopoint.com>
 */
if ( ! isset( $_COOKIE[ esc_attr( $key ) ] ) ) {
	@setcookie(
		$key,   // The payload :)
		time(),         // Cookie creation time
		time() + ( 60 * 10 ), // Expiry time, set to 10 mins to prevent the nonce expiry (used to generate the key) from interfering
		'/'             // Available across whole domain
	);
}


/*
 * Execute hidden input field payload
 *
 * @since 1.2.4
 * @author Bjørn Johansen <https://twitter.com/bjornjohansen>
 */
echo "try {
	document.getElementById('killer_value').value = '" . esc_attr( $key ) . "';
} catch (e) {}";
