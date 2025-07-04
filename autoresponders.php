<?php 
	mysqli_report(MYSQLI_REPORT_OFF);
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
<?php include('includes/helpers/PHPMailerAutoload.php');?>
<?php include('includes/helpers/short.php');?>
<?php include('includes/helpers/integrations/rules.php');?>
<?php include('includes/helpers/subscription.php');?>
<?php 	
	//Remove ONLY_FULL_GROUP_BY from sql_mode
	$q = 'SET SESSION sql_mode = ""';
	$r = mysqli_query($mysqli, $q);
	if (!$r) error_log("[Unable to set sql_mode]".mysqli_error($mysqli).': in '.__FILE__.' on line '.__LINE__);
	
	//setup cron
	$q = 'SELECT id, cron_ares, timezone FROM login LIMIT 1';
	$r = mysqli_query($mysqli, $q);
	if ($r)
	{
	    while($row = mysqli_fetch_array($r))
	    {
			$cron = $row['cron_ares'];
			$userid = $row['id'];
			$user_timezone = $row['timezone'];
			
			if($cron==0)
			{
				$q2 = 'UPDATE login SET cron_ares=1 WHERE id = '.$userid;
				$r2 = mysqli_query($mysqli, $q2);
				if ($r2) exit;
			}
	    }  
	}
	
	$today = time();
	
	//Current date & time
	if($user_timezone!='') date_default_timezone_set($user_timezone);
	$time = round($today/60)*60;
	$current_day = date("d", $time);
	$current_month = date("M", $time);
	$current_year = date("Y", $time);
	$current_hour = date("H", $time);
	$current_mins = date("i", $time);
	$current_time = strtotime($current_day.' '.$current_month.' '.$current_year.' '.$current_hour.$current_mins.'H');
	
	//convert date tags
	$currentdaynumber = date('d', $today);
	$currentday = date('l', $today);
	$currentmonthnumber = date('m', $today);
	$currentmonth = date('F', $today);
	$currentyear = date('Y', $today);
	$unconverted_date = array('[currentdaynumber]', '[currentday]', '[currentmonthnumber]', '[currentmonth]', '[currentyear]');
	$converted_date = array($currentdaynumber, $currentday, $currentmonthnumber, $currentmonth, $currentyear);
	
	//get user details
	$q2 = 'SELECT s3_key, s3_secret FROM login ORDER BY id ASC LIMIT 1';
	$r2 = mysqli_query($mysqli, $q2);
	if ($r2)
	{
	    while($row = mysqli_fetch_array($r2))
	    {
			$s3_key = $row['s3_key'];
			$s3_secret = $row['s3_secret'];
	    }  
	}
	
	//Process Type 1 autoresponders (new subscriber)
	$q = 'SELECT ares_emails.id, ares_emails.ares_id, ares_emails.from_name, ares_emails.from_email, ares_emails.reply_to, ares_emails.title, ares_emails.plain_text, ares_emails.html_text, ares_emails.query_string, ares_emails.time_condition, ares_emails.timezone, ares_emails.opens_tracking, ares_emails.links_tracking, ares_emails.segs, ares_emails.segs_excl, ares.list, ares.name as ares_name FROM ares, ares_emails WHERE ares_emails.ares_id = ares.id AND ares.type = 1 AND ares_emails.enabled = 1';
	$r = mysqli_query($mysqli, $q);
	if ($r && mysqli_num_rows($r) > 0)
	{
	    while($row = mysqli_fetch_array($r))
	    {
			$ares_id = $row['id'];
			$ares = $row['ares_id'];
			$ares_name = $row['ares_name'];
			$from_name = $row['from_name']=='' ? '' : stripslashes($row['from_name']);
			$from_email = $row['from_email'];
			$reply_to = $row['reply_to'];
			$title = $row['title']=='' ? '' : stripslashes($row['title']);
			$plain_text = $row['plain_text']=='' ? '' : stripslashes($row['plain_text']);
			$html = $row['html_text']=='' ? '' : stripslashes($row['html_text']);
			$query_string = $row['query_string']=='' ? '' : stripslashes($row['query_string']);
			$time_condition = $row['time_condition'];
			$list = $row['list'];
			$opens_tracking = $row['opens_tracking'];
			$links_tracking = $row['links_tracking'];
			$segs = $row['segs'];
			$segs_excl = $row['segs_excl'];
			
			if($time_condition == 'immediately')
				$time_condition = '+1 minutes';
			
			//get smtp settings & monthly limit
			$q3 = 'SELECT apps.smtp_host, apps.smtp_port, apps.smtp_ssl, apps.smtp_username, apps.smtp_password, apps.allocated_quota, apps.current_quota, apps.id, apps.gdpr_only_ar, apps.custom_domain, apps.custom_domain_protocol, apps.custom_domain_enabled, lists.name FROM lists, apps WHERE apps.id = lists.app AND lists.id = '.$list;
			$r3 = mysqli_query($mysqli, $q3);
			if ($r3 && mysqli_num_rows($r3) > 0)
			{
			    while($row = mysqli_fetch_array($r3))
			    {
					$smtp_host = $row['smtp_host'];
					$smtp_port = $row['smtp_port'];
					$smtp_ssl = $row['smtp_ssl'];
					$smtp_username = $row['smtp_username'];
					$smtp_password = $row['smtp_password'];
					$allocated_quota = $row['allocated_quota'];
					$current_quota = $row['current_quota'];
					$app = $row['id'];
					$gdpr_line = $row['gdpr_only_ar'] ? 'AND gdpr = 1 ' : '';
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
					$list_name = $row['name'];
			    }  
			}
			
			//If links tracking is enabled, insert links into database
			if($links_tracking)
			{
				//Insert web version link
				if(strpos($html, '</webversion>')==true)
				{
					mysqli_query($mysqli, 'INSERT INTO links (ares_emails_id, link) 
									SELECT '.$ares_id.', "'.$app_path.'/w/'.encrypt_val($ares_id).'/a"
									        FROM dual
									        WHERE NOT EXISTS
									        	(SELECT ares_emails_id, link FROM links WHERE ares_emails_id = '.$ares_id.' AND link = "'.$app_path.'/w/'.encrypt_val($ares_id).'/a")');
				}
			
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
					
					if(substr($var, 0, 1)!="#" && substr($var, 0, 6)!="mailto" && substr($var, 0, 3)!="ftp" && substr($var, 0, 3)!="tel" && substr($var, 0, 3)!="sms" && substr($var, 0, 13)!="[unsubscribe]" && substr($var, 0, 12)!="[webversion]" && !strpos($var, 'fonts.googleapis.com') && !strpos($var, 'use.typekit.net') && !strpos($var, 'use.fontawesome.com'))
					{
						$var = str_replace($unconverted_date, $converted_date, $var);
				    	array_push($links, $var);
				    }
				}
				//extract unique links
				for($i=0;$i<count($links);$i++)
				{
				    mysqli_query($mysqli, 'INSERT INTO links (ares_emails_id, link) 
							SELECT '.$ares_id.', "'.$links[$i].'"
							        FROM dual
							        WHERE NOT EXISTS
							        	(SELECT ares_emails_id, link FROM links WHERE ares_emails_id = '.$ares_id.' AND link = "'.$links[$i].'")');
				}
			}
			
			//select subscribers			
			if($segs=='' && $segs_excl=='') //if no segments selected
				$q2 = 'SELECT id, name, email, custom_fields, join_date FROM subscribers WHERE list = '.$list.' AND unsubscribed = 0 AND bounced = 0 AND complaint = 0 AND confirmed = 1 '.$gdpr_line.' AND join_date is not NULL ORDER BY id ASC';
			else //if sending to specific segment(s)
			{
				$segs_condition = $segs=='' ? '' : 'subscribers_seg.seg_id IN ('.$segs.') AND ';
				$segs_excl_condition = $segs_excl=='' ? '' : 'subscribers.email NOT IN (SELECT subscribers.email FROM subscribers LEFT JOIN subscribers_seg ON (subscribers.id = subscribers_seg.subscriber_id) WHERE subscribers_seg.seg_id IN ('.$segs_excl.')) AND ';
				
				$q2 = 
				'SELECT 
					subscribers.id, subscribers.name, subscribers.email, subscribers.custom_fields, subscribers.join_date
				FROM 
					subscribers
				LEFT JOIN
					subscribers_seg
				ON
					subscribers.id = subscribers_seg.subscriber_id
				WHERE 
					'.$segs_condition.' 
					'.$segs_excl_condition.' 
					subscribers.list = '.$list.' AND subscribers.unsubscribed = 0 AND subscribers.bounced = 0 AND subscribers.complaint = 0 AND subscribers.confirmed = 1 '.$gdpr_line.' 
					AND subscribers.join_date is not NULL 
				GROUP BY 
					subscribers.email 
				ORDER BY 
					subscribers.id ASC';
			}
			$r2 = mysqli_query($mysqli, $q2);
			if ($r2 && mysqli_num_rows($r2) > 0)
			{
			    while($row = mysqli_fetch_array($r2))
			    {
			    	$subscriber_id = $row['id'];
					$name = $row['name']=='' ? '' : ucfirst(stripslashes($row['name']));
					$email = $row['email']=='' ? '' : stripslashes($row['email']);
					$custom_fields = $row['custom_fields']=='' ? '' : stripslashes($row['custom_fields']);
					$custom_values = $row['custom_fields']=='' ? '' : stripslashes($row['custom_fields']);
					$join_date = $row['join_date'];
					
					//if user is not added through CSV or line import, continue
					if($join_date != '')
					{
						//add time condition to join_date
						$join_date_with_tc = round(strtotime(date("M d Y h.iA", $join_date).' '.$time_condition)/60)*60;
						
						//if join date matches autoresponder options
						if($time == $join_date_with_tc)
						{
							//SEND EMAIL
							
							//prevent execution timeout
					    	set_time_limit(0);
							
							$html_treated = str_replace($unconverted_date, $converted_date, $html);
							$plain_treated = str_replace($unconverted_date, $converted_date, $plain_text);
							$title_treated = str_replace($unconverted_date, $converted_date, $title);
							
							//replace new links on HTML code
							$ql = 'SELECT id, link FROM links WHERE ares_emails_id = '.$ares_id.' ORDER BY id DESC';
							$rl = mysqli_query($mysqli, $ql);
							if ($rl && mysqli_num_rows($rl) > 0)
							{			
							    while($row2 = mysqli_fetch_array($rl))
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
								    	$html_treated = str_replace('href="'.$link.'"', 'href="'.$app_path.'/l/'.encrypt_val($subscriber_id).'/'.encrypt_val($linkID).'/'.encrypt_val($ares_id).'"', $html_treated);
								    	$html_treated = str_replace('href=\''.$link.'\'', 'href="'.$app_path.'/l/'.encrypt_val($subscriber_id).'/'.encrypt_val($linkID).'/'.encrypt_val($ares_id).'"', $html_treated);
								    	
								    	//replace new links on Plain Text code
								    	$plain_treated = str_replace($link, $app_path.'/l/'.encrypt_val($subscriber_id).'/'.encrypt_val($linkID).'/'.encrypt_val($ares_id), $plain_treated);
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
										$q5 = 'SELECT custom_fields FROM lists WHERE id = '.$list;
										$r5 = mysqli_query($mysqli, $q5);
										if ($r5)
										{
										    while($row2 = mysqli_fetch_array($r5)) $l_custom_fields = $row2['custom_fields'];
										    $custom_fields_array = explode('%s%', $l_custom_fields);
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
										$q5 = 'SELECT custom_fields FROM lists WHERE id = '.$list;
										$r5 = mysqli_query($mysqli, $q5);
										if ($r5)
										{
										    while($row2 = mysqli_fetch_array($r5)) $l_custom_fields = $row2['custom_fields'];
										    $custom_fields_array = explode('%s%', $l_custom_fields);
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
										$q5 = 'SELECT custom_fields FROM lists WHERE id = '.$list;
										$r5 = mysqli_query($mysqli, $q5);
										if ($r5)
										{
										    while($row2 = mysqli_fetch_array($r5)) $l_custom_fields = $row2['custom_fields'];
										    $custom_fields_array = explode('%s%', $l_custom_fields);
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
					    	$html_treated = str_replace('<webversion', '<a href="'.$app_path.'/w/'.encrypt_val($subscriber_id).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a" ', $html_treated);
					    	$html_treated = str_replace('</webversion>', '</a>', $html_treated);
					    	$html_treated = str_replace('[webversion]', $app_path.'/w/'.encrypt_val($subscriber_id).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a', $html_treated);
					    	$plain_treated = str_replace('[webversion]', $app_path.'/w/'.encrypt_val($subscriber_id).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a', $plain_treated);
					    	
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
					    		$html_treated = str_replace('<unsubscribe', '<a href="'.$app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a" ', $html_treated);
					    		$html_treated = str_replace('</unsubscribe>', '</a>', $html_treated);
					    		$html_treated = str_replace('[unsubscribe]', $app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a', $html_treated);
					    		$plain_treated = str_replace('[unsubscribe]', $app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a', $plain_treated);
							}
					    	
					    	//Name tag
							$html_treated = str_replace('[Name]', $name, $html_treated);
							$plain_treated = str_replace('[Name]', $name, $plain_treated);
							$title_treated = str_replace('[Name]', $name, $title_treated);
							
					    	//Email tag
							$html_treated = str_replace('[Email]', $email, $html_treated);
							$plain_treated = str_replace('[Email]', $email, $plain_treated);
							$title_treated = str_replace('[Email]', $email, $title_treated);
					    	
					    	//If opens tracking is enabled, add 1 x 1 px tracking image
					    	if($opens_tracking)
					    	{
						    	//add tracking 1 by 1px image
								$html_treated .= '<img src="'.$app_path.'/t/'.encrypt_val($ares_id).'/'.encrypt_val($subscriber_id).'/a" alt="" style="width:1px;height:1px;"/>';
							}
							
							//send email
							$mail = new PHPMailer();
							if($smtp_host!='' && $smtp_port!='' && $smtp_ssl!='' && $smtp_username!='' && $smtp_password!='')
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
									$mail->AddCustomHeader('X-ElasticEmail-Postback: '.encrypt_val($list));
								//If sending via Sendgrid, send subscriber list ID to Sendgrid so that we can retrieve it via their webhook later
								else if($smtp_host == 'smtp.sendgrid.net' && $smtp_port!='' && $smtp_username!='' && $smtp_password!='')
								{
									$sgheaders = json_encode(array('category' => array(encrypt_val($list))));
									$mail->AddCustomHeader('X-SMTPAPI: '.$sgheaders);
								}
								else if($smtp_host == 'in-v3.mailjet.com' && $smtp_port!='' && $smtp_username!='' && $smtp_password!='')
									$mail->AddCustomHeader('X-MJ-CustomID: '.encrypt_val($list));
							}
							else if($s3_key!='' && $s3_secret!='')
							{
								$mail->IsAmazonSES(false, $ares_id, $subscriber_id, '', 0, true);
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
							$mail->AddCustomHeader('List-Unsubscribe: <'.$app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a>');
							$server_path_array = explode('autoresponders.php', $_SERVER['SCRIPT_FILENAME']);
						    $server_path = $server_path_array[0];
							if(file_exists($server_path.'uploads/attachments/a'.$ares_id))
							{
								foreach(glob($server_path.'uploads/attachments/a'.$ares_id.'/*') as $attachment){
									if(file_exists($attachment))
									    $mail->AddAttachment($attachment);
								}
							}
							$mail->Send();
							
							//Format sent date and web version for Rules
							$sent_formatted = date("D, M d, Y, h:iA", time());
							$web_version = $app_path.'/w/'.encrypt_val($ares_id).'/a';
							
							//Run rules
							$rules_data = array(
							    'trigger' => 'ares_sent',
							    'subject' => $title_treated,
							    'from_name' => $from_name,
							    'from_email' => $from_email,
							    'reply_to' => $reply_to,
							    'to_name' => $name,
							    'to_email' => $email,
							    'sent' => $sent_formatted,
							    'webversion' => $web_version,
							    'list_name' => $list_name,
							    'ares_name' => $ares_name,
							    'list_id' => encrypt_val($list),
							    'ares_id' => $ares,
							    'ares_email_id' => $ares_id,
							    'report_url' => "$app_path/autoresponders-report?i=$app&a=$ares&ae=$ares_id"
						    );
						    
						    //Run rules
							run_rule($rules_data);
								
							$q3 = 'UPDATE ares_emails, subscribers SET ares_emails.recipients = ares_emails.recipients+1, subscribers.last_ares = '.$ares_id.' WHERE ares_emails.id = '.$ares_id.' AND subscribers.id = '.$subscriber_id;
							mysqli_query($mysqli, $q3);
							
							//Update quota if a monthly limit was set
							if($allocated_quota!=-1)
							{
								//if so, update quota
								$updated_quota = $current_quota + $to_send_num;
								$q4 = 'UPDATE apps SET current_quota = current_quota+1 WHERE id = '.$app;
								mysqli_query($mysqli, $q4);
							}
						}
					}
			    }  
			}
	    }  
	}
	
	//Process Type 2 autoresponders (anniversary) *Ignore year, send annually
	$q = 'SELECT ares_emails.id, ares_emails.ares_id, ares_emails.from_name, ares_emails.from_email, ares_emails.reply_to, ares_emails.title, ares_emails.plain_text, ares_emails.html_text, ares_emails.query_string, ares_emails.time_condition, ares_emails.timezone, ares_emails.opens_tracking, ares_emails.links_tracking, ares_emails.segs, ares.list, ares.name as ares_name, custom_field FROM ares, ares_emails WHERE ares_emails.ares_id = ares.id AND ares.type = 2 AND ares_emails.enabled = 1';
	$r = mysqli_query($mysqli, $q);
	if ($r && mysqli_num_rows($r) > 0)
	{
	    while($row = mysqli_fetch_array($r))
	    {
	    	$ares_id = $row['id'];
	    	$ares = $row['ares_id'];
	    	$ares_name = $row['ares_name'];
			$from_name = $row['from_name']=='' ? '' : stripslashes($row['from_name']);
			$from_email = $row['from_email'];
			$reply_to = $row['reply_to'];
			$title = $row['title']=='' ? '' : stripslashes($row['title']);
			$plain_text = $row['plain_text']=='' ? '' : stripslashes($row['plain_text']);
			$html = $row['html_text']=='' ? '' : stripslashes($row['html_text']);
			$query_string = $row['query_string']=='' ? '' : stripslashes($row['query_string']);
			$time_condition = $row['time_condition'];
			$ares_custom_field = $row['custom_field'];
			$list = $row['list'];
			$opens_tracking = $row['opens_tracking'];
			$links_tracking = $row['links_tracking'];
			$segs = $row['segs'];
			
			//get smtp settings & monthly limit
			$q3 = 'SELECT apps.smtp_host, apps.smtp_port, apps.smtp_ssl, apps.smtp_username, apps.smtp_password, apps.allocated_quota, apps.current_quota, apps.id, apps.gdpr_only_ar, apps.custom_domain, apps.custom_domain_protocol, apps.custom_domain_enabled, lists.name FROM lists, apps WHERE apps.id = lists.app AND lists.id = '.$list;
			$r3 = mysqli_query($mysqli, $q3);
			if ($r3 && mysqli_num_rows($r3) > 0)
			{
			    while($row = mysqli_fetch_array($r3))
			    {
					$smtp_host = $row['smtp_host'];
					$smtp_port = $row['smtp_port'];
					$smtp_ssl = $row['smtp_ssl'];
					$smtp_username = $row['smtp_username'];
					$smtp_password = $row['smtp_password'];
					$allocated_quota = $row['allocated_quota'];
					$current_quota = $row['current_quota'];
					$app = $row['id'];
					$gdpr_line = $row['gdpr_only_ar'] ? 'AND subscribers.gdpr = 1 ' : '';
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
					$list_name = $row['name'];
			    }  
			}
			
			//If links tracking is enabled, insert links into database
			if($links_tracking)
			{
				//Insert web version link
				if(strpos($html, '</webversion>')==true)
				{
					mysqli_query($mysqli, 'INSERT INTO links (ares_emails_id, link) 
									SELECT '.$ares_id.', "'.$app_path.'/w/'.encrypt_val($ares_id).'/a"
									        FROM dual
									        WHERE NOT EXISTS
									        	(SELECT ares_emails_id, link FROM links WHERE ares_emails_id = '.$ares_id.' AND link = "'.$app_path.'/w/'.encrypt_val($ares_id).'/a")');
				}
			
				//Insert into links
				$links = array();
				//extract all links from HTML
				preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches, PREG_PATTERN_ORDER);
				$matches = array_unique($matches[1]);
				foreach($matches as $var)
				{    
					$var = $query_string!='' ? ((strpos($var,'?') !== false) ? $var.'&'.$query_string : $var.'?'.$query_string) : $var;
					if(substr($var, 0, 1)!="#" && substr($var, 0, 6)!="mailto" && substr($var, 0, 3)!="ftp" && substr($var, 0, 3)!="tel" && substr($var, 0, 3)!="sms" && substr($var, 0, 13)!="[unsubscribe]" && substr($var, 0, 12)!="[webversion]" && !strpos($var, 'fonts.googleapis.com') && !strpos($var, 'use.typekit.net') && !strpos($var, 'use.fontawesome.com'))
					{
						$var = str_replace($unconverted_date, $converted_date, $var);
				    	array_push($links, $var);
				    }
				}
				//extract unique links
				for($i=0;$i<count($links);$i++)
				{
				    mysqli_query($mysqli, 'INSERT INTO links (ares_emails_id, link) 
							SELECT '.$ares_id.', "'.$links[$i].'"
							        FROM dual
							        WHERE NOT EXISTS
							        	(SELECT ares_emails_id, link FROM links WHERE ares_emails_id = '.$ares_id.' AND link = "'.$links[$i].'")');
				}
			}
			
			//select subscribers
			if($segs=='') //if no segments selected
				$q2 = 'SELECT subscribers.id, subscribers.name, subscribers.email, subscribers.custom_fields, lists.custom_fields as lists_custom_fields FROM subscribers LEFT JOIN lists ON subscribers.list = lists.id WHERE lists.id = '.$list.' AND subscribers.unsubscribed = 0 AND subscribers.bounced = 0 AND subscribers.complaint = 0 AND subscribers.confirmed = 1 '.$gdpr_line.' ORDER BY subscribers.id ASC';
			else //if sending to specific segment(s)
			{
				$segs_condition = $segs=='' ? '' : 'subscribers_seg.seg_id IN ('.$segs.') AND ';
				$segs_excl_condition = $segs_excl=='' ? '' : 'subscribers.email NOT IN (SELECT subscribers.email FROM subscribers LEFT JOIN subscribers_seg ON (subscribers.id = subscribers_seg.subscriber_id) WHERE subscribers_seg.seg_id IN ('.$segs_excl.')) AND ';
				
				$q2 = 
				'SELECT 
					subscribers.id, subscribers.name, subscribers.email, subscribers.custom_fields, lists.custom_fields as lists_custom_fields
				FROM 
					subscribers
				LEFT JOIN
					lists
				ON
					subscribers.list = lists.id
				LEFT JOIN
					subscribers_seg
				ON
					subscribers.id = subscribers_seg.subscriber_id
				WHERE 
					'.$segs_condition.' 
					'.$segs_excl_condition.' 
					lists.id = '.$list.' AND subscribers.unsubscribed = 0 AND subscribers.bounced = 0 AND subscribers.complaint = 0 AND subscribers.confirmed = 1 '.$gdpr_line.' 
				GROUP BY 
					subscribers.email 
				ORDER BY 
					subscribers.id ASC';
			}
			$r2 = mysqli_query($mysqli, $q2);
			if ($r2 && mysqli_num_rows($r2) > 0)
			{
				$custom_fields = '';
				
			    while($row = mysqli_fetch_array($r2))
			    {
			    	$subscriber_id = $row['id'];
					$name = $row['name']=='' ? '' : ucfirst(stripslashes($row['name']));
					$email = $row['email']=='' ? '' : stripslashes($row['email']);
					$custom_fields = $row['custom_fields']=='' ? '' : stripslashes($row['custom_fields']);
					$custom_values = $row['custom_fields']=='' ? '' : stripslashes($row['custom_fields']);
					$list_custom_fields = $row['lists_custom_fields']=='' ? '' : stripslashes($row['lists_custom_fields']);
					
					//check if subscriber has custom_fields
					if($custom_fields != '')
					{
						$list_custom_fields_array = explode('%s%', $list_custom_fields);
						$custom_fields_array = explode('%s%', $custom_fields);
						
						$i = 0;
						foreach($list_custom_fields_array as $lcf)
						{
							$lcf_array = explode(':', $lcf);
							
							if($lcf_array[0] == $ares_custom_field && $lcf_array[1]=='Date')
							{
								if($custom_fields_array[$i]!='')
								{
									date_default_timezone_set($user_timezone);
									$cf_day = date("d", $custom_fields_array[$i]);
									$cf_month = date("M", $custom_fields_array[$i]);
									$cf_year = $current_year;
									$cf_hour = '00';
									$cf_mins = '00';
									$cf_time = strtotime($cf_day.' '.$cf_month.' '.$cf_year.' '.$cf_hour.'.'.$cf_mins.' '.$time_condition);
									
									//if current time matches autoresponder options
									if($current_time == $cf_time)
									{
										//SEND EMAIL
								
										//prevent execution timeout
								    	set_time_limit(0);
								    	
								    	$html_treated = str_replace($unconverted_date, $converted_date, $html);
										$plain_treated = str_replace($unconverted_date, $converted_date, $plain_text);
										$title_treated = str_replace($unconverted_date, $converted_date, $title);
										
										//replace new links on HTML code
										$ql = 'SELECT id, link FROM links WHERE ares_emails_id = '.$ares_id.' ORDER BY id DESC';
										$rl = mysqli_query($mysqli, $ql);
										if ($rl && mysqli_num_rows($rl) > 0)
										{			
										    while($row2 = mysqli_fetch_array($rl))
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
											    	$html_treated = str_replace('href="'.$link.'"', 'href="'.$app_path.'/l/'.encrypt_val($subscriber_id).'/'.encrypt_val($linkID).'/'.encrypt_val($ares_id).'"', $html_treated);
											    	$html_treated = str_replace('href=\''.$link.'\'', 'href="'.$app_path.'/l/'.encrypt_val($subscriber_id).'/'.encrypt_val($linkID).'/'.encrypt_val($ares_id).'"', $html_treated);
											    	
											    	//replace new links on Plain Text code
											    	$plain_treated = str_replace($link, $app_path.'/l/'.encrypt_val($subscriber_id).'/'.encrypt_val($linkID).'/'.encrypt_val($ares_id), $plain_treated);
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
													$q5 = 'SELECT custom_fields FROM lists WHERE id = '.$list;
													$r5 = mysqli_query($mysqli, $q5);
													if ($r5)
													{
													    while($row2 = mysqli_fetch_array($r5)) $l_custom_fields = $row2['custom_fields'];
													    $custom_fields_array = explode('%s%', $l_custom_fields);
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
													$q5 = 'SELECT custom_fields FROM lists WHERE id = '.$list;
													$r5 = mysqli_query($mysqli, $q5);
													if ($r5)
													{
													    while($row2 = mysqli_fetch_array($r5)) $l_custom_fields = $row2['custom_fields'];
													    $custom_fields_array = explode('%s%', $l_custom_fields);
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
													$q5 = 'SELECT custom_fields FROM lists WHERE id = '.$list;
													$r5 = mysqli_query($mysqli, $q5);
													if ($r5)
													{
													    while($row2 = mysqli_fetch_array($r5)) $l_custom_fields = $row2['custom_fields'];
													    $custom_fields_array = explode('%s%', $l_custom_fields);
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
								    	$html_treated = str_replace('<webversion', '<a href="'.$app_path.'/w/'.encrypt_val($subscriber_id).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a" ', $html_treated);
								    	$html_treated = str_replace('</webversion>', '</a>', $html_treated);
								    	$html_treated = str_replace('[webversion]', $app_path.'/w/'.encrypt_val($subscriber_id).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a', $html_treated);
								    	$plain_treated = str_replace('[webversion]', $app_path.'/w/'.encrypt_val($subscriber_id).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a', $plain_treated);
								    	
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
								    		$html_treated = str_replace('<unsubscribe', '<a href="'.$app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a" ', $html_treated);
								    		$html_treated = str_replace('</unsubscribe>', '</a>', $html_treated);
								    		$html_treated = str_replace('[unsubscribe]', $app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a', $html_treated);
								    		$plain_treated = str_replace('[unsubscribe]', $app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a', $plain_treated);
										}
								    	
								    	//Name tag
										$html_treated = str_replace('[Name]', $name, $html_treated);
										$plain_treated = str_replace('[Name]', $name, $plain_treated);
										$title_treated = str_replace('[Name]', $name, $title_treated);
										
								    	//Email tag
										$html_treated = str_replace('[Email]', $email, $html_treated);
										$plain_treated = str_replace('[Email]', $email, $plain_treated);
										$title_treated = str_replace('[Email]', $email, $title_treated);
								    	
								    	//If opens tracking is enabled, add 1 x 1 px tracking image
								    	if($opens_tracking)
								    	{
									    	//add tracking 1 by 1px image
											$html_treated .= '<img src="'.$app_path.'/t/'.encrypt_val($ares_id).'/'.encrypt_val($subscriber_id).'/a" alt="" style="width:1px;height:1px;"/>';
										}
										
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
												$mail->AddCustomHeader('X-ElasticEmail-Postback: '.encrypt_val($list));
											//If sending via Sendgrid, send subscriber list ID to Sendgrid so that we can retrieve it via their webhook later
											else if($smtp_host == 'smtp.sendgrid.net' && $smtp_port!='' && $smtp_username!='' && $smtp_password!='')
											{
												$sgheaders = json_encode(array('category' => array(encrypt_val($list))));
												$mail->AddCustomHeader('X-SMTPAPI: '.$sgheaders);
											}
											else if($smtp_host == 'in-v3.mailjet.com' && $smtp_port!='' && $smtp_username!='' && $smtp_password!='')
												$mail->AddCustomHeader('X-MJ-CustomID: '.encrypt_val($list));
										}
										else if($s3_key!='' && $s3_secret!='')
										{
											$mail->IsAmazonSES(false, $ares_id, $subscriber_id, '', 0, true);
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
										$mail->AddCustomHeader('List-Unsubscribe: <'.$app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a>');
										$server_path_array = explode('autoresponders.php', $_SERVER['SCRIPT_FILENAME']);
									    $server_path = $server_path_array[0];
										if(file_exists($server_path.'uploads/attachments/a'.$ares_id))
										{
											foreach(glob($server_path.'uploads/attachments/a'.$ares_id.'/*') as $attachment){
												if(file_exists($attachment))
												    $mail->AddAttachment($attachment);
											}
										}
										$mail->Send();
										
										//Format sent date and web version for Rules
										$sent_formatted = date("D, M d, Y, h:iA", time());
										$web_version = $app_path.'/w/'.encrypt_val($ares_id).'/a';
										
										//Run rules
										$rules_data = array(
										    'trigger' => 'ares_sent',
										    'subject' => $title_treated,
										    'from_name' => $from_name,
										    'from_email' => $from_email,
										    'reply_to' => $reply_to,
										    'to_name' => $name,
										    'to_email' => $email,
										    'sent' => $sent_formatted,
										    'webversion' => $web_version,
										    'list_name' => $list_name,
										    'ares_name' => $ares_name,
										    'list_id' => encrypt_val($list),
										    'ares_id' => $ares,
										    'ares_email_id' => $ares_id,
										    'report_url' => "$app_path/autoresponders-report?i=$app&a=$ares&ae=$ares_id"
									    );
									    
									    //Run rules
										run_rule($rules_data);
											
										$q3 = 'UPDATE ares_emails, subscribers SET ares_emails.recipients = ares_emails.recipients+1, subscribers.last_ares = '.$ares_id.' WHERE ares_emails.id = '.$ares_id.' AND subscribers.id = '.$subscriber_id;
										mysqli_query($mysqli, $q3);
										
										//Update quota if a monthly limit was set
										if($allocated_quota!=-1)
										{
											//if so, update quota
											$updated_quota = $current_quota + $to_send_num;
											$q4 = 'UPDATE apps SET current_quota = current_quota+1 WHERE id = '.$app;
											mysqli_query($mysqli, $q4);
										}
									}
								}								
							}
							$i++;
						}
					}
			    }  
			}
	    }  
	}
	
	//Process Type 3 autoresponders (send at a specific date)
	$q = 'SELECT ares_emails.id, ares_emails.ares_id, ares_emails.from_name, ares_emails.from_email, ares_emails.reply_to, ares_emails.title, ares_emails.plain_text, ares_emails.html_text, ares_emails.query_string, ares_emails.time_condition, ares_emails.timezone, ares_emails.opens_tracking, ares_emails.links_tracking, ares_emails.segs, ares.list, ares.name as ares_name, custom_field FROM ares, ares_emails WHERE ares_emails.ares_id = ares.id AND ares.type = 3 AND ares_emails.enabled = 1';
	$r = mysqli_query($mysqli, $q);
	if ($r && mysqli_num_rows($r) > 0)
	{
	    while($row = mysqli_fetch_array($r))
	    {
	    	$ares_id = $row['id'];
	    	$ares = $row['ares_id'];
	    	$ares_name = $row['ares_name'];
			$from_name = $row['from_name']=='' ? '' : stripslashes($row['from_name']);
			$from_email = $row['from_email'];
			$reply_to = $row['reply_to'];
			$title = $row['title']=='' ? '' : stripslashes($row['title']);
			$plain_text = $row['plain_text']=='' ? '' : stripslashes($row['plain_text']);
			$html = $row['html_text']=='' ? '' : stripslashes($row['html_text']);
			$query_string = $row['query_string']=='' ? '' : stripslashes($row['query_string']);
			$time_condition = $row['time_condition'];
			$ares_custom_field = $row['custom_field'];
			$list = $row['list'];
			$opens_tracking = $row['opens_tracking'];
			$links_tracking = $row['links_tracking'];
			$segs = $row['segs'];
			
			//get smtp settings & monthly limit
			$q3 = 'SELECT apps.smtp_host, apps.smtp_port, apps.smtp_ssl, apps.smtp_username, apps.smtp_password, apps.allocated_quota, apps.current_quota, apps.id, apps.gdpr_only_ar, apps.custom_domain, apps.custom_domain_protocol, apps.custom_domain_enabled, lists.name FROM lists, apps WHERE apps.id = lists.app AND lists.id = '.$list;
			$r3 = mysqli_query($mysqli, $q3);
			if ($r3 && mysqli_num_rows($r3) > 0)
			{
			    while($row = mysqli_fetch_array($r3))
			    {
					$smtp_host = $row['smtp_host'];
					$smtp_port = $row['smtp_port'];
					$smtp_ssl = $row['smtp_ssl'];
					$smtp_username = $row['smtp_username'];
					$smtp_password = $row['smtp_password'];
					$allocated_quota = $row['allocated_quota'];
					$current_quota = $row['current_quota'];
					$app = $row['id'];
					$gdpr_line = $row['gdpr_only_ar'] ? 'AND subscribers.gdpr = 1 ' : '';
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
					$list_name = $row['name'];
			    }  
			}
			
			//If links tracking is enabled, insert links into database
			if($links_tracking)
			{
				//Insert web version link
				if(strpos($html, '</webversion>')==true)
				{
					mysqli_query($mysqli, 'INSERT INTO links (ares_emails_id, link) 
									SELECT '.$ares_id.', "'.$app_path.'/w/'.encrypt_val($ares_id).'/a"
									        FROM dual
									        WHERE NOT EXISTS
									        	(SELECT ares_emails_id, link FROM links WHERE ares_emails_id = '.$ares_id.' AND link = "'.$app_path.'/w/'.encrypt_val($ares_id).'/a")');
				}
			
				//Insert into links
				$links = array();
				//extract all links from HTML
				preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches, PREG_PATTERN_ORDER);
				$matches = array_unique($matches[1]);
				foreach($matches as $var)
				{
					$var = $query_string!='' ? ((strpos($var,'?') !== false) ? $var.'&'.$query_string : $var.'?'.$query_string) : $var;    
					if(substr($var, 0, 1)!="#" && substr($var, 0, 6)!="mailto" && substr($var, 0, 3)!="ftp" && substr($var, 0, 3)!="tel" && substr($var, 0, 3)!="sms" && substr($var, 0, 13)!="[unsubscribe]" && substr($var, 0, 12)!="[webversion]" && !strpos($var, 'fonts.googleapis.com') && !strpos($var, 'use.typekit.net') && !strpos($var, 'use.fontawesome.com'))
					{
						$var = str_replace($unconverted_date, $converted_date, $var);
				    	array_push($links, $var);
				    }
				}
				//extract unique links
				for($i=0;$i<count($links);$i++)
				{
				    mysqli_query($mysqli, 'INSERT INTO links (ares_emails_id, link) 
							SELECT '.$ares_id.', "'.$links[$i].'"
							        FROM dual
							        WHERE NOT EXISTS
							        	(SELECT ares_emails_id, link FROM links WHERE ares_emails_id = '.$ares_id.' AND link = "'.$links[$i].'")');
				}
			}
			
			//select subscribers
			if($segs=='') //if no segments selected
				$q2 = 'SELECT subscribers.id, subscribers.name, subscribers.email, subscribers.custom_fields, lists.custom_fields as lists_custom_fields FROM subscribers LEFT JOIN lists ON subscribers.list = lists.id WHERE lists.id = '.$list.' AND subscribers.unsubscribed = 0 AND subscribers.bounced = 0 AND subscribers.complaint = 0 AND subscribers.confirmed = 1 '.$gdpr_line.' ORDER BY subscribers.id ASC';
			else //if sending to specific segment(s)
			{
				$segs_condition = $segs=='' ? '' : 'subscribers_seg.seg_id IN ('.$segs.') AND ';
				$segs_excl_condition = $segs_excl=='' ? '' : 'subscribers.email NOT IN (SELECT subscribers.email FROM subscribers LEFT JOIN subscribers_seg ON (subscribers.id = subscribers_seg.subscriber_id) WHERE subscribers_seg.seg_id IN ('.$segs_excl.')) AND ';
				
				$q2 = 
				'SELECT 
					subscribers.id, subscribers.name, subscribers.email, subscribers.custom_fields, lists.custom_fields as lists_custom_fields
				FROM 
					subscribers
				LEFT JOIN
					lists
				ON
					subscribers.list = lists.id
				LEFT JOIN
					subscribers_seg
				ON
					subscribers.id = subscribers_seg.subscriber_id
				WHERE 
					'.$segs_condition.' 
					'.$segs_excl_condition.' 
					lists.id = '.$list.' AND subscribers.unsubscribed = 0 AND subscribers.bounced = 0 AND subscribers.complaint = 0 AND subscribers.confirmed = 1 '.$gdpr_line.' 
				GROUP BY 
					subscribers.email 
				ORDER BY 
					subscribers.id ASC';
			}
			$r2 = mysqli_query($mysqli, $q2);
			if ($r2 && mysqli_num_rows($r2) > 0)
			{
				$custom_fields = '';
				
			    while($row = mysqli_fetch_array($r2))
			    {
			    	$subscriber_id = $row['id'];
					$name = $row['name']=='' ? '' : ucfirst(stripslashes($row['name']));
					$email = $row['email']=='' ? '' : stripslashes($row['email']);
					$custom_fields = $row['custom_fields']=='' ? '' : stripslashes($row['custom_fields']);
					$custom_values = $row['custom_fields']=='' ? '' : stripslashes($row['custom_fields']);
					$list_custom_fields = $row['lists_custom_fields']=='' ? '' : stripslashes($row['lists_custom_fields']);
					
					//check if subscriber has custom_fields
					if($custom_fields != '')
					{
						$list_custom_fields_array = explode('%s%', $list_custom_fields);
						$custom_fields_array = explode('%s%', $custom_fields);
						
						$i = 0;
						foreach($list_custom_fields_array as $lcf)
						{
							$lcf_array = explode(':', $lcf);
							
							if($lcf_array[0] == $ares_custom_field && $lcf_array[1]=='Date')
							{
								if($custom_fields_array[$i]!='')
								{
									date_default_timezone_set($user_timezone);
									$cf_day = date("d", $custom_fields_array[$i]);
									$cf_month = date("M", $custom_fields_array[$i]);
									$cf_year = date("Y", $custom_fields_array[$i]);
									$cf_hour = '00';
									$cf_mins = '00';
									$cf_time = strtotime($cf_day.' '.$cf_month.' '.$cf_year.' '.$cf_hour.'.'.$cf_mins.' '.$time_condition);
									
									//if current time matches autoresponder options
									if($current_time == $cf_time)
									{
										//SEND EMAIL
										
										//prevent execution timeout
								    	set_time_limit(0);
								    	
								    	$html_treated = str_replace($unconverted_date, $converted_date, $html);
										$plain_treated = str_replace($unconverted_date, $converted_date, $plain_text);
										$title_treated = str_replace($unconverted_date, $converted_date, $title);
										
										//replace new links on HTML code
										$ql = 'SELECT id, link FROM links WHERE ares_emails_id = '.$ares_id.' ORDER BY id DESC';
										$rl = mysqli_query($mysqli, $ql);
										if ($rl && mysqli_num_rows($rl) > 0)
										{			
										    while($row2 = mysqli_fetch_array($rl))
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
											    	$html_treated = str_replace('href="'.$link.'"', 'href="'.$app_path.'/l/'.encrypt_val($subscriber_id).'/'.encrypt_val($linkID).'/'.encrypt_val($ares_id).'"', $html_treated);
											    	$html_treated = str_replace('href=\''.$link.'\'', 'href="'.$app_path.'/l/'.encrypt_val($subscriber_id).'/'.encrypt_val($linkID).'/'.encrypt_val($ares_id).'"', $html_treated);
											    	
											    	//replace new links on Plain Text code
											    	$plain_treated = str_replace($link, $app_path.'/l/'.encrypt_val($subscriber_id).'/'.encrypt_val($linkID).'/'.encrypt_val($ares_id), $plain_treated);
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
													$q5 = 'SELECT custom_fields FROM lists WHERE id = '.$list;
													$r5 = mysqli_query($mysqli, $q5);
													if ($r5)
													{
													    while($row2 = mysqli_fetch_array($r5)) $l_custom_fields = $row2['custom_fields'];
													    $custom_fields_array = explode('%s%', $l_custom_fields);
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
													$q5 = 'SELECT custom_fields FROM lists WHERE id = '.$list;
													$r5 = mysqli_query($mysqli, $q5);
													if ($r5)
													{
													    while($row2 = mysqli_fetch_array($r5)) $l_custom_fields = $row2['custom_fields'];
													    $custom_fields_array = explode('%s%', $l_custom_fields);
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
													$q5 = 'SELECT custom_fields FROM lists WHERE id = '.$list;
													$r5 = mysqli_query($mysqli, $q5);
													if ($r5)
													{
													    while($row2 = mysqli_fetch_array($r5)) $l_custom_fields = $row2['custom_fields'];
													    $custom_fields_array = explode('%s%', $l_custom_fields);
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
								    	$html_treated = str_replace('<webversion', '<a href="'.$app_path.'/w/'.encrypt_val($subscriber_id).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a" ', $html_treated);
								    	$html_treated = str_replace('</webversion>', '</a>', $html_treated);
								    	$html_treated = str_replace('[webversion]', $app_path.'/w/'.encrypt_val($subscriber_id).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a', $html_treated);
								    	$plain_treated = str_replace('[webversion]', $app_path.'/w/'.encrypt_val($subscriber_id).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a', $plain_treated);
								    	
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
								    		$html_treated = str_replace('<unsubscribe', '<a href="'.$app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a" ', $html_treated);
								    		$html_treated = str_replace('</unsubscribe>', '</a>', $html_treated);
								    		$html_treated = str_replace('[unsubscribe]', $app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a', $html_treated);
								    		$plain_treated = str_replace('[unsubscribe]', $app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a', $plain_treated);
										}
								    	
								    	//Name tag
										$html_treated = str_replace('[Name]', $name, $html_treated);
										$plain_treated = str_replace('[Name]', $name, $plain_treated);
										$title_treated = str_replace('[Name]', $name, $title_treated);
										
								    	//Email tag
										$html_treated = str_replace('[Email]', $email, $html_treated);
										$plain_treated = str_replace('[Email]', $email, $plain_treated);
										$title_treated = str_replace('[Email]', $email, $title_treated);
								    	
								    	//If opens tracking is enabled, add 1 x 1 px tracking image
								    	if($opens_tracking)
								    	{
									    	//add tracking 1 by 1px image
											$html_treated .= '<img src="'.$app_path.'/t/'.encrypt_val($ares_id).'/'.encrypt_val($subscriber_id).'/a" alt="" style="width:1px;height:1px;"/>';
										}
										
										//send email
										$mail = new PHPMailer();
										if($smtp_host!='' && $smtp_port!='' && $smtp_ssl!='' && $smtp_username!='' && $smtp_password!='')
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
												$mail->AddCustomHeader('X-ElasticEmail-Postback: '.encrypt_val($list));
											//If sending via Sendgrid, send subscriber list ID to Sendgrid so that we can retrieve it via their webhook later
											else if($smtp_host == 'smtp.sendgrid.net' && $smtp_port!='' && $smtp_username!='' && $smtp_password!='')
											{
												$sgheaders = json_encode(array('category' => array(encrypt_val($list))));
												$mail->AddCustomHeader('X-SMTPAPI: '.$sgheaders);
											}
											else if($smtp_host == 'in-v3.mailjet.com' && $smtp_port!='' && $smtp_username!='' && $smtp_password!='')
												$mail->AddCustomHeader('X-MJ-CustomID: '.encrypt_val($list));
										}
										else if($s3_key!='' && $s3_secret!='')
										{
											$mail->IsAmazonSES(false, $ares_id, $subscriber_id, '', 0, true);
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
										$mail->AddCustomHeader('List-Unsubscribe: <'.$app_path.'/unsubscribe/'.encrypt_val($email).'/'.encrypt_val($list).'/'.encrypt_val($ares_id).'/a>');
										$server_path_array = explode('autoresponders.php', $_SERVER['SCRIPT_FILENAME']);
									    $server_path = $server_path_array[0];
										if(file_exists($server_path.'uploads/attachments/a'.$ares_id))
										{
											foreach(glob($server_path.'uploads/attachments/a'.$ares_id.'/*') as $attachment){
												if(file_exists($attachment))
												    $mail->AddAttachment($attachment);
											}
										}
										$mail->Send();
										
										//Format sent date and web version for Rules
										$sent_formatted = date("D, M d, Y, h:iA", time());
										$web_version = $app_path.'/w/'.encrypt_val($ares_id).'/a';
										
										//Run rules
										$rules_data = array(
										    'trigger' => 'ares_sent',
										    'subject' => $title_treated,
										    'from_name' => $from_name,
										    'from_email' => $from_email,
										    'reply_to' => $reply_to,
										    'to_name' => $name,
										    'to_email' => $email,
										    'sent' => $sent_formatted,
										    'webversion' => $web_version,
										    'list_name' => $list_name,
										    'ares_name' => $ares_name,
										    'list_id' => encrypt_val($list),
										    'ares_id' => $ares,
										    'ares_email_id' => $ares_id,
										    'report_url' => "$app_path/autoresponders-report?i=$app&a=$ares&ae=$ares_id"
									    );
									    
									    //Run rules
										run_rule($rules_data);
											
										$q3 = 'UPDATE ares_emails, subscribers SET ares_emails.recipients = ares_emails.recipients+1, subscribers.last_ares = '.$ares_id.' WHERE ares_emails.id = '.$ares_id.' AND subscribers.id = '.$subscriber_id;
										mysqli_query($mysqli, $q3);
										
										//Update quota if a monthly limit was set
										if($allocated_quota!=-1)
										{
											//if so, update quota
											$updated_quota = $current_quota + $to_send_num;
											$q4 = 'UPDATE apps SET current_quota = current_quota+1 WHERE id = '.$app;
											mysqli_query($mysqli, $q4);
										}
									}
								}								
							}
							$i++;
						}
					}
			    }  
			}
	    }  
	}
?>