<?php include('includes/header.php');?>
<?php include('includes/login/auth.php');?>
<?php include('includes/subscribers/main.php');?>
<?php include('includes/helpers/short.php');?>
<?php			
	if(get_app_info('is_sub_user')) 
	{
		if(get_app_info('app')!=get_app_info('restricted_to_app'))
		{
			echo '<script type="text/javascript">window.location="'.addslashes(get_app_info('path')).'/list?i='.get_app_info('restricted_to_app').'"</script>';
			exit;
		}
	}
	
	//vars
	if(isset($_GET['s'])) $s = trim(mysqli_real_escape_string($mysqli, $_GET['s']));
?>
<div class="row-fluid">
    <div class="span2">
        <?php include('includes/sidebar.php');?>
    </div> 
    <div class="span10">
    	<div>
	    	<p class="lead">
		    	<?php if(get_app_info('is_sub_user')):?>
			    	<?php echo get_app_data('app_name');?>
		    	<?php else:?>
			    	<a href="<?php echo get_app_info('path'); ?>/edit-brand?i=<?php echo get_app_info('app');?>" data-placement="right" title="<?php echo _('Edit brand settings');?>"><?php echo get_app_data('app_name');?> <span class="icon icon-pencil top-brand-pencil"></span></a>
		    	<?php endif;?>
		    </p>
    	</div>
    	<h2><?php echo _('Search all lists');?></h2> <br/>
		
		<div class="well">			
			<p><?php echo _('Keyword');?>: <span class="label label-info"><?php echo htmlentities($s);?></span> | Results <span class="label label-info" id="results-count-lists"></span> <span class="label label-info" id="results-count"></span></p>
			
			<div style="float: right; margin-top: -34px">
				<form class="form-search" action="<?php echo get_app_info('path');?>/search-all-lists" method="GET" style="float:right;">
					<input type="hidden" name="i" value="<?php echo get_app_info('app');?>">
					<input type="text" class="input-medium search-query" id="search-field" name="s" style="width: 200px;">
					<button type="submit" class="btn"><i class="icon-search"></i> <?php echo _('Search all lists');?></button>
				</form>
				
				<a href="<?php echo get_app_info('path')?>/list?i=<?php echo get_app_info('app');?>" title="" style="float:right; margin: 5px 15px 0 0"><i class="icon icon-double-angle-left"></i> <?php echo _('Back to lists');?></a>
			</div>
		</div>
		
		<br/>
		
		<div>
			<h3 style="margin: 0 0 10px 5px;">Lists</h3>
		</div>
		
		<?php $has_gdpr_subscribers = has_gdpr_subscribers(); ?>
		
		<table class="table table-striped responsive" style="margin-bottom: 30px;">
		  <thead>
			<tr>
			  <th><?php echo _('ID');?></th>
			  <th><?php echo _('List');?></th>
			  <th><?php echo _('Active');?></th>
			  <?php if($has_gdpr_subscribers):?>
			  <th><?php echo _('GDPR');?></th>
			  <?php endif;?>
			  <th><?php echo _('Hide');?></th>
			  <th><?php echo _('Edit');?></th>
			  <th><?php echo _('Delete');?></th>
			</tr>
		  </thead>
		  <tbody>
			  
			  <!-- Auto select encrypted listID -->
			  <script type="text/javascript">
				  $(document).ready(function() {
					$(".encrypted-list-id").mouseover(function(){
						$(this).selectText();
					});
					
					$("#search-field").focus();
					$('#search-field').val('').val("<?php echo $s;?>");
				});
			</script>
			
			<?php 
				//Get sorting preference and whether to show hidden lists
				$q = 'SELECT templates_lists_sorting, hide_lists, opt_in FROM apps WHERE id = '.get_app_info('app');
				$r = mysqli_query($mysqli, $q);
				if ($r && mysqli_num_rows($r) > 0) 
				{
					while($row = mysqli_fetch_array($r)) 
					{
						$templates_lists_sorting = $row['templates_lists_sorting'];
						$hide_lists = $row['hide_lists'];
						$opt_in = $row['opt_in'];
					}
				}
				$sortby = $templates_lists_sorting=='date' ? 'id DESC' : 'name ASC';
				$show_hidden_lists = $hide_lists == 0 ? '' : 'AND hide = 0';
			?>
			  
			  <?php 
				  
				  $q = 'SELECT id, name, hide FROM lists WHERE app = '.get_app_info('app').' AND userID = '.get_app_info('main_userID').' AND name LIKE "%'.$s.'%" '.$show_hidden_lists.' ORDER BY '.$sortby;
				  $r = mysqli_query($mysqli, $q);
				  $number_of_lists = mysqli_num_rows($r) > 1 ? mysqli_num_rows($r).' '._('lists') : mysqli_num_rows($r).' '._('list');
				  echo '
				  	<script type="text/javascript">
				  	$(document).ready(function() {
					  	$("#results-count-lists").text("'.$number_of_lists.'");
				  	});
					</script>
				  ';
				  if ($r && mysqli_num_rows($r) > 0)
				  {
					  while($row = mysqli_fetch_array($r))
					  {
						  $id = $row['id'];
						  $name = stripslashes($row['name']);
						  $hidden = stripslashes($row['hide']);
						  $subscribers_count = get_subscribers_count($id);
						  if(strlen(encrypt_val($id))>5) $listid = substr(encrypt_val($id), 0, 5).'..';
						  else $listid = encrypt_val($id);
						  
						$is_hidden = $hidden ? '.5' : '1';
						$icon_eye = $hidden ? 'icon-eye-open' : 'icon-eye-close';
						$hide_unhide_title = $hidden ? _('Unhide this list') : _('Hide this list');
						$to_hide = $hidden ? 0 : 1;
						  
						  echo '
						  
						  <tr id="'.$id.'" style="opacity: '.$is_hidden.'">
							<td><span class="label" id="list'.$id.'">'.$listid.'</span><span class="label encrypted-list-id" id="list'.$id.'-encrypted" style="display:none;">'.encrypt_val($id).'</span></td>
						  <td><a href="'.get_app_info('path').'/subscribers?i='.get_app_info('app').'&l='.$id.'" title="">'.$name.'</a></td>
						  <td id="progress'.$id.'"><span class="badge badge-success">'.$subscribers_count.'</span></td>';
						  
						if($has_gdpr_subscribers)
						{
							$gdpr_count = get_gdpr_count($id);
							$gdpr_percentage = get_gdpr_percentage($id, $gdpr_count);
							echo '<td><span class="label label-warning">'.$gdpr_percentage.'%</span> '.$gdpr_count.'</td>';
						}
						  
						echo '
						  <td><a href="includes/list/hide-list.php" id="hide-btn-'.$id.'" title="'.$hide_unhide_title.'" data-l="'.$id.'" data-hide="'.$to_hide.'"><i class="icon '.$icon_eye.'"></i></a></td>
						  <td><a href="edit-list?i='.get_app_info('app').'&l='.$id.'" title="'._('List settings').'"><i class="icon icon-pencil"></i></a></td>
						  <td><a href="#delete-list" title="'._('Delete').' '.$name.'" id="delete-btn-'.$id.'" data-toggle="modal"><span class="icon icon-trash"></span></a></td>
						  <script type="text/javascript">
							  $("#hide-btn-'.$id.'").click(function(e){
								e.preventDefault(); 
								$.post($(this).attr("href"), {l:$(this).data("l"), hide:$(this).attr("data-hide")},
									function(data) 
									{
										if(data==1) 
										{
											$("#'.$id.'").css("opacity", "0.5");
											$("#hide-btn-'.$id.'").html("<i class=\"icon icon-eye-open\"></i>");
											$("#hide-btn-'.$id.'").attr("title", "'._('Unhide this list').'");
											$("#hide-btn-'.$id.'").attr("data-hide", 0);
											to_hide = 0;
										}
										else 
										{
											$("#'.$id.'").css("opacity", "1");
											$("#hide-btn-'.$id.'").html("<i class=\"icon icon-eye-close\"></i>");
											$("#hide-btn-'.$id.'").attr("title", "'._('Hide this list').'");
											$("#hide-btn-'.$id.'").attr("data-hide", 1);
											to_hide = 1;
										}
									}
								);
							});
							$("#delete-btn-'.$id.'").click(function(e){
								e.preventDefault(); 
								$("#delete-list-btn").attr("data-id", '.$id.');
								$("#list-to-delete").text("'.$name.'");
								$("#delete-text").val("");
							});
							$("#list'.$id.'").mouseover(function(){
								$("#list'.$id.'-encrypted").show();
								$(this).hide();
							});
							$("#list'.$id.'-encrypted").mouseout(function(){
								$(this).hide();
								$("#list'.$id.'").show();
							});
							</script>
						</tr>
						  ';
					  }  
				  }
				  else
				  {
					  echo '
						  <tr>
							  <td>'._('No lists found.').'</td>
							  <td></td>
							  <td></td>
							  <td></td>
							  <td></td>
							  <td></td>
						  </tr>
					  ';
				  }
			  ?>
			
		  </tbody>
		</table>	
		
		<!-- Delete -->
		<div id="delete-list" class="modal hide fade">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
			<h3><?php echo _('Delete list');?></h3>
		  </div>
		  <div class="modal-body">
			<p><?php echo _('All subscribers, custom fields, autoresponders and segments in this list will be permanently deleted. Confirm delete <span id="list-to-delete" style="font-weight:bold;"></span>?');?></p>
		  </div>
		  <div class="modal-footer">
			<?php if(get_app_info('strict_delete')):?>
			<input autocomplete="off" type="text" class="input-large" id="delete-text" name="delete-text" placeholder="<?php echo _('Type the word');?> DELETE" style="margin: -2px 7px 0 0;"/>
			<?php endif;?>
			
			<a href="javascript:void(0)" id="delete-list-btn" data-id="" class="btn btn-primary"><?php echo _('Delete');?></a>
		  </div>
		</div>
		
		<script type="text/javascript">
			$("#delete-list-btn").click(function(e){
				e.preventDefault(); 
				
				<?php if(get_app_info('strict_delete')):?>
				if($("#delete-text").val()=='DELETE'){
				<?php endif;?>
				
					$.post("includes/list/delete.php", { list_id: $(this).attr("data-id") },
					  function(data) {
						  if(data)
						  {
							$("#delete-list").modal('hide');
							$("#"+$("#delete-list-btn").attr("data-id")).fadeOut(); 
						  }
						  else alert("<?php echo _('Sorry, unable to delete. Please try again later!')?>");
					  }
					);
				
				<?php if(get_app_info('strict_delete')):?>
				}
				else alert("<?php echo _('Type the word');?> DELETE");
				<?php endif;?>
			});
		</script>
		
		
		<div>
			<h3 style="margin: 0 0 10px 5px;">Subscribers</h3>
		</div>
		
	    <table class="table table-striped table-condensed responsive">
		  <thead>
		    <tr>
		      <th><?php echo _('Name');?></th>
		      <th><?php echo _('Email');?></th>
		      <th><?php echo _('List');?></th>
		      <th><?php echo _('Last activity');?></th>
		      <th><?php echo _('Status');?></th>
		      <th><?php echo _('Unsubscribe');?></th>
		      <th><?php echo _('Delete');?></th>
		    </tr>
		  </thead>
		  <tbody>		  	
		  	<?php
		  		$q = 'SELECT subscribers.id, subscribers.name, subscribers.email, subscribers.unsubscribed, subscribers.bounced, subscribers.complaint, subscribers.confirmed, subscribers.list, subscribers.timestamp FROM subscribers, lists WHERE (subscribers.name LIKE "%'.$s.'%" OR subscribers.email LIKE "%'.$s.'%" OR subscribers.custom_fields LIKE "%'.$s.'%" OR subscribers.notes LIKE "%'.$s.'%") AND lists.app = '.get_app_info('app').' AND lists.id = subscribers.list ORDER BY subscribers.timestamp DESC';
			  	$r = mysqli_query($mysqli, $q);
			  	$number_of_results = mysqli_num_rows($r);
				$number_of_results = mysqli_num_rows($r) > 1 ? mysqli_num_rows($r).' '._('subscribers') : mysqli_num_rows($r).' '._('subscriber');
			  	echo '
			  	<script type="text/javascript">
			  	$(document).ready(function() {
			  		$("#results-count").text("'.$number_of_results.'");
			  	});
			    </script>
			  	';
			  	if ($r && mysqli_num_rows($r) > 0)
			  	{
			  	    while($row = mysqli_fetch_array($r))
			  	    {
			  			$id = $row['id'];
			  			$name = stripslashes($row['name']);
			  			$email = stripslashes($row['email']);
			  			$unsubscribed = $row['unsubscribed'];
			  			$bounced = $row['bounced'];
			  			$complaint = $row['complaint'];
			  			$confirmed = $row['confirmed'];
			  			$list = $row['list'];
			  			$timestamp = parse_date($row['timestamp'], 'short', true);
			  			
			  			if($unsubscribed==0)
			  				$unsubscribed = '<span class="label label-success">'._('Subscribed').'</span>';
			  			else if($unsubscribed==1)
			  				$unsubscribed = '<span class="label label-important">'._('Unsubscribed').'</span>';
			  			if($bounced==1)
				  			$unsubscribed = '<span class="label label-inverse">'._('Bounced').'</span>';
				  		if($complaint==1)
				  			$unsubscribed = '<span class="label label-inverse">'._('Marked as spam').'</span>';
				  		if($confirmed==0)
			  				$unsubscribed = '<span class="label">'._('Unconfirmed').'</span>';
						if($confirmed==0 && $bounced==1)
							$unsubscribed = '<span class="label label-inverse">'._('Bounced').'</span>';
						if($confirmed==0 && $complaint==1)
							$unsubscribed = '<span class="label label-inverse">'._('Marked as spam').'</span>';
				  		
				  		if($name=='')
				  			$name = '['._('No name').']';
			  			
			  			echo '
			  			
			  			<tr id="'.$id.'">
			  			  <td><a href="#subscriber-info" data-id="'.$id.'" data-toggle="modal" class="subscriber-info">'.$name.'</a></td>
					      <td><a href="#subscriber-info" data-id="'.$id.'" data-toggle="modal" class="subscriber-info">'.$email.'</a></td>
					      <td><a href="'.get_app_info('path').'/subscribers?i='.get_app_info('app').'&l='.$list.'">'.get_list_name($list).'</a></td>
					      <td>'.$timestamp.'</td>
					      <td id="unsubscribe-label-'.$id.'">'.$unsubscribed.'</td>
					      <td>
					    ';
					    
					    if($row['unsubscribed']==0)
							$action_icon = '
								<a href="javascript:void(0)" title="'._('Unsubscribe').' '.$email.'" data-action'.$id.'="unsubscribe" id="unsubscribe-btn-'.$id.'">
									<i class="icon icon-ban-circle"></i>
								</a>
								';
						else if($row['unsubscribed']==1)
							$action_icon = '
								<a href="javascript:void(0)" title="'._('Resubscribe').' '.$email.'" data-action'.$id.'="resubscribe" id="unsubscribe-btn-'.$id.'">
									<i class="icon icon-ok"></i>
								</a>
							';
						if($row['bounced']==1 || $row['complaint']==1)
							$action_icon = '
								-
							';
						if($row['confirmed']==0)
							$action_icon = '
								<a href="javascript:void(0)" title="'._('Confirm').' '.$email.'" data-action'.$id.'="confirm" id="unsubscribe-btn-'.$id.'">
									<i class="icon icon-ok"></i>
								</a>
							';
						
						echo $action_icon;
					    
					    echo'
					      </td>
					      <td><a href="#delete-subscriber" title="'._('Delete').' '.$email.'?" data-toggle="modal" id="delete-btn-'.$id.'" class="delete-subscriber"><i class="icon icon-trash"></i></a></td>
					      <script type="text/javascript">
					    	$("#delete-btn-'.$id.'").click(function(e){
								e.preventDefault(); 
								$("#delete-subscriber-1, #delete-subscriber-2").attr("data-id", '.$id.');
								$("#email-to-delete").text("'.$email.'");
							});
							$("#unsubscribe-btn-'.$id.'").click(function(e){
								e.preventDefault(); 
								action = $("#unsubscribe-btn-'.$id.'").data("action'.$id.'");
								$.post("includes/subscribers/unsubscribe.php", { subscriber_id: '.$id.', action: action},
								  function(data) {
								      if(data)
								      {
								      	if($("#unsubscribe-label-'.$id.'").text()=="'._('Subscribed').'")
								      	{
								      		$("#unsubscribe-btn-'.$id.'").html("<li class=\'icon icon-ok\'></li>");
								      		$("#unsubscribe-btn-'.$id.'").data("action'.$id.'", "resubscribe");
									      	$("#unsubscribe-label-'.$id.'").html("<span class=\'label label-important\'>'._('Unsubscribed').'</span>");
									    }
									    else
									    {
									    	$("#unsubscribe-btn-'.$id.'").html("<li class=\'icon icon-ban-circle\'></li>");
								      		$("#unsubscribe-btn-'.$id.'").data("action'.$id.'", "unsubscribe");
									      	$("#unsubscribe-label-'.$id.'").html("<span class=\'label label-success\'>'._('Subscribed').'</span>");
									    }
									    if($("#unsubscribe-label-'.$id.'").text()=="'._('Unconfirmed').'")
									    {
									    	$("#unsubscribe-btn-'.$id.'").html("<li class=\'icon icon-ban-circle\'></li>");
								      		$("#unsubscribe-btn-'.$id.'").data("action'.$id.'", "confirm");
									      	$("#unsubscribe-label-'.$id.'").html("<span class=\'label label-success\'>'._('Subscribed').'</span>");
									    }
								      }
								      else
								      {
								      	alert("'._('Sorry, unable to unsubscribe. Please try again later!').'");
								      }
								  }
								);
							});
							</script>
					    </tr>
						
			  			';
			  	    }  
			  	}
			  	else
			  	{
			  		echo '
			  			<tr>
			  				<td>'._('No subscribers found.').'</td>
			  				<td></td>
			  				<td></td>
			  				<td></td>
			  				<td></td>
			  				<td></td>
			  				<td></td>
			  			</tr>
			  		';
			  	}
		  	?>
		    
		  </tbody>
		</table>
    </div>   
