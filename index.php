<?php
/**
 * @package HIS Submission Runtime
 * @version 1.0
 */

/*
Plugin Name: HIS Submission Runtime
Plugin URI: http://wordpress.org/plugins/his-submission-runtime/
Description:  Allows Wordpress posts containing text such as [hf uid=YOURUID secret=YOURSECRET base=http://localhost/his cxml=true s=weather remote=true][/hf], which automatically submits HIS jobs directly from Wordpress on the editor's behalf.  Wordpress Post content is automatically updated when the HIS job finishes processing.
Author: HIS Developers 
Version: 1.0
Author URI: http://humanintelligencesystem.com/
*/


/*

Array
(
    [_wpnonce] => 587db7d231
    [_wp_http_referer] => /wordpress/wp-admin/post.php?post=1&action=edit&message=1
    [user_ID] => 1
    [action] => editpost
    [originalaction] => editpost
    [post_author] => 1
    [post_type] => post
    [original_post_status] => publish
    [referredby] => http://turnkeyapp.net/wordpress/wp-admin/post.php?post=1&action=edit&message=1
    [_wp_original_http_referer] => http://turnkeyapp.net/wordpress/wp-admin/post.php?post=1&action=edit&message=1
    [post_ID] => 1
    [meta-box-order-nonce] => 780975f61d
    [closedpostboxesnonce] => bdb4992def
    [post_title] => Hello world!
    [samplepermalinknonce] => 9748ebcec5
    [content] => [audio]
    [wp-preview] => 
    [hidden_post_status] => publish
    [post_status] => publish
    [hidden_post_password] => 
    [hidden_post_visibility] => public
    [visibility] => public
    [post_password] => 
    [mm] => 04
    [jj] => 21
    [aa] => 2014
    [hh] => 21
    [mn] => 06
    [ss] => 19
    [hidden_mm] => 04
    [cur_mm] => 04
    [hidden_jj] => 21
    [cur_jj] => 22
    [hidden_aa] => 2014
    [cur_aa] => 2014
    [hidden_hh] => 21
    [cur_hh] => 06
    [hidden_mn] => 06
    [cur_mn] => 25
    [original_publish] => Update
    [save] => Update
    [post_format] => 0
    [post_category] => Array
        (
            [0] => 0
            [1] => 1
        )

    [newcategory] => New Category Name
    [newcategory_parent] => -1
    [_ajax_nonce-add-category] => cdf585df0a
    [tax_input] => Array
        (
            [post_tag] => 
        )

    [newtag] => Array
        (
            [post_tag] => 
        )

    [excerpt] => 
    [trackback_url] => 
    [metakeyinput] => 
    [metavalue] => 
    [_ajax_nonce-add-meta] => 50afc90631
    [advanced_view] => 1
    [comment_status] => open
    [ping_status] => open
    [add_comment_nonce] => c92b151396
    [_ajax_fetch_list_nonce] => aad56b0c2f
    [post_name] => hello-world-hello
    [post_author_override] => 1
    [post_mime_type] => 
    [ID] => 1
    [post_content] => [audio]
    [post_excerpt] => 
    [to_ping] => 
)

*/

global $his_submission_runtime_version;
$his_submission_runtime_version="1.0";

