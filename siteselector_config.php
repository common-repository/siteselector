<?php

//read config table
global $wpdb,$siteselectorConfigTable;
$goesLeft = $key = $domain = $status = $simileLimit = '';
$simileLimitMax = 8;
if(isset($_POST['simile_limit'])){
	//update config table
	//global $wpdb,$siteselectorConfigTable;
	$newSimileLimit = $_POST['simile_limit'];
	if(intval($newSimileLimit)){	
		$query = $wpdb->prepare("UPDATE `$siteselectorConfigTable` set `simile_limit`=%d",$newSimileLimit);
		$res = $wpdb->query($query);
		if(false === $res){
			siteselector_log("update of simile_limit failed: ".$wpdb->last_error);
		}
	}
	
}
//
$config = $wpdb->get_results("SELECT * from `$siteselectorConfigTable`");
if(count($config) > 0){
	$domain = $config[0]->domain;
	$key = $config[0]->api_key;
	$status = $config[0]->status;
	$goesLeft = $config[0]->goes_left;
	$simileLimit = $config[0]->simile_limit;
	if($status == 'free'){
		$simileLimitMax = 1;
	}else
	{
		$simileLimitMax = 8;//will be set at remote server regardless
	}
}

?>
<script type="text/javascript">
var limit_max = <?php echo $simileLimitMax;?>;
jQuery(document).ready(function($){
		jQuery.ajax({
		url:"<?php echo admin_url('admin-ajax.php'); ?>",
		type: "POST",
		dataType:"json",
		data:({
			action:"siteselector_ajax_initialize"
		}),
		success: function(r){
			if(r['status'] == 'success'){

				console.log('goes left updated');
				jQuery('#goes_left').html(r['goes_left']);
				jQuery('#service_status').html(r['service_status']);
				if(jQuery('#goes_left').html() > low_goes || jQuery('#goes_left').html() < 1){
					jQuery('#buy_reminder_window').hide();
				}else
				{
					jQuery('#buy_reminder_window').show();
				}
			}else
			{
				console.log('goes update failed, status: '+r['status']+' ('+r['message']+')');
			}
			
		},
		error: function(jqXHR,error, errorThrown,data) {  
			//console.log(jqXHR.responseText + ' '+error+' '+errorThrown+' '+data);
			jQuery('#progress_table').hide();
			console.log(this['url']);
			var text;
			if(jqXHR.responseText){
				text = jqXHR.responseText;
			}else
			{
				if(error){
					text = error;
				}else
				if(errorThrown){
					text = errorThrown;
				}
			}
			console.log(text);
		},
		timeout: 6000
	});
	
	$('#save_button').hide();
	$('#save_cancel').hide();
	
	$('.text_input').each(function() {
		var elem = $(this);
		
		// Save current value of element
		elem.data('oldVal', elem.val());
		
		// Look for changes in the value
		elem.bind("propertychange change click keyup input paste", function(event){
			// If value has changed...
			if (elem.data('oldVal') != elem.val()) {
				// Updated stored value
				elem.data('oldVal', elem.val());
				
				// Do action
				if($('#simile_limit').val().length > 0){
					if($('#simile_limit').val() > limit_max){$('#simile_limit').val(limit_max);}
					$('#save_button').show();
					$('#save_cancel').show();
					//$('#credentials_verified_button').hide();
				}
			}
		});
	});
});


</script>
<div id="outer_container">
		<div id="container" style="text-align:left;">			
			<div id="header">				
				<div id="logo">
					<div id="logo_title">siteselector</div>
					<div id="logo_sub_title">a domain choosing plugin by scideas software</div>
				</div>
				<div id="status_bar">
					<div class="row">
						<span class="cell align_base">Status: </span><span class="cell black align_base" id="service_status"><?php echo $status;?></span>
						<span class="cell align_base">Full searches left: </span><span class="cell black align_base" id="goes_left"><?php echo $goesLeft;?></span>
						<a href="http://siteselector.scideas.net/buy_credits.php?host=<?php echo $domain;?>&key=<?php echo $key;?>" target="_blank"><span class="cell align_base"><div class="buy_button">Buy search credits</div></span></a>
					</div>
				</div>
		
			</div>
		<form method="post" action="" id="config_form" onsubmit="">
		
			<h2>siteselector configuration</h2>
			
			<div id="config_form">
				<div class="row">
					<span class="field_label">shortcode: </span>
					<span class="field_input"><input class="" type="text" value="[siteselector name=chooser]" readonly/></span>
					<span class="field_help">Use this shortcode to make searches available on pages.</span>
				</div>
				<div class="row">
					<span class="field_label">api key: </span>
					<span class="field_input"><input class="" type="text" value="<?php echo $key; ?>" readonly/></span>
					<span class="field_help">This is used automatically by the system.</span>
				</div>
					<div class="row">
					<span class="field_label">registered domain: </span>
					<span class="field_input"><input class="" type="text" value="<?php echo $domain; ?>" readonly/></span>
					<span class="field_help">Searches will only work for this WP installation</span>
				</div>
				<div class="row">
					<span class="field_label">simile limit : </span>
					<span class="field_input"><input class="text_input" type="text" id="simile_limit" name="simile_limit" value="<?php echo $simileLimit; ?>" /></span>
					<span class="field_help">Number of alternatives found per keyword. Maximum allowed is 8.</span>
				</div>
				
			</div>
			
			<div class="large_button" id="save_button" onclick="jQuery('#config_form').submit();">Save</div>
			<div class="large_button" id="save_cancel" onclick="jQuery('#restore_form').submit();">Cancel changes</div>
		
		</form>	
		
				<div id="footer">
				<div class="centre" id="footer_table">
					<div class="row">
						<span class="cell"><font class="italic">siteselector</font> by <a href="http://new.scideas.net" target="_blank">scideas software</a></span>
						<!--<span class="cell clickable" onclick="openBox('contact');">contact</span>-->
					</div>
				</div>
			</div>
		
		
	</div>
	
	<div id="buy_reminder_window">
		<div id="buy_reminder_text">You only have a few credits left. When they are gone siteselector won't give so many variations, why not buy more now ?</div>
		<div id="buy_reminder_button_box"><a href="http://siteselector.scideas.net/buy_credits.php?host=<?php echo $domain;?>&key=<?php echo $key;?>" target="_blank"><div class="buy_button_large">Buy search credits</div></a></div>				
	</div>
	
	
</div>
<form method="post" action="" id="restore_form">
	<input type="hidden" id="restore" name="restore"/>
</form