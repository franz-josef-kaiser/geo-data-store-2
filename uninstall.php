<?php
! defined( 'WP_UNINSTALL_PLUGIN' ) and exit;

global $wpdb;

delete_option( "sc_gds_db_version" );

$wpdb->query( "DROP TABLE ".$wpdb->prefix."geodatastore" );