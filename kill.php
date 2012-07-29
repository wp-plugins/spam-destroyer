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


// Deploy cookie payload
if ( ! isset( $_COOKIE[ $_GET['key'] ] ) ) {
	@setcookie(
		$_GET['key'],   // The payload :)
		time(),         // Cookie creation time
		time() + ( 60 * 10 ), // Expiry time, set to 10 mins to prevent the nonce expiry (used to generate the key) from interfering
		'/'             // Available across whole domain
	);
}


// Execute hidden input field payload (this could probably be done without jQuery, but I suck at JS so have no idea how - feel free to provide better code!)
echo "jQuery(document).ready(function($) {
	$('input#killer_value').val('" . $_GET['key'] . "');
});
";