function his_submission_runtime_shortcode_handler( $atts, $content = null )
{
	global $wpdb;

	$warning_message = "";
	$job_id = "";

	$all_params_set_ready_to_submit=true;
	$params_required_for_submit = array("uid","secret","base");

	// ADD WARNING MESSAGE IF NEEDED
	foreach ($params_required_for_submit as $required_param)
	{
		if ( !in_array($required_param,array_keys($atts)) || strlen($atts[$required_param])==0 )
		{
			$all_params_set_ready_to_submit=false;
			$warning_message = "<!--WARNING: NOT SUBMITTED; MISSING PARAMETER \"$required_param\"-->";
			break;
		}
	} // END FOREACH (LOOP THROUGH ALL REQUIRED PARAMS)


	if ($all_params_set_ready_to_submit)
	{
		$sets_of_optional_params = array( array("q","s") ); // Q or S, etc.
		foreach ($sets_of_optional_params as $set_of_optionals)
		{
			$found_one_in_set = false;
			foreach ($set_of_optionals as $an_optional)
			{
				if ( in_array($an_optional,array_keys($atts)) && strlen($atts[$an_optional])>0 )
				{
					//$content.="found $an_optional";
					$found_one_in_set = true;
				}
			}
			$list_of_params_str = implode(", ",$set_of_optionals);
			$param_set_warning="<!--WARNING: NOT SUBMITTED; ENTER ONE OF THESE PARAMETERS \"$list_of_params_str\"-->";
			if (!$found_one_in_set)
			{
				$all_params_set_ready_to_submit=false;
				if ( strpos($content,($param_set_warning))===FALSE)
				{
					$warning_message = $param_set_warning;
				}
			}
		} // END FOREAC (LOOP THROUGH ALL OPTIONAL SETS)
	} // END IF (ALMOST READY TO SUBMIT, CHECK OPTIONAL SETS)



	// IF JOB SUBMISSION FAILS, ENTER <!--COMMENT--> ABOUT IT
	$submit_trigger=false;
	$job_submitted = false;
	$job_submission_failed = false;

	// DOES JOB NOT EXIST YET, AND ARE WE IN EDIT MODE?
	if ( (!isset($atts['job'])  || isset($atts['resubmit'])|| isset($atts['submit']) ) && strpos($_SERVER['SCRIPT_NAME'],'wp-admin/post.php')!==FALSE && $all_params_set_ready_to_submit )
	{
		if ( isset($atts['resubmit']) )
		{
			unset($atts['resubmit']);
			$submit_trigger=true;
		}
		if ( isset($atts['submit']) )
		{
			unset($atts['submit']);
			$submit_trigger=true;
		}
		// WE ARE IN EDIT MODE, WHICH IS WHEN THE JOB SHOULD BE SUBMITTED

		// SUBMIT HIS JOB AND GET JOB ID
		// INSERT "POST","POST ID (VALUE)", and "JOB" AS CUSTOM WP TABLE ENTRIES
		//	FOR EACH HF ENTRY IN THIS POST (COULD BE MANY)
		// HIS JOB DOES POSTBACK TO A HIS WP PLUGIN RECEIVER
		// RECEIVER USES JOB ID TO LOOK UP WHICH POST IT IS FOR
		// STORES RESULT IN TABLE (OR ELSEWHERE)
		// WAITS UNTIL ALL UNFINISHED JOBS FOR THIS POST ARE COMPLETED)
		// ONCE ALL JOBS FINISHED, MERGE RESULTS BY INSERTING THEM INSIDE
		// 	THE [HF Q=PLANE]CONTENT HERE[/HF] AREA
		// Q: DO_SHORTCODE ON FINAL RESULT OR NOT? USE SHORTCODE PARAM TO DECIDE

		// CHECK FOR AUTHORIZED RECEIVER

		$get_params = "";
		$get_params_sq = "";
		$get_param_list = array("q","s","uid","secret","remote","short","xml","cxml","use_approved");
		foreach ($get_param_list as $get_param_check)
		{
			if ( isset($atts[$get_param_check]) )
			{
				if ( strlen($get_params)>0 )
				{
					$get_params.="&";
				}
				$get_params.=$get_param_check."=".$atts[$get_param_check];
				if ( ($get_param_check=="s" || $get_param_check=="q") && strlen($get_params_sq)==0 )
				{
					$get_params_sq.=$get_param_check."=".$atts[$get_param_check];
				}
			}
		} // END FOREACH

		$base_query_url_his_location="";
		if ( isset($atts['base']) )
		{
			$base_query_url_his_location=$atts['base'];
		}

		$url = "$base_query_url_his_location?$get_params";
		$url_sq = "$base_query_url_his_location?$get_params_sq";
		
		$post = array();

		$also_exclude_from_post = array("base");
		$not_in_post_array = array_merge($get_param_list,$also_exclude_from_post);
		foreach ($atts as $shortcode_key=>$shortcode_value)
		{
			if ( !in_array($shortcode_key,$not_in_post_array) )
			{
				$post[$shortcode_key]=$shortcode_value;
			}
		} // END FOREACH
		$defaults = array( 
		    CURLOPT_POST => 1, 
		    CURLOPT_HEADER => 0, 
		    CURLOPT_URL => $url, 
		    CURLOPT_FRESH_CONNECT => 1, 
		    CURLOPT_RETURNTRANSFER => 1, 
		    CURLOPT_FORBID_REUSE => 1, 
		    CURLOPT_TIMEOUT => 4, 
		    CURLOPT_FOLLOWLOCATION=>TRUE,
		    CURLOPT_TIMEOUT=>10,
		    CURLOPT_POSTFIELDS => http_build_query($post)
		); 
		// CURL Options
		$options = array();

		//$content .= $url."\n";
		//$content .= var_export($ch,true);
		$job_submitted=false;
		if ($submit_trigger)
		{
			$ch = curl_init(); 
			curl_setopt_array($ch, ($options + $defaults));

			if( !$result = curl_exec($ch) ) 
			{ 
				//trigger_error(curl_error($ch));
				$warning_message = "<!--WARNING: JOB SUBMISSION FAILED: ERROR = ". htmlspecialchars(curl_error($ch))."-->";
				//$content .= curl_error($ch);
				$job_submitted = false;
				$job_submission_failed = true;
			}
			else
			{
				$job_submitted = true;
				$job_submission_failed = false;
	
				$fcontent = $result; //"<success value='JOB-SUBMITTED' job='us-east-node2-win7-x64-instance1@e8cd9fbdef991a8bba5d85ef309f733c3358c8c7'/>";
	
	                        $pattern = "/job='(.*?)'/s";
				preg_match($pattern, trim($fcontent), $matches);
	
				if ( count($matches)==2 )
				{
					// INDEX 1 IS THE SUBMATCH
					$job_id = $matches[1];
					$atts['job']=$job_id;
					$wpdb->insert( $wpdb->prefix."his_post_job_output" , array("ID"=>$_POST['post_ID'],"id_job"=>$job_id,"str_output"=>"") );
		
					//$insert="INSERT INTO ".$wpdb->prefix." (ID,id_job,str_output) VALUES (".intval($_POST['post_ID']).",'".$wpdb->escape($job_id)."','')";
					//$results = $wpdb->query( $insert );
				}
				else
				{
					$job_submitted = false;
					$job_submission_failed = true;
					$warning_message = "<!--WARNING:\n    JOB SUBMISSION FAILED? UNEXPECTED JOB SUBMISSION RESPONSE:\n    ====================\n    ". htmlspecialchars($fcontent)."\n    ====================\n    USE DEFAULT HIS JOB SUBMISSION SUCCESS RESPONSES PLEASE\n-->";
				} // END IF (MATCHED OUTPUT)
	
			} // END IF
			curl_close($ch);

		} // END IF (SUBMIT TRIGGER)
		else
		{
                        $pattern = '/<!--INFO.*?-->/is';
                        $replacement = '';
                        $content = preg_replace($pattern, $replacement, $content);

                        $pattern = '/<!--WARNING.*?-->/is';
                        $replacement = '';
                        $content = preg_replace($pattern, $replacement, $content);

                        $pattern = '/<!--SUCCESS.*?-->/is';
                        $replacement = '';
                        $content = preg_replace($pattern, $replacement, $content);

			$info_message= "<!--INFO:\n    READY TO SUBMIT HIS JOB; ADD \"submit=true\" OR \"resubmit=true\" TO HF SHORTCODE PARAMETERS NOW TO SUBMIT HIS JOB \n    BEFORE SUBMITTING HIS JOB PLEASE CONFIGURE THIS HIS FUNCTION:\n        $url_sq\n    WITH A HTTP OUTPUT EXPRESSION CONTAINING THESE SETTINGS:\n        URL: ".get_home_url()."/wp-content/plugins/his_wp_runtime/receiver.php\n            POST VARIABLE: job = [JID]\n            POST VARIABLE: data = [RAW_OUTPUT] (OR WHATEVER DATA YOU WANT TO COME BACK TO WORDPRESS IN THE END)\n-->";
                        if ( !is_null($content) )
                        {
                                $content = $info_message.$content;
                        }
			else
			{
                                $content = $info_message;
			}

		}

		if ($job_submitted)
		{
			$pattern = '/<!--INFO.*?-->/is';
			$replacement = '';
			$content = preg_replace($pattern, $replacement, $content);

			$pattern = '/<!--WARNING.*?-->/is';
			$replacement = '';
			$content = preg_replace($pattern, $replacement, $content);

			$pattern = '/<!--SUCCESS.*?-->/is';
			$replacement = '';
			$content = preg_replace($pattern, $replacement, $content);

			$job_submission_content = "<!--SUCCESS:\n    HF RESULTS WILL BE INSERTED HERE WHEN JOB HAS COMPLETED\n    JOB ID:\n        $job_id\n    PLEASE CONFIGURE THIS FUNCTION:\n        $url_sq\n    WITH A HTTP OUTPUT EXPRESSION CONTAINING THESE SETTINGS:\n        URL: ".get_home_url()."/wp-content/plugins/his_wp_runtime/receiver.php\n            POST VARIABLE: job = [JID]\n            POST VARIABLE: data = [RAW_OUTPUT] (OR WHATEVER DATA YOU WANT TO COME BACK TO WORDPRESS IN THE END)\n-->";
			if ( !is_null($content)  )
			{
				$content = $job_submission_content.$content;
			}
			else
			{
				$content = $job_submission_content;
			}
		} // END IF (JOB HAS BEEN SUBMITTED)
	} // END IF (SUBMITTED JOB FROM EDIT MODE)


	if ($job_submission_failed || !$all_params_set_ready_to_submit )
	{
		if ( strlen($warning_message)==0 )
		{
			$warning_message = "<!--WARNING: JOB SUBMISSION FAILED-->";
		}
		$pattern = '/<!--INFO.*?-->/is';
		$replacement = '';
		$content = preg_replace($pattern, $replacement, $content);

		$pattern = '/<!--SUCCESS.*?-->/is';
		$replacement = '';
		$content = preg_replace($pattern, $replacement, $content);

		$pattern = '/<!--WARNING.*?-->/is';
		$replacement = '';
		$content = preg_replace($pattern, $replacement, $content);

		if ( is_null($content) || strpos($content,($warning_message))===FALSE )
		{
			$content = $warning_message.$content;
		}
	}

	// VIEW MODE (NOT EDIT MODE); JUST SHOW RESULT CONTENT
	if ( strpos($_SERVER['SCRIPT_NAME'],'wp-admin/post.php')===FALSE )
	{

		if ( strpos($content,"<!--INFO")!==FALSE )
		{
			$pattern = '/<!--INFO.*?-->/is';
			$replacement = '';
			$content = preg_replace($pattern, $replacement, $content);
		}

		if ( strpos($content,"<!--SUCCESS")!==FALSE )
		{
			$pattern = '/<!--SUCCESS.*?-->/is';
			$replacement = '';
			$content = preg_replace($pattern, $replacement, $content);
		}

		if ( strpos($content,"<!--WARNING")!==FALSE )
		{
			$pattern = '/<!--WARNING.*?-->/is';
			$replacement = '';
			$content = preg_replace($pattern, $replacement, $content);
		}

		// DO_SHORTCODE
		if ( isset($atts['do_shortcode']) )
		{
			if (strtolower($atts['do_shortcode'])=="yes" || strtolower($atts['do_shortcode'])=="true" || strtolower($atts['do_shortcode']=="1") )
			{
				return do_shortcode($content);
			}
			else
			{
				return $content;
			}
		}
		else
		{
			return $content;
		}
		// SHOW_GUI ??


	} // END IF (VIEW MODE)


	// WRITE OUT THE UPDATED SHORTCODE STRING
        $sca = "";
        foreach ($atts as $ak=>$av)
	{
		$sca .= $ak."=".$av." ";
	}
	return "[hf ".$sca."]".$content."[/hf]";

} // END FUNCTION

