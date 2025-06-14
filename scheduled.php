<?php ini_set('display_errors', 0);?>
<?php mysqli_report(MYSQLI_REPORT_OFF);?>
<?php include('includes/helpers/PHPMailerAutoload.php');?>
<?php include('includes/helpers/integrations/zapier/triggers/functions.php');?>
<?php 
	include('includes/config.php');
	//--------------------------------------------------------------//
	function dbConnect() { //Connect to database
	//--------------------------------------------------------------//
	    // Access global variables
	    global $mysqli;
	    global $dbHost;
	    global $dbUser;
	    global $dbPass;
	    global $dbName;
	    global $dbPort;
	    
	    // Attempt to connect to database server
	    if(isset($dbPort)) $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
	    else $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
	
	    // If connection failed...
	    if ($mysqli->connect_error) {
	        fail();
	    }
	    
	    global $charset; mysqli_set_charset($mysqli, isset($charset) ? $charset : "utf8");
	    
	    return $mysqli;
	}
	//--------------------------------------------------------------//
	function fail() { //Database connection fails
	//--------------------------------------------------------------//
	    print 'Database error';
	    exit;
	}
	// connect to database
	dbConnect();
?>
<?php include('includes/helpers/short.php');?>
<?php include('includes/helpers/locale.php');?>
<?php include('includes/helpers/integrations/rules.php');?>
<?php include('includes/helpers/subscription.php');?>
<?php
	//Remove ONLY_FULL_GROUP_BY from sql_mode
	$q = 'SET SESSION sql_mode = ""';
	$r = mysqli_query($mysqli, $q);
	if (!$r) error_log("[Unable to set sql_mode]".mysqli_error($mysqli).': in '.__FILE__.' on line '.__LINE__);

	//setup cron
	$q = 'SELECT id, cron, send_rate, ses_endpoint FROM login LIMIT 1';
	$r = mysqli_query($mysqli, $q);
	if ($r)
	{
	    while($row = mysqli_fetch_array($r))
	    {
			$cron = $row['cron'];
			$userid = $row['id'];
			$send_rate = $row['send_rate'];
			$ses_endpoint = $row['ses_endpoint'];
			
			if($cron==0)
			{
				$q2 = 'UPDATE login SET cron=1 WHERE id = '.$userid;
				$r2 = mysqli_query($mysqli, $q2);
				if ($r2) exit;
			}
	    }  
	}
	
	$the_offset = '';
	$offset = isset($_GET['offset']) ? $_GET['offset'] : '';
	
	function get_google_translate_url($url, $translate_to_lang)
	{
		if($translate_to_lang != '')
		{
			//Get language code
			$lang_code_array = explode('/', $translate_to_lang);
			$lang_code = $lang_code_array[1];
			
			//Return google translate URL		
			return 'https://translate.google.com/translate?js=n&sl=auto&tl='.$lang_code.'&u='.$url;
		}
		else return $url;
	}
	
	//Check campaigns database
	$q = 'SELECT timezone, sent, id, app, userID, to_send, to_send_lists, recipients, timeout_check, send_date, lists, lists_excl, segs, segs_excl, from_name, from_email, reply_to, title, label, plain_text, html_text, query_string, opens_tracking, links_tracking, web_version_lang FROM campaigns WHERE (send_date !="" AND lists !="" AND timezone != "") OR (to_send > recipients) ORDER BY sent DESC';
	$r = mysqli_query($mysqli, $q);
	if ($r && mysqli_num_rows($r) > 0)
	{
	    while($row = mysqli_fetch_array($r))
	    {
	    	//prepare variables
	    	$timezone = $row['timezone'];
			$sent = $row['sent'];
			$campaign_id = $row['id'];
			$app = $row['app'];
			$userID = $row['userID'];
			$send_date = $row['send_date'];
			$email_list = $row['lists'];
			$email_list_excl = $row['lists_excl'];
			$segs = $row['segs'];
			$segs_excl = $row['segs_excl'];
			$time = time();
			$current_recipient_count = $row['recipients'];
			$timeout_check = $row['timeout_check'];
			$from_name = stripslashes($row['from_name']);
	    	$from_email = stripslashes($row['from_email']);
	    	$reply_to = stripslashes($row['reply_to']);
			$title = stripslashes($row['title']);
			$campaign_title = $row['label']=='' ? $title : stripslashes(htmlentities($row['label'],ENT_QUOTES,"UTF-8"));
			$plain_text = stripslashes($row['plain_text']);
			$html = stripslashes($row['html_text']);
			$query_string = stripslashes($row['query_string']);
			$to_send_num = $row['to_send'];
			$to_send = $to_send_num;
			$to_send_lists = $row['to_send_lists'];
			$opens_tracking = $row['opens_tracking'];
			$links_tracking = $row['links_tracking'];
			$web_version_lang = $row['web_version_lang'];
			
			//Set language
			$q_l = 'SELECT login.language FROM campaigns, login WHERE campaigns.id = '.$campaign_id.' AND login.app = campaigns.app';
			$r_l = mysqli_query($mysqli, $q_l);
			if ($r_l && mysqli_num_rows($r_l) > 0) while($row = mysqli_fetch_array($r_l)) $language = $row['language'];
			set_locale($language);
			
			//get user details
			$q2 = 'SELECT s3_key, s3_secret, timezone FROM login WHERE id = '.$userID;
			$r2 = mysqli_query($mysqli, $q2);
			if ($r2)
			{
			    while($row = mysqli_fetch_array($r2))
			    {
					$s3_key = $row['s3_key'];
					$s3_secret = $row['s3_secret'];
					$user_timezone = $row['timezone'];
			    }  
			}
			
			//Set default timezone
			date_default_timezone_set($timezone!='0' && $timezone!='' ? $timezone : $user_timezone);
			
			//convert date tags
			$today = time();
			$day_word = array(_('Sunday'), _('Monday'), _('Tuesday'), _('Wednesday'), _('Thursday'), _('Friday'), _('Saturday'));
			$month_word = array('', _('January'), _('February'), _('March'), _('April'), _('May'), _('June'), _('July'), _('August'), _('September'), _('October'), _('November'), _('December'));
			$currentdaynumber = date('d', $today);
			$currentday = $day_word[date('w', $today)];
			$currentmonthnumber = date('m', $today);
			$currentmonth = $currentmonthnumber==10 ? $month_word[$currentmonthnumber] : $month_word[str_replace('0', '', $currentmonthnumber)];
			$currentyear = date('Y', $today);
			$unconverted_date = array('[currentdaynumber]', '[currentday]', '[currentmonthnumber]', '[currentmonth]', '[currentyear]');
			$converted_date = array($currentdaynumber, $currentday, $currentmonthnumber, $currentmonth, $currentyear);
			
			//get smtp settings
			$q3 = 'SELECT app_name, smtp_host, smtp_port, smtp_ssl, smtp_username, smtp_password, allocated_quota, gdpr_only, custom_domain, custom_domain_protocol, custom_domain_enabled FROM apps WHERE id = '.$app;
			$r3 = mysqli_query($mysqli, $q3);
			if ($r3 && mysqli_num_rows($r3) > 0)
			{
			    while($row = mysqli_fetch_array($r3))
			    {
					$app_name = $row['app_name'];
					$smtp_host = $row['smtp_host'];
					$smtp_port = $row['smtp_port'];
					$smtp_ssl = $row['smtp_ssl'];
					$smtp_username = $row['smtp_username'];
					$smtp_password = $row['smtp_password'];
					$allocated_quota = $row['allocated_quota'];
					$gdpr_line = $row['gdpr_only'] ? 'AND gdpr = 1 ' : '';
					$custom_domain = $row['custom_domain'];
					$custom_domain_protocol = $row['custom_domain_protocol'];
					$custom_domain_enabled = $row['custom_domain_enabled'];
					if($custom_domain!='' && $custom_domain_enabled)
					{
						$parse = parse_url(APP_PATH);
						$domain = $parse['host'];
						$protocol = $parse['scheme'];
						$app_path = str_replace($domain, $custom_domain, APP_PATH);
						$app_path = str_replace($protocol, $custom_domain_protocol, $app_path);
					}
					else $app_path = APP_PATH;
			    }  
			}
			
			//Format sent date and web version for Zapier and Rules
			$sent_formatted = date("D, M d, Y, h:iA", time());
			$web_version = get_google_translate_url($app_path.'/w/'.encrypt_val($campaign_id), $web_version_lang);
			
			if($offset=='') //If sending for the first time
				$main_query = $email_list == 0 ? '' : 'subscribers.list in ('.$email_list.') '; //Include main list query
			else //If resuming
				$main_query = $to_send_lists == '' ? '' : 'subscribers.list in ('.$to_send_lists.') '; //Include main list query
			
			//Include segmentation query
			$seg_query = $main_query != '' && $segs != 0 ? 'OR ' : '';
			$seg_query .= $segs == 0 ? '' : '(subscribers_seg.seg_id IN ('.$segs.')) ';
			
			//Exclude list query
			$exclude_query = $email_list_excl == 0 ? '' : 'subscribers.email NOT IN (SELECT email FROM subscribers WHERE list IN ('.$email_list_excl.')) ';
			
			//Exclude segmentation query
			$exclude_seg_query = $exclude_query != '' && $segs_excl != 0 ? 'AND ' : ''; 
			$exclude_seg_query .= $segs_excl == 0 ? '' : 'subscribers.email NOT IN (SELECT subscribers.email FROM subscribers LEFT JOIN subscribers_seg ON (subscribers.id = subscribers_seg.subscriber_id) WHERE subscribers_seg.seg_id IN ('.$segs_excl.'))';
			
			//check if we should send email now
			if((($time>=$send_date && $time<$send_date+300) && $sent=='') || (($send_date<$time) && $sent=='') || ($send_date=='0' && $timezone=='0'))
			{
				//if resuming
				if($offset!='')
					$q = 'UPDATE campaigns SET send_date=NULL, lists=NULL, timezone=NULL WHERE id = '.$campaign_id;
				else
					$q = 'UPDATE campaigns SET sent = "'.$time.'", send_date=NULL, lists=NULL, timezone=NULL WHERE id = '.$campaign_id;
				$r = mysqli_query($mysqli, $q);
				if ($r){}
				
				//if sending for the first time
				if($offset=='')
				{							
					//If links tracking is enabled, insert links into database
					if($links_tracking)
					{
						//Insert web version link
						if(strpos($html, '</webversion>')==true || strpos($html, '[webversion]')==true)
							mysqli_query($mysqli, 'INSERT INTO links (campaign_id, link) VALUES ('.$campaign_id.', "'.$web_version.'")');
						
						//Insert reconsent link
						if(strpos($html, '[reconsent]')==true)
							mysqli_query($mysqli, 'INSERT INTO links (campaign_id, link) VALUES ('.$campaign_id.', "'.$app_path.'/r?c='.encrypt_val($campaign_id).'")');
						
						//Insert into links
						$links = array();
						//extract all links from HTML
						preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches, PREG_PATTERN_ORDER);
						$matches = array_unique($matches[1]);
						foreach($matches as $var)
						{							
							//If a query_string is set
							if($query_string!='')
							{								
								$var_array = explode('#', $var);
								$url_part_1 = $var_array[0];
								$url_part_2 = $var_array[1];
								$anchor = strpos($var,'#') !== false ? '#' : '';
								if(strpos($var,'?') !== false) $var = $url_part_1.'&'.$query_string.$anchor.$url_part_2;
								else $var = $url_part_1.'?'.$query_string.$anchor.$url_part_2;
							}
							
							if(substr($var, 0, 1)!="#" && substr($var, 0, 6)!="mailto" && substr($var, 0, 3)!="ftp" && substr($var, 0, 3)!="tel" && substr($var, 0, 3)!="sms" && substr($var, 0, 13)!="[unsubscribe]" && substr($var, 0, 12)!="[webversion]" && substr($var, 0, 11)!="[reconsent]" && !strpos($var, 'fonts.googleapis.com') && !strpos($var, 'use.typekit.net') && !strpos($var, 'use.fontawesome.com'))
							{
								$var = str_replace($unconverted_date, $converted_date, $var);
						    	array_push($links, $var);
						    }
						}
						//extract unique links
						for($i=0;$i<count($links);$i++)
						{
						    $q = 'INSERT INTO links (campaign_id, link) VALUES ('.$campaign_id.', "'.$links[$i].'")';
						    $r = mysqli_query($mysqli, $q);
						    if ($r){}
						}
					}
					
					//Get and update number of recipients to send to
					$q  = 'SELECT 1 FROM subscribers';
					$q .= $segs==0 && $segs_excl==0 ? ' ' : ' LEFT JOIN subscribers_seg ON (subscribers.id = subscribers_seg.subscriber_id) ';
					$q .= 'WHERE ('.$main_query.$seg_query.') ';
					$q .= $exclude_query != '' || $exclude_seg_query != '' ? 'AND ('.$exclude_query.$exclude_seg_query.') ' : '';
					$q .= 'AND subscribers.unsubscribed = 0 AND subscribers.bounced = 0 AND subscribers.complaint = 0 AND subscribers.confirmed = 1 '.$gdpr_line.'
						   GROUP BY subscribers.email 
						   ORDER BY subscribers.id ASC 
						   LIMIT 18446744073709551615'.$the_offset;
					$r = mysqli_query($mysqli, $q);
					if ($r)
					{
					    $to_send = mysqli_num_rows($r);	
					    $to_send_num = 	$to_send;
						$q2 = 'UPDATE campaigns SET to_send = '.$to_send.', to_send_lists = "'.$email_list.'" WHERE id = '.$campaign_id;
						$r2 = mysqli_query($mysqli, $q2);
						if ($r2){} 
					}
					
					//Run rules
					$rules_data = array(
					    'trigger' => 'campaign_sending',
						'app_name' => $app_name,
					    'campaign_title' => $campaign_title,
					    'subject' => $title,
					    'from_name' => $from_name,
					    'from_email' => $from_email,
					    'reply_to' => $reply_to,
					    'sent' => $sent_formatted,
					    'no_of_recipients' => $to_send_num,
					    'webversion' => $web_version,
					    'brand_id' => $app,
					    'campaign_id' => $campaign_id,
					    'report_url' => "$app_path/report?i=$app&c=$campaign_id"
				    );
				    
				    //Run rules
					run_rule($rules_data);
				}
				else
				{
					//if resuming					
					$email_list = $to_send_lists;
					//get currently unsubscribed
					$uc = 'SELECT id FROM subscribers WHERE unsubscribed = 1 AND last_campaign = '.$campaign_id;
				    $currently_unsubscribed = mysqli_num_rows(mysqli_query($mysqli, $uc));
				    //get currently bounced
					$bc = 'SELECT id FROM subscribers WHERE bounced = 1 AND last_campaign = '.$campaign_id;
				    $currently_bounced = mysqli_num_rows(mysqli_query($mysqli, $bc));
				    //get currently complaint
					$cc = 'SELECT id FROM subscribers WHERE complaint = 1 AND last_campaign = '.$campaign_id;
				    $currently_complaint = mysqli_num_rows(mysqli_query($mysqli, $cc));
					//calculate offset (offset should exclude currently unsubscribed, bounced or complaint)
					$the_offset = ' OFFSET '.($offset-($currently_unsubscribed+$currently_bounced+$currently_complaint));	
				}
				
				//Replace links in newsletter and put tracking image
				$q  = 'SELECT subscribers.id, subscribers.name, subscribers.email, subscribers.list, subscribers.custom_fields FROM subscribers';
				$q .= $segs==0 && $segs_excl==0 ? ' ' : ' LEFT JOIN subscribers_seg ON (subscribers.id = subscribers_seg.subscriber_id) ';
				$q .= 'WHERE ('.$main_query.$seg_query.') ';
				$q .= $exclude_query != '' || $exclude_seg_query != '' ? 'AND ('.$exclude_query.$exclude_seg_query.') ' : '';
				$q .= 'AND subscribers.unsubscribed = 0 AND subscribers.bounced = 0 AND subscribers.complaint = 0 AND subscribers.confirmed = 1 '.$gdpr_line.'
					   GROUP BY subscribers.email 
					   ORDER BY subscribers.id ASC 
					   LIMIT 18446744073709551615'.$the_offset;
				$r = mysqli_query($mysqli, $q);
				if ($r && mysqli_num_rows($r) > 0)
				{
					$subscriber_id = '';
					$email = '';
					$subscriber_list = '';
					
				    while($row = mysqli_fetch_array($r))
				    {
				    	//prevent execution timeout
				    	set_time_limit(0);
						
						//Check if 'stop campaign' is initiated
						$qc = 'SELECT campaign_stopped, recipients FROM campaigns WHERE id = '.$campaign_id;
						$rc = mysqli_query($mysqli, $qc);
						if ($rc) 
						{
							while($rowc = mysqli_fetch_array($rc)) 
							{
								$campaign_stopped = $rowc['campaign_stopped'];
								$recipients_already_sent = $rowc['recipients'];
							}
							
							//If so,
							if($campaign_stopped==1)
							{								
								//sent timestamp
								$sent_formatted = date("D, M d, Y, h:iA", time());
								
								//Zapier Trigger 'new_campaign_sent' event
								zapier_trigger_new_campaign_sent($title_treated, $from_name, $from_email, $reply_to, $sent_formatted, $web_version, $app);
								
								//Run rules
								$rules_data = array(
									'trigger' => 'campaign_sent',
									'app_name' => $app_name,
									'campaign_title' => $campaign_title,
									'subject' => $title_treated,
									'from_name' => $from_name,
									'from_email' => $from_email,
									'reply_to' => $reply_to,
									'sent' => $sent_formatted,
									'no_of_recipients' => $recipients_already_sent,
									'webversion' => $web_version,
									'brand_id' => $app,
									'campaign_id' => $campaign_id,
									'report_url' => "$app_path/report?i=$app&c=$campaign_id"
								);
								
								//Run rules
								run_rule($rules_data);
								
								//Exit script to stop campaign from sending further
								exit;
							}
						}
				    	
						//Get data
				    	$subscriber_id = $row['id'];
				    	$name = ucfirst(trim($row['name']));
						$email = trim($row['email']);
						$subscriber_list = $row['list'];
						$custom_values = $row['custom_fields'];
						
						//convert date tags
						$html_treated = str_replace($unconverted_date, $converted_date, $html);
						$plain_treated = str_replace($unconverted_date, $converted_date, $plain_text);
						$title_treated = str_replace($unconverted_date, $converted_date, $title);
						
						//replace new links on HTML code
						$q2 = 'SELECT id, link FROM links WHERE campaign_id = '.$campaign_id;
						$r2 = mysqli_query($mysqli, $q2);
						if ($r2 && mysqli_num_rows($r2) > 0)
						{			
						    while($row2 = mysqli_fetch_array($r2))
						    {
						    	$linkID = $row2['id'];
						    	if($query_string!='')
						    	{
							    	$link = (strpos($row2['link'],'?'.$query_string) !== false) ? str_replace('?'.$query_string, '', $row2['link']) : str_replace('&'.$query_string, '', $row2['link']);
						    	}
						    	else $link = $row2['link'];
								
								//If link tracking is enabled, replace links with trackable links
								if($links_tracking)
								{									
									//replace new links on HTML code
							    	$html_treated = str_replace('href="'.$link.'"', 'href="'.$app_path.'/l/'.encrypt_val($subscriber_id).'/'.encrypt_val($linkID).'/'.encrypt_val($campaign_id).'"', $html_treated);
							    	$html_treated = str_replace('href=\''.$link.'\'', 'href="'.$app_path.'/l/'.encrypt_val($subscriber_id).'/'.encrypt_val($linkID).'/'.encrypt_val($campaign_id).'"', $html_treated);
							    	
							    	//replace new links on Plain Text code
							    	$plain_treated = str_replace($link, $app_path.'/l/'.encrypt_val($subscriber_id).'/'.encrypt_val($linkID).'/'.encrypt_val($campaign_id), $plain_treated);
							    }
						    }  
						}
						
						//tags for subject
						preg_match_all('/\[([a-zA-Z0-9!#%^&*()+=$@._\-\:|\/?<>~`"\'\s]+),\s*fallback=/i', $title_treated, $matches_var, PREG_PATTERN_ORDER);
						preg_match_all('/,\s*fallback=([a-zA-Z0-9!,#%^&*()+=$@._\-\:|\/?<>~`"\'\s]*)\]/i', $title_treated, $matches_val, PREG_PATTERN_ORDER);
						preg_match_all('/(\[[a-zA-Z0-9!#%^&*()+=$@._\-\:|\/?<>~`"\'\s]+,\s*fallback=[a-zA-Z0-9!,#%^&*()+=$@._\-\:|\/?<>~`"\'\s]*\])/i', $title_treated, $matches_all, PREG_PATTERN_ORDER);
						preg_match_all('/\[([^\]]+),\s*fallback=/i', $title_treated, $matches_var, PREG_PATTERN_ORDER);
						preg_match_all('/,\s*fallback=([^\]]*)\]/i', $title_treated, $matches_val, PREG_PATTERN_ORDER);
						preg_match_all('/(\[[^\]]+,\s*fallback=[^\]]*\])/i', $title_treated, $matches_all, PREG_PATTERN_ORDER);
						$matches_var = $matches_var[1];
						$matches_val = $matches_val[1];
						$matches_all = $matches_all[1];
						for($i=0;$i<count($matches_var);$i++)
						{   
							$field = $matches_var[$i];
							$fallback = $matches_val[$i];
							$tag = $matches_all[$i];
							
							//if tag is Name
							if($field=='Name')
							{
								if($name=='')
									$title_treated = str_replace($tag, $fallback, $title_treated);
								else
									$title_treated = str_replace($tag, ucfirst($row[strtolower($field)]), $title_treated);
							}
							else //if not 'Name', it's a custom field
							{
								//if subscriber has no custom fields, use fallback
								if($custom_values=='')
									$title_treated = str_replace($tag, $fallback, $title_treated);
								//otherwise, replace custom field tag
								else
								{					
									$q5 = 'SELECT custom_fields FROM lists WHERE id = '.$subscriber_list;
									$r5 = mysqli_query($mysqli, $q5);
									if ($r5)
									{
									    while($row2 = mysqli_fetch_array($r5)) $custom_fields = $row2['custom_fields'];
									    $custom_fields_array = explode('%s%', $custom_fields);
									    $custom_values_array = explode('%s%', $custom_values);
									    $cf_count = count($custom_fields_array);
									    $k = 0;
									    
									    for($j=0;$j<$cf_count;$j++)
									    {
										    $cf_array = explode(':', $custom_fields_array[$j]);
										    $key = str_replace(' ', '', $cf_array[0]);
										    
										    //if tag matches a custom field
										    if($field==$key)
										    {
										    	//if custom field is empty, use fallback
										    	if($custom_values_array[$j]=='')
											    	$title_treated = str_replace($tag, $fallback, $title_treated);
										    	//otherwise, use the custom field value
										    	else
										    	{
										    		//if custom field is of 'Date' type, format the date
										    		if($cf_array[1]=='Date')
											    		$title_treated = str_replace($tag, date("D, M d, Y", $custom_values_array[$j]), $title_treated);
										    		//otherwise just replace tag with custom field value
										    		else
												    	$title_treated = str_replace($tag, $custom_values_array[$j], $title_treated);
										    	}
										    }
										    else
										    	$k++;
									    }
									    if($k==$cf_count)
									    	$title_treated = str_replace($tag, $fallback, $title_treated);
									}
								}
							}
						}
						
						//tags for HTML
						preg_match_all('/\[([a-zA-Z0-9!#%^&*()+=$@._\-\:|\/?<>~`"\'\s]+),\s*fallback=/i', $html_treated, $matches_var, PREG_PATTERN_ORDER);
						preg_match_all('/,\s*fallback=([a-zA-Z0-9!,#%^&*()+=$@._\-\:|\/?<>~`"\'\s]*)\]/i', $html_treated, $matches_val, PREG_PATTERN_ORDER);
						preg_match_all('/(\[[a-zA-Z0-9!#%^&*()+=$@._\-\:|\/?<>~`"\'\s]+,\s*fallback=[a-zA-Z0-9!,#%^&*()+=$@._\-\:|\/?<>~`"\'\s]*\])/i', $html_treated, $matches_all, PREG_PATTERN_ORDER);
						preg_match_all('/\[([^\]]+),\s*fallback=/i', $html_treated, $matches_var, PREG_PATTERN_ORDER);
						preg_match_all('/,\s*fallback=([^\]]*)\]/i', $html_treated, $matches_val, PREG_PATTERN_ORDER);
						preg_match_all('/(\[[^\]]+,\s*fallback=[^\]]*\])/i', $html_treated, $matches_all, PREG_PATTERN_ORDER);
						$matches_var = $matches_var[1];
						$matches_val = $matches_val[1];
						$matches_all = $matches_all[1];
						for($i=0;$i<count($matches_var);$i++)
						{   
							$field = $matches_var[$i];
							$fallback = $matches_val[$i];
							$tag = $matches_all[$i];
							
							//if tag is Name
							if($field=='Name')
							{
								if($name=='')
									$html_treated = str_replace($tag, $fallback, $html_treated);
								else
									$html_treated = str_replace($tag, ucfirst($row[strtolower($field)]), $html_treated);
							}
							else //if not 'Name', it's a custom field
							{
								//if subscriber has no custom fields, use fallback
								if($custom_values=='')
									$html_treated = str_replace($tag, $fallback, $html_treated);
								//otherwise, replace custom field tag
								else
								{					
									$q5 = 'SELECT custom_fields FROM lists WHERE id = '.$subscriber_list;
									$r5 = mysqli_query($mysqli, $q5);
									if ($r5)
									{
									    while($row2 = mysqli_fetch_array($r5)) $custom_fields = $row2['custom_fields'];
									    $custom_fields_array = explode('%s%', $custom_fields);
									    $custom_values_array = explode('%s%', $custom_values);
									    $cf_count = count($custom_fields_array);
									    $k = 0;
									    
									    for($j=0;$j<$cf_count;$j++)
									    {
										    $cf_array = explode(':', $custom_fields_array[$j]);
										    $key = str_replace(' ', '', $cf_array[0]);
										    
										    //if tag matches a custom field
										    if($field==$key)
										    {
										    	//if custom field is empty, use fallback
										    	if($custom_values_array[$j]=='')
											    	$html_treated = str_replace($tag, $fallback, $html_treated);
										    	//otherwise, use the custom field value
										    	else
										    	{
										    		//if custom field is of 'Date' type, format the date
										    		if($cf_array[1]=='Date')
											    		$html_treated = str_replace($tag, date("D, M d, Y", $custom_values_array[$j]), $html_treated);
										    		//otherwise just replace tag with custom field value
										    		else
												    	$html_treated = str_replace($tag, $custom_values_array[$j], $html_treated);
										    	}
										    }
										    else
										    	$k++;
									    }
									    if($k==$cf_count)
									    	$html_treated = str_replace($tag, $fallback, $html_treated);
									}
								}
							}
						}
						//tags for Plain text
						preg_match_all('/\[([a-zA-Z0-9!#%^&*()+=$@._\-\:|\/?<>~`"\'\s]+),\s*fallback=/i', $plain_treated, $matches_var, PREG_PATTERN_ORDER);
						preg_match_all('/,\s*fallback=([a-zA-Z0-9!,#%^&*()+=$@._\-\:|\/?<>~`"\'\s]*)\]/i', $plain_treated, $matches_val, PREG_PATTERN_ORDER);
						preg_match_all('/(\[[a-zA-Z0-9!#%^&*()+=$@._\-\:|\/?<>~`"\'\s]+,\s*fallback=[a-zA-Z0-9!,#%^&*()+=$@._\-\:|\/?<>~`"\'\s]*\])/i', $plain_treated, $matches_all, PREG_PATTERN_ORDER);
						preg_match_all('/\[([^\]]+),\s*fallback=/i', $plain_treated, $matches_var, PREG_PATTERN_ORDER);
						preg_match_all('/,\s*fallback=([^\]]*)\]/i', $plain_treated, $matches_val, PREG_PATTERN_ORDER);
						preg_match_all('/(\[[^\]]+,\s*fallback=[^\]]*\])/i', $plain_treated, $matches_all, PREG_PATTERN_ORDER);
						$matches_var = $matches_var[1];
						$matches_val = $matches_val[1];
						$matches_all = $matches_all[1];
						for($i=0;$i<count($matches_var);$i++)
						{   
							$field = $matches_var[$i];
							$fallback = $matches_val[$i];
							$tag = $matches_all[$i];
							
							//if tag is Name
							if($field=='Name')
							{
								if($name=='')
									$plain_treated = str_replace($tag, $fallback, $plain_treated);
								else
									$plain_treated = str_replace($tag, ucfirst($row[strtolower($field)]), $plain_treated);
							}
							else //if not 'Name', it's a custom field
							{
								//if subscriber has no custom fields, use fallback
								if($custom_values=='')
									$plain_treated = str_replace($tag, $fallback, $plain_treated);
								//otherwise, replace custom field tag
								else
								{					
									$q5 = 'SELECT custom_fields FROM lists WHERE id = '.$subscriber_list;
									$r5 = mysqli_query($mysqli, $q5);
									if ($r5)
									{
									    while($row2 = mysqli_fetch_array($r5)) $custom_fields = $row2['custom_fields'];
									    $custom_fields_array = explode('%s%', $custom_fields);
									    $custom_values_array = explode('%s%', $custom_values);
									    $cf_count = count($custom_fields_array);
									    $k = 0;
									    
									    for($j=0;$j<$cf_count;$j++)
									    {
										    $cf_array = explode(':', $custom_fields_array[$j]);
										    $key = str_replace(' ', '', $cf_array[0]);
										    
										    //if tag matches a custom field
										    if($field==$key)
										    {
										    	//if custom field is empty, use fallback
										    	if($custom_values_array[$j]=='')
													$plain_treated = str_replace($tag, $fallback, $plain_treated);
										    	//otherwise, use the custom field value
										    	else
										    	{
										    		//if custom field is of 'Date' type, format the date
										    		if($cf_array[1]=='Date')
														$plain_treated = str_replace($tag, date("D, M d, Y", $custom_values_array[$j]), $plain_treated);
										    		//otherwise just replace tag with custom field value
										    		else
														$plain_treated = str_replace($tag, $custom_values_array[$j], $plain_treated);
										    	}
										    }
										    else
										    	$k++;
									    }
									    if($k==$cf_count)
									    	$plain_treated = str_replace($tag, $fallback, $plain_treated);
									}
								}
							}
						}
						
						//set web version links
						$wv = get_google_translate_url($app_path.'/w/'.encrypt_val($subscriber_id).'/'.encrypt_val($subscriber_list).'/'.encrypt_val($campaign_id), $web_version_lang);
				    	$html_treated = str_replace('<webversion', '<a href="'.$wv.'" ', $html_treated);
				    	$html_treated = str_replace('</webversion>', '</a>', $html_treated);
				    	$html_treated = str_replace('[webversion]', $wv, $html_treated);
				    	$plain_treated = str_replace('[webversion]', $wv, $plain_treated);
				    	
				    	//set unsubscribe links
						if($smtp_host == 'smtp.elasticemail.com' && $smtp_port!='' && $smtp_username!='' && $smtp_password!='')
						{
							$html_treated = str_replace('<unsubscribe', '<a href="{unsubscribe}" ', $html_treated);
							$html_treated = str_replace('</unsubscribe>', '</a>', $html_treated);
							$html_treated = str_replace('[unsubscribe]', '{unsubscribe}', $html_treated);
							$plain_treated = str_replace('[unsubscribe]', '{unsubscribe}', $plain_treated);
						}
						else
						{
				    		$html_treated = str_replace('<unsubscribe', '<a href="'.$app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($subscriber_list).'/'.encrypt_val($campaign_id).'" ', $html_treated);
				    		$html_treated = str_replace('</unsubscribe>', '</a>', $html_treated);
				    		$html_treated = str_replace('[unsubscribe]', $app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($subscriber_list).'/'.encrypt_val($campaign_id), $html_treated);
				    		$plain_treated = str_replace('[unsubscribe]', $app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($subscriber_list).'/'.encrypt_val($campaign_id), $plain_treated);
						}
				    	
				    	//Name tag
						$html_treated = str_replace('[Name]', $name, $html_treated);
						$plain_treated = str_replace('[Name]', $name, $plain_treated);
						$title_treated = str_replace('[Name]', $name, $title_treated);
						
				    	//Email tag
						$html_treated = str_replace('[Email]', $email, $html_treated);
						$plain_treated = str_replace('[Email]', $email, $plain_treated);
						$title_treated = str_replace('[Email]', $email, $title_treated);
						
						//set reconsent links
				    	$html_treated = str_replace('[reconsent]', $app_path.'/r?e='.encrypt_val($email).'&a='.encrypt_val($app).'&w='.encrypt_val($subscriber_id).'/'.encrypt_val($subscriber_list).'/'.encrypt_val($campaign_id), $html_treated);
				    	$plain_treated = str_replace('[reconsent]', $app_path.'/r?e='.encrypt_val($email).'&a='.encrypt_val($app).'&w='.encrypt_val($subscriber_id).'/'.encrypt_val($subscriber_list).'/'.encrypt_val($campaign_id), $plain_treated);
				    	
				    	//If opens tracking is enabled, add 1 x 1 px tracking image
				    	if($opens_tracking)
				    	{
					    	//add tracking 1 by 1px image
							$html_treated .= '<img src="'.$app_path.'/t/'.encrypt_val($campaign_id).'/'.encrypt_val($subscriber_id).'" alt="" style="width:1px;height:1px;"/>';
						}
						
						//Get server path
						$server_path_array = explode('scheduled.php', $_SERVER['SCRIPT_FILENAME']);
					    $server_path = $server_path_array[0];
					    
						//send email
						$mail = new PHPMailer();
						if($smtp_host!='' && $smtp_port!='' && $smtp_username!='' && $smtp_password!='')
						{
							$mail->IsSMTP();
							$mail->SMTPDebug = 0;
							$mail->SMTPAuth = true;
							$mail->SMTPSecure = $smtp_ssl;
							$mail->Host = $smtp_host;
							$mail->Port = $smtp_port; 
							$mail->Username = $smtp_username;  
							$mail->Password = $smtp_password;
							
							//If sending via ElasticEmail, send subscriber list ID to ElasticEmail so that we can retrieve it via their webhook later
							if($smtp_host == 'smtp.elasticemail.com' && $smtp_port!='' && $smtp_username!='' && $smtp_password!='')
								$mail->AddCustomHeader('X-ElasticEmail-Postback: '.encrypt_val($subscriber_list));
							//If sending via Sendgrid, send subscriber list ID to Sendgrid so that we can retrieve it via their webhook later
							else if($smtp_host == 'smtp.sendgrid.net' && $smtp_port!='' && $smtp_username!='' && $smtp_password!='')
							{
								$sgheaders = json_encode(array('category' => array(encrypt_val($subscriber_list))));
								$mail->AddCustomHeader('X-SMTPAPI: '.$sgheaders);
							}
							else if($smtp_host == 'in-v3.mailjet.com' && $smtp_port!='' && $smtp_username!='' && $smtp_password!='')
								$mail->AddCustomHeader('X-MJ-CustomID: '.encrypt_val($subscriber_list));
						}
						else if($s3_key!='' && $s3_secret!='')
						{
							//if there is an attachment, don't use curl_multi
							if(file_exists($server_path.'uploads/attachments/'.$campaign_id))
								$mail->IsAmazonSES(false, $campaign_id, $subscriber_id, $user_timezone);
							//otherwise send with curl_multi
							else
								$mail->IsAmazonSES(true, $campaign_id, $subscriber_id, $user_timezone, $send_rate);
							$mail->AddAmazonSESKey($s3_key, $s3_secret);
						}
						$mail->Timezone   = $user_timezone;
						$mail->CharSet	  =	"UTF-8";
						$mail->From       = $from_email;
						$mail->FromName   = $from_name;
						$mail->Subject = $title_treated;
						$mail->AltBody = $plain_treated;
						$mail->Body = $html_treated;
						$mail->IsHTML(true);
						$mail->AddAddress($email, $name);
						$mail->AddReplyTo($reply_to, $from_name);
						$mail->AddCustomHeader('List-Unsubscribe-Post: List-Unsubscribe=One-Click');
						$mail->AddCustomHeader('List-Unsubscribe: <'.$app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($subscriber_list).'/'.encrypt_val($campaign_id).'>');
						$mail->AddCustomHeader('Precedence: Bulk');
						//check if attachments are available for this campaign to attach
						if(file_exists($server_path.'uploads/attachments/'.$campaign_id))
						{
							foreach(glob($server_path.'uploads/attachments/'.$campaign_id.'/*') as $attachment){
								if(file_exists($attachment))
								    $mail->AddAttachment($attachment);
							}
						}
						$mail->Send();
						
						//increment recipient count if not using AWS or SMTP
						if(($s3_key=='' && $s3_secret=='') || ($smtp_host!='' && $smtp_port!='' && $smtp_username!='' && $smtp_password!=''))
						{
							//increment recipients number in campaigns table
							$q5 = 'UPDATE campaigns SET recipients = recipients+1 WHERE id = '.$campaign_id;
							mysqli_query($mysqli, $q5);
							
							//update last_campaign
							$q14 = 'UPDATE subscribers SET last_campaign = '.$campaign_id.' WHERE id = '.$subscriber_id;
							mysqli_query($mysqli, $q14);
						}
				    }  
				    
				    //====================== Send remaining in queue ======================//
				    
				    $q4 = 'SELECT id, query_str, http_headers, subscriber_id FROM queue WHERE campaign_id = '.$campaign_id.' AND sent = 0';
				    $r4 = mysqli_query($mysqli, $q4);
				    if ($r4 && mysqli_num_rows($r4) > 0)
				    {
				        while($row = mysqli_fetch_array($r4))
				        {
				        	$request_url = 'https://'.$ses_endpoint;
				    		$queue_id = $row['id'];
				    		$query_str = stripslashes($row['query_str']);
				    		$http_headers = explode("\n", $row['http_headers']);
				    		$subscriber_id = $row['subscriber_id'];
				    		
				    		//send remaining in queue
					        $cr = curl_init();
					        curl_setopt($cr, CURLOPT_URL, $request_url);
					        curl_setopt($cr, CURLOPT_POST, $query_str);
					        curl_setopt($cr, CURLOPT_POSTFIELDS, $query_str);
					        curl_setopt($cr, CURLOPT_HTTPHEADER, $http_headers);
					        curl_setopt($cr, CURLOPT_HEADER, true);
					        curl_setopt($cr, CURLOPT_RETURNTRANSFER, true); 
					        curl_setopt($cr, CURLOPT_SSL_VERIFYHOST, 2);
							curl_setopt($cr, CURLOPT_SSL_VERIFYPEER, 1);
							curl_setopt($cr, CURLOPT_CAINFO, $server_path.'certs/cacert.pem');
					
					        // Make the request and fetch response.
					        $response = curl_exec($cr);
					        
					        //Get message ID from response
					        $messageIDArray = explode('<MessageId>', $response);
					        $messageIDArray2 = explode('</MessageId>', $messageIDArray[1]);
					        $messageID = $messageIDArray2[0];
					        
					        $response_http_status_code = curl_getinfo($cr, CURLINFO_HTTP_CODE);
					        
					        if ($response_http_status_code !== 200)
					        {
						        $q7 = 'SELECT errors FROM campaigns WHERE id = '.$campaign_id;
					        	$r7 = mysqli_query($mysqli, $q7);
					        	if ($r7)
					        	{
					        	    while($row = mysqli_fetch_array($r7))
					        	    {
					        			$errors = $row['errors'];
					        			
					        			if($errors=='')
											$val = $subscriber_id.':'.$response_http_status_code;
										else
										{
											$errors .= ','.$subscriber_id.':'.$response_http_status_code;
											$val = $errors;
										}
					        	    }  
					        	}
			
						        //update campaigns' errors column
						        $q6 = 'UPDATE campaigns SET errors = "'.$val.'" WHERE id = '.$campaign_id;
								mysqli_query($mysqli, $q6);
					        }
					        else
					        {
					        	//increment recipients number in campaigns table
								$q6 = 'UPDATE campaigns SET recipients = recipients+1 WHERE recipients < to_send AND id = '.$campaign_id;
								mysqli_query($mysqli, $q6);
								
								//update record in queue
						        $q5 = 'UPDATE queue SET sent = 1, query_str = NULL WHERE id = '.$queue_id;
								mysqli_query($mysqli, $q5);
								
								//update messageID of subscriber
								$q14 = 'UPDATE subscribers SET messageID = "'.$messageID.'" WHERE id = '.$subscriber_id;
								mysqli_query($mysqli, $q14);
					        }
				        }  
				    }
				    else
				    {
					    $q12 = 'UPDATE campaigns SET to_send = (SELECT recipients) WHERE id = '.$campaign_id;
						$r12 = mysqli_query($mysqli, $q12);
						if ($r12)
						{
							$q13 = 'SELECT recipients FROM campaigns WHERE id = '.$campaign_id;
							$r13 = mysqli_query($mysqli, $q13);
							if ($r13) while($row = mysqli_fetch_array($r13)) $current_recipient_count = $row['recipients'];
							$to_send = $current_recipient_count;
							$to_send_num = $current_recipient_count;
						}
				    }
				    //======================= /Send remaining in queue ======================//
				}
				else
				{
					$q12 = 'UPDATE campaigns SET to_send = '.$current_recipient_count.' WHERE id = '.$campaign_id;
					$r12 = mysqli_query($mysqli, $q12);
					if ($r12)
					{
						$to_send = $current_recipient_count;
						$to_send_num = $current_recipient_count;
					}
				}
				
				//=========================== Post processing ===========================//
				    
			    $q8 = 'SELECT recipients FROM campaigns where id = '.$campaign_id;
			    $r8 = mysqli_query($mysqli, $q8);
			    if ($r8) while($row = mysqli_fetch_array($r8)) $no_of_recipients = $row['recipients'];
			    if($no_of_recipients >= $to_send)
			    {
				    //tags for subject to me
					preg_match_all('/\[([a-zA-Z0-9!#%^&*()+=$@._\-\:|\/?<>~`"\'\s]+),\s*fallback=/i', $title, $matches_var, PREG_PATTERN_ORDER);
					preg_match_all('/,\s*fallback=([a-zA-Z0-9!,#%^&*()+=$@._\-\:|\/?<>~`"\'\s]*)\]/i', $title, $matches_val, PREG_PATTERN_ORDER);
					preg_match_all('/(\[[a-zA-Z0-9!#%^&*()+=$@._\-\:|\/?<>~`"\'\s]+,\s*fallback=[a-zA-Z0-9!,#%^&*()+=$@._\-\:|\/?<>~`"\'\s]*\])/i', $title, $matches_all, PREG_PATTERN_ORDER);
					preg_match_all('/\[([^\]]+),\s*fallback=/i', $title, $matches_var, PREG_PATTERN_ORDER);
					preg_match_all('/,\s*fallback=([^\]]*)\]/i', $title, $matches_val, PREG_PATTERN_ORDER);
					preg_match_all('/(\[[^\]]+,\s*fallback=[^\]]*\])/i', $title, $matches_all, PREG_PATTERN_ORDER);
					$matches_var = $matches_var[1];
					$matches_val = $matches_val[1];
					$matches_all = $matches_all[1];
					for($i=0;$i<count($matches_var);$i++)
					{		
						$field = $matches_var[$i];
						$fallback = $matches_val[$i];
						$tag = $matches_all[$i];
						//for each match, replace tag with fallback
						$title = str_replace($tag, $fallback, $title);
					}
					$title = str_replace('[Name]', $from_name, $title);
					$title = str_replace('[Email]', $from_email, $title);
					$title = str_replace($unconverted_date, $converted_date, $title);
				    
				    if($to_send_num=='' || $to_send>$to_send_num) $to_send_num = $to_send;
				    
				    $q4 = 'UPDATE campaigns SET recipients = '.$to_send_num.' WHERE id = '.$campaign_id;
					mysqli_query($mysqli, $q4);
					
					$q9 = 'DELETE FROM queue WHERE campaign_id = '.$campaign_id;
					mysqli_query($mysqli, $q9);
					
					$q11 = 'SELECT errors, to_send_lists FROM campaigns WHERE id = '.$campaign_id;
					$r11 = mysqli_query($mysqli, $q11);
					if ($r11) 
					{
						while($row = mysqli_fetch_array($r11))
						{
							$error_recipients_ids = $row['errors'];
							$tsl = $row['to_send_lists'];
						}
						
						if($error_recipients_ids=='')
						{
							$q10 = 'UPDATE subscribers SET bounce_soft = 0 WHERE list IN ('.$tsl.')';
							mysqli_query($mysqli, $q10);
						}
						else
						{
							$error_recipients_ids_array = explode(',', $error_recipients_ids);
							$eid_array = array();
							foreach($error_recipients_ids_array as $id_val)
							{
								$id_val_array = explode(':', $id_val);
								array_push($eid_array, $id_val_array[0]);
							}
							$error_recipients_ids = implode(',', $eid_array);
							$q10 = 'UPDATE subscribers SET bounce_soft = 0 WHERE list IN ('.$tsl.') AND id NOT IN ('.$error_recipients_ids.')';
							mysqli_query($mysqli, $q10);
						}
					}
					
					//sent timestamp
					$sent_formatted = date("D, M d, Y, h:iA", time());
					
					//Zapier Trigger 'new_campaign_sent' event
					zapier_trigger_new_campaign_sent($title_treated, $from_name, $from_email, $reply_to, $sent_formatted, $web_version, $app);
					
					//Run rules
					$rules_data = array(
					    'trigger' => 'campaign_sent',
						'app_name' => $app_name,
					    'campaign_title' => $campaign_title,
					    'subject' => $title_treated,
					    'from_name' => $from_name,
					    'from_email' => $from_email,
					    'reply_to' => $reply_to,
					    'sent' => $sent_formatted,
					    'no_of_recipients' => $to_send_num,
					    'webversion' => $web_version,
					    'brand_id' => $app,
					    'campaign_id' => $campaign_id,
					    'report_url' => "$app_path/report?i=$app&c=$campaign_id"
				    );
				    
				    //Run rules
					run_rule($rules_data);
					
					//quit
					exit;
				}
				
			    //========================== /Post processing ===========================//
			}
			
			//check if sending timed out			
			if($current_recipient_count > 0 && $current_recipient_count < $to_send_num && $offset == '')
			{
				//check time out
				$tc_array = is_string($timeout_check) ? explode(':', $timeout_check) : [];
					
				// Initialize default values for $tc_prev
				$tc_prev = '0'; // Set a sensible default value for $tc_prev
				if (isset($tc_array[0])) {
					$tc_prev = $tc_array[0];
				} else {
				    // Log details if $tc_array[0] is missing
				    error_log("Warning: \$tc_array[0] is not set. \$timeout_check value: " . var_export($timeout_check, true));
				    error_log("Debug: \$tc_array = " . var_export($tc_array, true));
			    }
				$tc_now = $current_recipient_count;
				$tc = $tc_now.':'.$tc_prev;
				
				//update status of timeout
				$q = 'UPDATE campaigns SET timeout_check = "'.$tc.'" WHERE id = '.$campaign_id;
				mysqli_query($mysqli, $q);
				
				//compare prev count with current recipients number count
				//if timed out
				if($tc_now == $tc_prev)
				{
					$q = 'UPDATE campaigns SET timeout_check = NULL, send_date = "0", timezone = "0" WHERE id = '.$campaign_id;
					mysqli_query($mysqli, $q);
					
					//continue sending
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_HEADER, 0);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_URL, $app_path.'/scheduled.php?offset='.$current_recipient_count);
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
					$data = curl_exec($ch);
				}
			}
			else if($current_recipient_count == $to_send_num)
			{
				$q = 'UPDATE campaigns SET timeout_check = NULL WHERE id = '.$campaign_id;
				mysqli_query($mysqli, $q);
			}
	    }  
	}
?>