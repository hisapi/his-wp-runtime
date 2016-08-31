<?php

	chdir( dirname(dirname(dirname(getcwd()))) );
	echo getcwd();
        define('WP_USE_THEMES', true);
        $wp_did_header = true;
        require_once( getcwd() . '/wp-load.php' );
        wp();
	print_r($wpdb);