function his_submission_runtime_save_post( $post_id )
{
	if ( ! wp_is_post_revision( $post_id ) )
	{
		remove_action('save_post', 'his_submission_runtime_save_post');	

		$new_content = $_POST['post_content'];
		$new_content = do_shortcode($new_content);

		wp_update_post( array("ID"=>$post_id,"post_content"=>$new_content) );
		add_action('save_post', 'his_submission_runtime_save_post');
	}
} // END FUNCTION
add_action('save_post', 'his_submission_runtime_save_post');
add_shortcode('hf','his_submission_runtime_shortcode_handler');



function his_submission_runtime_install() {
   global $wpdb;
   global $his_submission_runtime_version;

   $table_name = $wpdb->prefix . "his_post_job_output";
      
   $sql = "CREATE TABLE `$table_name` (
  `ID` bigint(20) unsigned NOT NULL DEFAULT 0,
  `id_job` varchar(100) NOT NULL DEFAULT '',
  `str_output` TEXT NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`,`id_job`),
  KEY `id_job` (`id_job`)
) DEFAULT CHARSET=utf8;";

   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
   dbDelta( $sql );
 
   add_option( "his_submission_runtime_version", $his_submission_runtime_version );
}
//add_action('activate_plugin', 'his_submission_runtime_install');
register_activation_hook( __FILE__, 'his_submission_runtime_install' );





?>