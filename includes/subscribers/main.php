<?php 
	//------------------------------------------------------//
	//                      FUNCTIONS                       //
	//------------------------------------------------------//
	
	//------------------------------------------------------//
	function get_app_data($val)
	//------------------------------------------------------//
	{
		global $mysqli;
		$q = 'SELECT '.$val.' FROM apps WHERE id = "'.get_app_info('app').'" AND userID = '.get_app_info('main_userID');
		$r = mysqli_query($mysqli, $q);
		if ($r && mysqli_num_rows($r) > 0)
		{
		    while($row = mysqli_fetch_array($r))
		    {
				return $row[$val];
		    }  
		}
	}
	
	//------------------------------------------------------//
	function get_list_data($val)
	//------------------------------------------------------//
	{
		global $mysqli;
		$q = 'SELECT '.$val.' FROM lists WHERE id = '.mysqli_real_escape_string($mysqli, (int)$_GET['l']).' AND userID = '.get_app_info('main_userID');
		$r = mysqli_query($mysqli, $q);
		if ($r && mysqli_num_rows($r) > 0)
		{
		    while($row = mysqli_fetch_array($r))
		    {
				return $row[$val];
		    }  
		}
	}
	
	//------------------------------------------------------//
	function get_list_name($val)
	//------------------------------------------------------//
	{
		global $mysqli;
		$q = 'SELECT name FROM lists WHERE id = '.$val.' AND userID = '.get_app_info('main_userID');
		$r = mysqli_query($mysqli, $q);
		if ($r && mysqli_num_rows($r) > 0)
		{
		    while($row = mysqli_fetch_array($r))
		    {
				return $row['name'];
		    }  
		}
	}
	
	//------------------------------------------------------//
	function get_seg_data($val, $sid)
	//------------------------------------------------------//
	{
		global $mysqli;
		$q = 'SELECT '.$val.' FROM seg WHERE id = '.$sid;
		$r = mysqli_query($mysqli, $q);
		if ($r && mysqli_num_rows($r) > 0)
		{
		    while($row = mysqli_fetch_array($r))
		    {
				return $row[$val];
		    }  
		}
	}
	
	//------------------------------------------------------//
	function get_totals_in_seg($val)
	//------------------------------------------------------//
	{
		global $mysqli;
		$q2 = 'SELECT COUNT(*) AS subscriber_count FROM subscribers_seg WHERE seg_id = '.$val;
		$r2 = mysqli_query($mysqli, $q2);
		if ($r2 && mysqli_num_rows($r2) > 0)
		    while($row = mysqli_fetch_array($r2))
				return number_format($row['subscriber_count']);
	}
	
	//------------------------------------------------------//
	function totals($list)
	//------------------------------------------------------//
	{
		global $mysqli;
		global $s;
		global $c;
		global $p;
		global $a;
		global $u;
		global $b;
		global $cp;
	
		if($s!='')
			$s_more = 'AND (name LIKE "%'.$s.'%" OR email LIKE "%'.$s.'%")';
		else
			$s_more = '';
		
		$more = '';
		if($a!='')
			$more = 'AND confirmed = 1 AND unsubscribed = 0 AND bounced = 0 AND complaint = 0';
		else if($c!='')
			$more = 'AND confirmed = '.$c;
		else if($u!='')
			$more = 'AND unsubscribed = '.$u.' AND bounced = 0';
		if($b!='')
			$more = 'AND bounced = '.$b;
		if($cp!='')
			$more = 'AND complaint = '.$cp;
			
		$q = 'SELECT COUNT(*) FROM subscribers WHERE list = '.$list.' '.$s_more.' '.$more;
		$r = mysqli_query($mysqli, $q);
		if ($r) while($row = mysqli_fetch_array($r)) return $row['COUNT(*)'];
	}
	
	//------------------------------------------------------//
	function get_totals($val1, $val2, $val3='')
	//------------------------------------------------------//
	{
		global $mysqli;
		
		$lid = $val3=='' ? mysqli_real_escape_string($mysqli, (int)$_GET['l']) : $val3;
		
		if($val1=='' && $val2=='')
			$s_more = '';
		else if($val1=='a')
			$s_more = 'AND unsubscribed = 0 AND bounced = 0 AND complaint = 0 AND confirmed = 1';
		else if($val1=='unsubscribed')
			$s_more = 'AND '.$val1.' = '.$val2.' AND bounced = 0';
		else if($val1=='gdpr')
			$s_more = 'AND unsubscribed = 0 AND bounced = 0 AND complaint = 0 AND confirmed = 1 AND '.$val1.' = '.$val2;
		else if($val1=='confirmed')
			$s_more = 'AND bounced = 0 AND complaint = 0 AND '.$val1.' = '.$val2;
		else
			$s_more = 'AND '.$val1.' = '.$val2;
		
		$q = 'SELECT COUNT(*) FROM subscribers use index (s_list) WHERE list = '.$lid.' '.$s_more;
		$r = mysqli_query($mysqli, $q);
		if ($r) while($row = mysqli_fetch_array($r)) return number_format($row['COUNT(*)']);
	}
	
	//------------------------------------------------------//
	function get_lists_data($val, $lid)
	//------------------------------------------------------//
	{
		global $mysqli;
		$q = 'SELECT '.$val.' FROM lists WHERE app = "'.get_app_info('app').'" AND id = '.$lid;
		$r = mysqli_query($mysqli, $q);
		if ($r && mysqli_num_rows($r) > 0)
		{
		    while($row = mysqli_fetch_array($r))
		    {
				return $row[$val];
		    }  
		}
	}
	
	//------------------------------------------------------//
	function get_autoresponder_count()
	//------------------------------------------------------//
	{
		global $mysqli;
		$q = 'SELECT COUNT(*) FROM ares WHERE list = '.mysqli_real_escape_string($mysqli, (int)$_GET['l']);
		$r = mysqli_query($mysqli, $q);
		if ($r && mysqli_num_rows($r) > 0)
		{
		    while($row = mysqli_fetch_array($r))
		    {
				return $row['COUNT(*)'];
		    }  
		}
	}
	
	//------------------------------------------------------//
	function get_segments_count()
	//------------------------------------------------------//
	{
		global $mysqli;
		$q = 'SELECT COUNT(*) FROM seg WHERE list = '.mysqli_real_escape_string($mysqli, (int)$_GET['l']);
		$r = mysqli_query($mysqli, $q);
		if ($r && mysqli_num_rows($r) > 0)
		{
		    while($row = mysqli_fetch_array($r))
		    {
				return $row['COUNT(*)'];
		    }  
		}
	}
	
	//------------------------------------------------------//
	function get_custom_fields_count()
	//------------------------------------------------------//
	{
		global $mysqli;
		$q = 'SELECT custom_fields FROM lists WHERE app = "'.get_app_info('app').'" AND id = '.mysqli_real_escape_string($mysqli, (int)$_GET['l']);
		$r = mysqli_query($mysqli, $q);
		if ($r && mysqli_num_rows($r) > 0)
		{
		    while($row = mysqli_fetch_array($r))
		    {
				$custom_fields = $row['custom_fields'];
				$custom_fields_array = $custom_fields=='' ? '' : explode('%s%', $custom_fields);
				
				if($custom_fields == '')
					return 0;
				else
					return count($custom_fields_array);
		    }  
		}
	}
	
	//------------------------------------------------------//
	function has_gdpr_subscribers()
	//------------------------------------------------------//
	{
		global $mysqli;
		$q = 'SELECT COUNT(subscribers.gdpr) as gdpr_subs_no FROM subscribers, lists, apps WHERE subscribers.list = lists.id AND lists.app = apps.id AND subscribers.gdpr = 1 AND apps.id = '.get_app_info('app');
		$r = mysqli_query($mysqli, $q);
		if ($r) while($row = mysqli_fetch_array($r)) $gdpr_subs_no = $row['gdpr_subs_no'];
		if($gdpr_subs_no > 0) return true;
		else return false;
	}
	
	//------------------------------------------------------//
	function get_gdpr_count($lid)
	//------------------------------------------------------//
	{
		global $mysqli;
		$q = 'SELECT COUNT(id) FROM subscribers use index (s_list) WHERE unsubscribed = 0 AND bounced = 0 AND complaint = 0 AND confirmed = 1 AND gdpr = 1 AND list = '.$lid;
		$r = mysqli_query($mysqli, $q);
		if ($r) while($row = mysqli_fetch_array($r)) return number_format($row['COUNT(id)']);
	}
	
	//------------------------------------------------------//
	function get_gdpr_percentage($lid, $gdpr_subs)
	//------------------------------------------------------//
	{
		global $mysqli;
		
		//Get subscriber count
		$q = "SELECT COUNT(*) FROM subscribers WHERE list = '$lid' AND unsubscribed = 0 AND bounced = 0 AND complaint = 0 AND confirmed = 1";
		$r = mysqli_query($mysqli, $q);
		if ($r) while($row = mysqli_fetch_array($r)) $subscribers = $row['COUNT(*)'];
		
		$subscribers = str_replace(',', '', $subscribers);
		$gdpr_subs = str_replace(',', '', $gdpr_subs);
		$gdpr_percentage = $gdpr_subs==0 ? 0 : round($gdpr_subs / ($subscribers) * 100, 2);
		return $gdpr_percentage;
	}
	
	//------------------------------------------------------//
	function get_subscribers_count($lid)
	//------------------------------------------------------//
	{
		global $mysqli;
		
		//Check if the list has a pending CSV for importing via cron
		$server_path_array = explode('list.php', $_SERVER['SCRIPT_FILENAME']);
		$server_path = $server_path_array[0];
		
		if (file_exists($server_path.'uploads/csvs') && $handle = opendir($server_path.'uploads/csvs')) 
		{
			while (false !== ($file = readdir($handle))) 
			{
				if($file!='.' && $file!='..' && $file!='.DS_Store' && $file!='.svn')
				{
					$file_array = explode('-', $file);
					
					if(!empty($file_array))
					{
						if(str_replace('.csv', '', $file_array[1])==$lid)
							return _('Checking..').'
								<script type="text/javascript">
									$(document).ready(function() {
									
										list_interval = setInterval(function(){get_list_count('.$lid.')}, 2000);
										
										function get_list_count(lid)
										{
											clearInterval(list_interval);
											$.post("includes/list/progress.php", { list_id: lid, user_id: '.get_app_info('main_userID').' },
											  function(data) {
												  if(data)
												  {
													  if(data.indexOf("%)") != -1)
														  list_interval = setInterval(function(){get_list_count('.$lid.')}, 2000);
														  
													  $("#progress'.$lid.'").html(data);
												  }
												  else
												  {
													  $("#progress'.$lid.'").html("'._('Error retrieving count').'");
												  }
											  }
											);
										}
										
									});
								</script>';
					}
				}
			}
			closedir($handle);
		}
		
		//if not, just return the subscriber count
		$q = 'SELECT COUNT(list) FROM subscribers use index (s_list) WHERE list = '.$lid.' AND unsubscribed = 0 AND bounced = 0 AND complaint = 0 AND confirmed = 1';
		$r = mysqli_query($mysqli, $q);
		if ($r)
		{
			while($row = mysqli_fetch_array($r))
			{
				return number_format($row['COUNT(list)']);
			} 
		}
	}
	
	//------------------------------------------------------//
	function pagination($limit)
	//------------------------------------------------------//
	{
		global $s;
		global $c;
		global $p;
		global $a;
		global $u;
		global $b;
		global $cp;
		global $g;
		
		$curpage = $p;
		
		$next_page_num = 0;
		$prev_page_num = 0;
		
		$total_subs = totals($_GET['l']);
		$total_pages = @ceil($total_subs/$limit);
		
		if($s!='')
			$s_more = '&s='.$s;
		else
			$s_more = '';
		
		$more = '';
		if($a!='')
			$more = '&a='.$a;
		else if($c!='')
			$more = '&c='.$c;
		else if($u!='')
			$more = '&u='.$u;
		else if($b!='')
			$more = '&b='.$b;
		else if($cp!='')
			$more = '&cp='.$cp;
		else if($g!='')
			$more = '&g='.$g;
		
		if($total_subs > $limit)
		{
			if($curpage>=2)
			{
				$next_page_num = $curpage+1;
				$prev_page_num = $curpage-1;
			}
			else
			{
				$next_page_num = 2;
			}
		
			echo '<div class="btn-group" id="pagination">';
			
			//Prev btn
			if($curpage>=2)
				if($prev_page_num==1)
					echo '<button class="btn" onclick="window.location=\''.get_app_info('path').'/subscribers?i='.get_app_info('app').'&l='.$_GET['l'].$s_more.$more.'\'"><span class="icon icon icon-arrow-left"></span></button>';
				else
					echo '<button class="btn" onclick="window.location=\''.get_app_info('path').'/subscribers?i='.get_app_info('app').'&l='.$_GET['l'].$s_more.$more.'&p='.$prev_page_num.'\'"><span class="icon icon icon-arrow-left"></span></button>';
			else
				echo '<button class="btn disabled"><span class="icon icon icon-arrow-left"></span></button>';
			
			//Next btn
			if($curpage==$total_pages)
				echo '<button class="btn disabled"><span class="icon icon icon-arrow-right"></span></button>';
			else
				echo '<button class="btn" onclick="window.location=\''.get_app_info('path').'/subscribers?i='.get_app_info('app').'&l='.$_GET['l'].$s_more.$more.'&p='.$next_page_num.'\'"><span class="icon icon icon-arrow-right"></span></button>';
					
			echo '</div>';
		}
	}
?>