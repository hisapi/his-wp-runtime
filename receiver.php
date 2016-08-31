<?php

// CHECK FOR CONFIRMATION OF SECURITY FEATURE
// CHECK PROVIDED POST PARAMETER VS HIS JOB TABLE IN WORDPRESS
if ( isset($_POST['data']) && isset($_POST['job']) )
{
        chdir( dirname(dirname(dirname(getcwd()))) );
        define('WP_USE_THEMES', true);
        $wp_did_header = true;
        require_once( getcwd() . '/wp-load.php' );
        wp();
	
	try
{
	$_POST['job']=str_replace("'","",$_POST['job']);
	$_POST['job']=str_replace("\"","",$_POST['job']);
	$_POST['job']=str_replace("`","",$_POST['job']);
	//$receiver_row = $wpdb->get_row("SELECT ID, id_job FROM ".$wpdb->prefix."his_post_job_output WHERE id_job='".$wpdb->escape($_POST['job'])."'", ARRAY_A);
	$receiver_row = $wpdb->get_row("SELECT ID, id_job FROM ".$wpdb->prefix."his_post_job_output WHERE id_job='".$_POST['job']."'", ARRAY_A);
	if ( isset($receiver_row['ID']) )
	{
		$wpdb->replace( $wpdb->prefix."his_post_job_output", array("ID"=>$receiver_row['ID'],"id_job"=>$receiver_row['id_job'],"str_output"=>$_POST['data']));
		echo "SUCCESS";
	}
	else
	{
		echo "ERROR: UNABLE TO FIND JOB ROW; THIS IS NORMAL IF YOU ARE VIEWING IN EDIT MODE";
	}
}
catch (Exception $e)
{
	print_r($e);
}
}
else
{
	echo "ERROR: INVALID DATA";
}

