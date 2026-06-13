<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$wpcli_wxr_autoloader = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $wpcli_wxr_autoloader ) ) {
	require_once $wpcli_wxr_autoloader;
}

WP_CLI::add_command( 'wxr', 'Camaleaun_WXR_Convert_Command' );