</div>

<!-- Delete -->
<div id="delete-subscriber" class="modal hide fade">
  <div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
    <h3><?php echo _('Delete subscriber');?></h3>
  </div>
  <div class="modal-body">
    <p><?php echo _('Delete <span id="email-to-delete" style="font-weight:bold;"></span> from \'this list only\' or \'ALL lists\' in this brand?');?></p>
    <p></p>
  </div>
  <div class="modal-footer">
    <a href="javascript:void(0)" id="delete-subscriber-1" data-id="" class="btn"><?php echo _('This list only');?></a>
    <a href="javascript:void(0)" id="delete-subscriber-2" data-id="" class="btn btn-primary"><?php echo _('ALL lists in this brand');?></a>
  </div>
</div>

<!-- Subscriber info card -->
<div id="subscriber-info" class="modal hide fade">
    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal">&times;</button>
      <h3><?php echo _('Subscriber info');?></h3>
    </div>
    <div class="modal-body">
	    <p id="subscriber-text"></p>
    </div>
    <div class="modal-footer">
      <a href="#" class="btn btn-inverse" data-dismiss="modal"><i class="icon icon-ok-sign" style="margin-top: 5px;"></i> <?php echo _('Close');?></a>
    </div>
  </div>
<script type="text/javascript">
	$("#delete-subscriber-1").click(function(e){
		e.preventDefault(); 
		$.post("includes/subscribers/delete.php", { subscriber_id: $(this).attr("data-id"), option: 1, app: <?php echo get_app_info('app')?> },
		  function(data) {
		      if(data) 
		      {
			      $("#delete-subscriber").modal('hide');
			      $("#"+$("#delete-subscriber-1").attr("data-id")).fadeOut(); 
			  }
		      else alert("<?php echo _('Sorry, unable to delete. Please try again later!')?>");
		  }
		);
	});
	$("#delete-subscriber-2").click(function(e){
		e.preventDefault(); 
		$.post("includes/subscribers/delete.php", { subscriber_id: $(this).attr("data-id"), option: 2, app: <?php echo get_app_info('app')?> },
		  function(data) {
		      if(data) 
		      {
			      $("#delete-subscriber").modal('hide');
			      $("#"+$("#delete-subscriber-2").attr("data-id")).fadeOut(); 
			  }
		      else alert("<?php echo _('Sorry, unable to delete. Please try again later!')?>");
		  }
		);
	});
	$(".subscriber-info").click(function(){
		s_id = $(this).data("id");
		$("#subscriber-text").html("<?php echo _('Fetching');?>..");
		
		$.post("<?php echo get_app_info('path');?>/includes/subscribers/subscriber-info.php", { id: s_id, app:<?php echo get_app_info('app');?> },
		  function(data) {
		      if(data)
		      {
		      	$("#subscriber-text").html(data);
		      }
		      else
		      {
		      	$("#subscriber-text").html("<?php echo _('Oops, there was an error getting the subscriber\'s info. Please try again later.');?>");
		      }
		  }
		);
	});
</script>

<?php include('includes/footer.php');?>
