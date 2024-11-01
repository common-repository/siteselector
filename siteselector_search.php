<?php
//!!!!!!!  siteselector  !!!!!!!!!!!!!!!!!
//
include_once("common.php");
global $wpdb, $siteselectorConfigTable;
$status = '';
//
$device = siteselector_getDevice();
$ua=siteselector_getBrowser();
$browserName = $ua['name'];
$browserVersion = $ua['version'];
$platform = $ua['platform'];
$dev = $platform.$device;
//
$reload = 'no';
if($device == "mobile" && $platform == "android" && !isset($_POST['reload'])){
	//need to reload to force correct device width
	$reload = 'yes';
}


$findCom = "findNames();";
$suffixCom = "openSuffix(true);";

$config = $wpdb->get_results("SELECT * from `$siteselectorConfigTable`");
if(count($config) > 0){
	$domain = $config[0]->domain;
	$key = $config[0]->api_key;
	$status = $config[0]->status;
	$goesLeft = $config[0]->goes_left;
	$simileLimit = $config[0]->simile_limit;
}

?>
<script>
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
});
function findNames(){
	//first delete any previous results
	jQuery('.available_item').remove();
	jQuery('.taken_item').remove();
	jQuery('.registered_item').remove();
	jQuery('#progress_bar').width('0%');
	jQuery('#progress_table').show();
	jQuery('#spinner_text').html('finding names');
	jQuery('#progress_bar').html('');
	jQuery('#find_status_box').html('');
	U = L = tally = 0;
	//
	var k1 = jQuery('#keyword_1').val();
	var k2 = jQuery('#keyword_2').val();
	if(k1 == '' && k2 == ''){
		dialog('','Please supply at least one keyword');
	}
	var user_id = jQuery('#user_id').val();
	
	jQuery.ajax({
		url:"<?php echo admin_url('admin-ajax.php'); ?>",
		type: "POST",
		dataType:"json",
		data:({
			action:"siteselector_find_names",
			k1: k1,
			k2: k2
		}),
		success: function(r){
			if(r['status'] == 'success'){
				jQuery('#progress_bar').width(perm_offset+'%');
				//call checker using returned permutations
				console.log('number in list: '+r['count']+' '+r['list']);
				list = r['list'];
				jQuery('#service_status').html(r['service_status']);
				jQuery('#goes_left').html(r['goes_left']);
				if(jQuery('#goes_left').html() > low_goes || jQuery('#goes_left').html() < 1){
					jQuery('#buy_reminder_window').hide();
				}else
				{
					jQuery('#buy_reminder_window').show();
				}
				if(r['service_status'] == 'free'){
					jQuery('#suffix_button').hide();
				}
				//list.unshift('');
				L = list.length;
				if(L < 1){
					jQuery('#progress_table').hide();
				}else
				{
					jQuery('#spinner_text').html('collecting data');
					//for(var h=0;h<5;h++){
						checkUrls();
					//}
				}
			}else
			{
				jQuery('#progress_table').hide();
				jQuery('#find_status_box').show();
				jQuery('#find_status_box').html('Sorry, something went wrong');
				console.log('status: '+r['status']+' ('+r['message']+')');
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
		timeout: 60000
	});
}
//
function checkUrls(){
	//
	if(U >= L){return false;}
	var user_id = jQuery('#user_id').val();
	var N = U + 1;
	console.log(N+' out of '+L+' checking '+list[U]);
	jQuery.ajax({
		url:"<?php echo admin_url('admin-ajax.php'); ?>",
		data: {
			action: "siteselector_check_url",
			url: list[U],
			suffix: suffix_list,
			item_num: N
		},
		dataType: "json",		
		method: "post",
		success: function(r){
			console.log("Ajax success for item "+r['item_num']);
			//next call
			if(U < L){
				setTimeout(function() {
					checkUrls();
				}, 10);
			}
			if(r['success'] == 'success'){
				tally++;
				var p = (100-perm_offset)*tally/L;
				jQuery('#progress_bar').width(perm_offset+p+'%');
				if(p>=100-perm_offset){
					jQuery('#progress_table').hide();
					if(jQuery('#status').val() == 'free'){
						var i = document.createElement('div');
						i.className = "available_item clickable";
						i.innerHTML = 'subscribe to see many more alternatives';
						i.setAttribute('onclick','openBox("subscribe",true)');
						document.getElementById('available').appendChild(i);
					}
				}
				var S = suffix_list.length;
				var T = L * S;
				var t = tally * S;
				jQuery('#progress_bar').css('background-color', 'hsla(234,93%,43%,1.00)');
				jQuery('#progress_bar').html(t+' of '+T);
				//jQuery('#progress_bar_div').html(tally+'/'+L);
				//process all items in r['list']
				var items = r['list'];
				var d, red;
				for(var c=0;c<items.length;c++){
					d = items[c];
					if(d['status'] == 'available'){
						var i = document.createElement('div');
						i.className = "available_item";
						i.innerHTML = d['url'] + '<a href="https://www.namecheap.com/domains/registration/results.aspx?domain='+d['url']+'&aff=87458" target="_blank">&shy;register</a>';
						document.getElementById('available').appendChild(i);
					}else
					if(d['status'] == 'registered'){
						//registered but not hosted
						var i = document.createElement('div');
						i.className = "registered_item";
						i.innerHTML = d['url']+' <br><date>'+d['exp_date']+'</date>';
						document.getElementById('registered').appendChild(i);
					}else
					{
						//taken
						var i = document.createElement('div');
						i.className = "taken_item";
						if(d['image'] == ''){
							i.innerHTML = 'Domain name probably for sale<br><a target="_blank" href="http://'+d['url']+'">'+d['url']+'</a>';
						}
						if(d['image'] == 'white'){
							i.innerHTML = 'No image available<br><a target="_blank" href="http://'+d['url']+'">'+d['url']+'</a>';
						}
						else
						{
							if(d['redirect']){
								red = ' <br><red>(redirects to '+d['redirect']+')</red>';
							}else
							{
								red = '';	
							}
							i.innerHTML = '<a target="_blank" href="http://'+d['url']+'"><img class="url_frame" src="'+d['image']+'"></img></a><br><a target="_blank" href="http://'+d['url']+'">'+d['url']+'</a>'+red;
					
						}
						document.getElementById('taken').appendChild(i);
						

					}

				}
			}		
			
		},
		error: function(jqXHR,error, errorThrown,data) {  
			//console.log(jqXHR.responseText + ' '+error+' '+errorThrown+' '+data);
			tally++;
			var p = (100-perm_offset)*tally/L;
			jQuery('#progress_bar').width(perm_offset+p+'%');
			if(p>=100-perm_offset){
				jQuery('#progress_table').hide();
			}
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
			console.log('ajax failed for checkUrls');
			console.log(this['data']+' '+text);
			//next call
			if(U < L){
				setTimeout(function() {
					checkUrls();
				}, 2000);
			}
		},
		timeout: 180000
	});
	U++;
}
</script>
	<div id="outer_container">
		<div id="container" class="centre">			
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
		
					<div id="title_text">Enter one or two keywords that describe the idea behind your new site</div>
					
					
					<!--<h1>Coming Soon</h1>-->
		
						<div id="keyword_form" class="form_table">
							<div class="row">
								<span class="keyword_cell bold">keywords</span>
								<span class="keyword_input_cell"><input class="keyword_input" type="text" id="keyword_1" placeholder="keyword 1"/></span>
								<span class="keyword_input_cell"><input class="keyword_input" type="text" id="keyword_2" placeholder="keyword 2"/></span>
								
								<?php 
								if($status != 'free'){
									echo '<span class="keyword_cell"><div id="suffix_button" class="large_button" onclick="'.$suffixCom.'">suffix</div></span>';
								}
								?>
								
								<span class="keyword_cell"><div class="large_button" onclick="findNames();">find</div></span>
								
							</div>
						</div>
		
					<div id="find_status_box"></div>
						
					<div id="progress_table">
						<!--<div class="row">-->
							<span id="spinner"></span>
							<span id="spinner_text">idle</span>
							<span id="progress_bar_span"><div id="progress_bar_div"><div id="progress_bar"></div></div></span>
							<span id="check_cancel_span"><div id="cancel_button" class="small_button" onclick="cancelCheck();">cancel</div></span>		
						<!--</div>-->
					</div>
					<div id="results_table" class="centre">
						<div class="row">
							<span id="available" class="available_cell">
							<h3>Available names</h3>
							</span>
							<span id="registered" class="registered_cell">
							<h3>Registered names</h3>
							</span>
							<span id="taken" class="taken_cell">
							<h3>Possible Competition</h3>
							</span>
						</div>
					</div>

				</div>

					
					
			
			<div id="footer">
				<div class="centre" id="footer_table">
					<div class="row">
						<span class="cell"><font class="italic">siteselector</font> by <a href="http://new.scideas.net" target="_blank">scideas software</a></span>
						<!--<span class="cell clickable" onclick="openBox('contact');">contact</span>-->
					</div>
				</div>
			</div>
			
			
			<div id="suffix_box" class="form_box">
		<div id="close_suffix" class="close_button" onclick="closeBox('suffix');"><img class="close_image" src="<?php echo siteselector_URL.'/media/close.png';?>" /></div>
		
		<div id="login_form_box" class="form_table">
			<div class="row">
				<span class="cell">Add or remove any suffix leaving a space between each one</span>
			</div>
			<div class="row">
				<span class="input_span"><input class="input_span_input"  type="text" id="suffix_list" name="suffix_list" /></span>
			</div>
		</div>
			<div class="row">
				<span class="input_label"></span><span class="input_span" style="text-align:right;"><span class="large_button" onclick="saveSuffix();">save</span></span>
			</div>	
		
	</div>
			
					
		</div><!--end of container-->
		
		<input type="hidden" name="spam_number" id="spam_number" value="<?php echo $spamNumber;?>"/>
	


	
	
	<div id="buy_reminder_window">
		<div id="buy_reminder_text">You only have a few credits left. When they are gone siteselector won't give so many variations, why not buy more now ?</div>
		<div id="buy_reminder_button_box"><a href="http://siteselector.scideas.net/buy_credits.php?host=<?php echo $domain;?>&key=<?php echo $key;?>" target="_blank"><div class="buy_button_large">Buy search credits</div></a></div>				
	</div>


	
		<div id="contact_box" class="form_box">
		<div class="close_button" onclick="closeBox('contact');"><img class="close_image" src="<?php echo siteselector_URL.'/media/close.png';?>" /></div>
		<form action="" method="post" name="contact_form" id="contact_form">
		<div class="form_table">
			<div class="row">
				<span class="input_label"></span><span id="contact_status_box"></span>
			</div>
			<div class="row">
				<span class="input_label">Name</span><span class="input_span"><input class="input_span_input" type="text" id="contact_name" name="contact_name"/></span>
			</div>
			<div class="row">
				<span class="input_label">Email</span><span class="input_span"><input class="input_span_input" onblur="checkEmail('contact');" type="text" id="contact_email" name="contact_email"/></span><span class="icon_span" id="contact_email_status_box"></span>
			</div>
			<div class="row">
				<span class="input_label">Message</span><span class="input_span_area"><textarea class="input_span_input" onchange="checkMessage('contact');" id="contact_message" name="contact_message" rows="6"></textarea></span><span class="icon_span" id="contact_message_status_box"></span>
			</div>
			<div class="row">
				<span class="input_label">Anti-spam</span><span class="input_span"><input class="input_span_input" style="font-size: 1.6vw;" onchange="checkSpam('contact');" type="text" id="contact_spam" name="contact_spam" placeholder="add together the second and 4th digits of <?php echo $spamNumber;?>"/></span><span class="icon_span" id="contact_spam_status_box"></span>
			</div>
			<div class="row">
				<span class="input_label"></span><span class="input_span"><span class="large_button" onclick="sendForm('contact');">Send</span></span>
			</div>
		</div>
		<input type="hidden" name="spam_number" id="spam_number" value="<?php echo $spamNumber;?>"/>
		</form>
	</div>
	<!--dialog window-->
	<div id="message_window" class="message_window">
		<div id="message_header" class="message_header"></div>
		<div id="message_body" class="alert_message"></div>
		<div class="message_toolbar">
			<div class="row">
				<span class="toolItem"><div id="dialog_submit_button" class="submitButton" >OK</div></span>
				<span class="toolItem"><div class="submitButton" onclick=cancelDialog();>Cancel</div></span>
			</div>
		</div>	
	</div>
	<!--confirm window-->
	<div id="confirm_window" class="message_window">
		<div id="confirm_header" class="message_header"></div>
		<div id="confirm_body" class="alert_message"></div>
		<div class="message_toolbar">
			<div class="row">
				<span class="toolItem"><div class="submitButton" onclick=cancelConfirm();>OK</div></span>
			</div>
		</div>	
	</div>
		
	</div><!--end of outer container-->
	
	
	

	<form id="logoutForm" method="post"><input type="hidden" id="logout" name="logout" value="yes"/></form>
	<form id="reloadForm" method="post"><input type="hidden" id="reload" name="reload" value="<?php echo $reload;?>"/></form>
</body>

</html>