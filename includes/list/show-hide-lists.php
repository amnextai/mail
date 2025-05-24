<?php include('../functions.php');?>
<?php include('../login/auth.php');?>
<?php 
	$app = isset($_GET['app']) && is_numeric($_GET['app']) ? mysqli_real_escape_string($mysqli, (int)$_GET['app']) : exit;
	$hide = isset($_GET['hide']) && is_numeric($_GET['hide']) ? mysqli_real_escape_string($mysqli, (int)$_GET['hide']) : exit;
	
	$q = 'UPDATE apps SET hide_lists = '.$hide.' WHERE id = '.$app;
	$r = mysqli_query($mysqli, $q);
	if ($r)
	{
		header("Location: ".get_app_info('path')."/list?i=".$app);
	}
	else
	{
		error_log("[Unable to run query]".mysqli_error($mysqli).': in '.__FILE__.' on line '.__LINE__);
		exit;
	}
?>