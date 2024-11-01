<?php
/*
Plugin Name: Siteselector
Plugin URI: 
Description: choose and check domain names.
Author: scideas software
Author URI: http://new.scideas.net
Version: 1.01
Text Domain: 
License: GPL version 2 or later - 
*/
/*error_reporting(E_ALL);
ini_set('display_errors', '1');*/
include_once('common.php');

$siteurl = get_option('siteurl');
$pluginsUrl = plugins_url();
define('siteselector_URL', $pluginsUrl.'/'. basename(dirname(__FILE__)));

global $wpdb, $siteselectorConfigTable,$siteselector_shortcode_loaded;
$comp_table_prefix=$wpdb->prefix;
define('siteselector_TABLE_PREFIX', $comp_table_prefix);
$siteselectorConfigTable = siteselector_TABLE_PREFIX."siteselector_config";
$siteselector_shortcode_loaded = false;

function siteselector_plugin_deactivate()
{
	//clear results updater
	//wp_clear_scheduled_hook('siteselector_update_searches_action');
/*    global $wpdb;
    $table = siteselector_TABLE_PREFIX."siteselector_custom_buttons";
    $structure = "drop table if exists $table";
    $wpdb->query($structure);*/
}

function siteselector_plugin_activate() {
  //add_option( 'Activated_Plugin', 'Plugin-Slug' );	
  	
	siteselector_log("siteselector plugin loaded at ".siteselector_timestampToDate(time()));
	siteselector_install();
	siteselector_log("siteselector installed");
	siteselector_initialize();	
	add_option( 'Activated_Plugin', 'siteselector' );

  /* activation code here */
}
register_activation_hook( __FILE__, 'siteselector_plugin_activate' );
register_deactivation_hook(__FILE__ , 'siteselector_plugin_deactivate' );

function siteselector_load_plugin() {
	//wp_register_style('siteselector-googleFonts', '//fonts.googleapis.com/css?family=PT+Serif:400,400italic|Crete+Round:400,400italic|Vidaloka:400,400italic');
    //wp_enqueue_style( 'siteselector-googleFonts');
    if ( is_admin() && get_option( 'Activated_Plugin' ) == 'siteselector' ) {
		delete_option( 'Activated_Plugin' );
    }
	wp_enqueue_script("json2");
	wp_enqueue_script("jquery");
	wp_enqueue_script("siteselector_javascript_main",siteselector_URL."/siteselector_plugin.js");
}
add_action( 'admin_init', 'siteselector_load_plugin' );
add_action('init', 'siteselector_load_plugin');
//register_activation_hook(__FILE__,'siteselector_install');

function siteselector_search_menu() {
    include('siteselector_search.php');
}
function siteselector_credentials_menu() {
    include('siteselector_config.php');
}
function siteselector_admin_actions() {
	//add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
    add_menu_page("Siteselector", "Siteselector", 'manage_options','siteselector_config' , "siteselector_credentials_menu");
	//add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
    $configPage = add_submenu_page('siteselector_config','Siteselector Config','Siteselector Config','manage_options','siteselector_config','siteselector_credentials_menu');
    $searchPage = add_submenu_page('siteselector_config','Siteselector Search','Siteselector Search','manage_options','siteselector_search','siteselector_search_menu');
	add_action( 'load-' . $configPage, 'load_admin_css' );
	add_action( 'load-' . $searchPage, 'load_admin_css' );
}
add_action('admin_menu', 'siteselector_admin_actions');

function siteselector_install()
{
    global $wpdb, $siteselectorConfigTable;
	if($result = $wpdb->query("SHOW TABLES LIKE '$siteselectorConfigTable'") < 1){
		//if siteselector_config doesn't exist, create it and copy any data from bm basic
		$structure = "CREATE TABLE IF NOT EXISTS $siteselectorConfigTable (
			 `id` int(9) NOT NULL AUTO_INCREMENT,
			 `domain` varchar(255),
			 `api_key` varchar(255),
			 `status` varchar(20) NOT NULL DEFAULT 'new',
			 `goes_left` int(6) NOT NULL DEFAULT 0,
			 `simile_limit` tinyint(4) NOT NULL DEFAULT 3,
			 UNIQUE KEY `id` (`id`)
		);";
		$wpdb->query($structure);
	}
}
function siteselector_initialize(){
	global $wpdb, $siteselectorConfigTable;
	$host = get_option('siteurl');
	$serviceBase = "http://siteselector.scideas.net/initialize_remote.php";
	$params['host'] = $host;
	//$service = $serviceBase."?host=".$host;
    //$result = file_get_contents($service);
	$result = siteselector_httpPost($serviceBase,$params);
	$result=json_decode($result,true);
	siteselector_log($result['message']);
	if($result['status'] == 'success' && $result['message'] == 'initialization complete'){
		$key = $result['key'];
		$freeGoes = $result['free_goes'];
		if($wpdb->query("INSERT into $siteselectorConfigTable (`domain`,`api_key`,`status`,`goes_left`) VALUES('$host','$key','new',$freeGoes)") === FALSE){
			siteselector_log("Initialization failed (".$wpdb->last_error.")");
		}else
		{
			siteselector_log("siteselector initialized");
		}
	}else
	if($result['status'] == 'success' && strstr($result['message'],'already registered')){
		$key = $result['key'];
		$goesLeft = $result['goes_left'];
		$query = $wpdb->prepare("UPDATE `$siteselectorConfigTable` set `goes_left`=%d",$goesLeft);
		$res = $wpdb->query($query);
		if(FALSE === $res){
			siteselector_log("Goes left update failed (".$wpdb->last_error.")");
		}else
		{
			siteselector_log("siteselector goes left updated");
		}
	}else
	{
		siteselector_log("Initialization failed (".$result['message'].")");
	}
	//var_dump($result);
	return json_encode($result);
	//exit;
}
//
//initialize over ajax
add_action('wp_ajax_nopriv_siteselector_ajax_initialize','siteselector_ajax_initialize');
add_action('wp_ajax_siteselector_ajax_initialize','siteselector_ajax_initialize');
function siteselector_ajax_initialize(){
	echo siteselector_initialize();
	exit;
}
function load_admin_css(){
	wp_enqueue_style('siteselector_style', siteselector_URL . '/siteselector_plugin_admin.css');
}
//find names
add_action('wp_ajax_nopriv_siteselector_find_names','siteselector_find_names');
add_action('wp_ajax_siteselector_find_names','siteselector_find_names');
function siteselector_find_names(){
	global $wpdb,$siteselectorConfigTable;
	$config = $wpdb->get_results("SELECT * from `$siteselectorConfigTable`");
	if(count($config) > 0){
		$key = $config[0]->api_key;
		$simLim = $config[0]->simile_limit;
	}
	if($simLim == ''){$simLim = 3;}
	$k1 = $_POST['k1'];
	$k2 = $_POST['k2'];
	$host = get_option('siteurl');
	$serviceBase = "http://siteselector.scideas.net/permutate_remote.php";
	$service = $serviceBase."?k1=".$k1."&k2=".$k2."&simlim=".$simLim."&host=".$host."&key=".$key;
    $result = file_get_contents($service);
	$decoded = json_decode($result,true);
	$status = $decoded['service_status'];
	$goesLeft = $decoded['goes_left'];
	if($wpdb->query("UPDATE $siteselectorConfigTable SET `status`='$status',`goes_left`=$goesLeft") === FALSE){
		siteselector_log("status update failed (".$wpdb->last_error.")");
	}
	//var_dump($result);
	echo $result;
	exit;
}
//check url
add_action('wp_ajax_nopriv_siteselector_check_url','siteselector_check_url');
add_action('wp_ajax_siteselector_check_url','siteselector_check_url');
function siteselector_check_url(){
	$url = $_POST['url'];
	$suffixList = implode(',',$_POST['suffix']);
	$itemNum = $_POST['item_num'];
	$serviceBase = "http://siteselector.scideas.net/check_remote.php";
	$service = $serviceBase."?url=".$url."&suffix=".$suffixList."&item_num=".$itemNum;
    $result = file_get_contents($service);
	//var_dump($result);
	echo $result;
	exit;
}
//
function siteselector_arraySort(&$arr, $col, $dir = SORT_DESC) {
    $sort_col = array();
    foreach ($arr as $key=> $row) {
        $sort_col[$key] = $row[$col];
    }

    array_multisort($sort_col, $dir, $arr);
}
//
function siteselector_form($number){
	return number_format($number, 2, '.', ' ');
}
function siteselector_log($text){
	$logFile = __DIR__."/siteselector.log";
	error_log($text."\n", 3, $logFile);	
}
/* do shortcode */
add_shortcode("siteselector","siteselector_shortcode");
function siteselector_shortcode($atts)
{
	global $wpdb, $siteselectorConfigTable, $siteselector_shortcode_loaded;
	wp_enqueue_style('siteselector_plugin_style', siteselector_URL . '/siteselector_plugin.css');
	//wp_enqueue_script("siteselector_javascript_main",siteselector_URL."/main.js");
	
	$config = $wpdb->get_results("SELECT * from `$siteselectorConfigTable`");
	if(count($config) > 0){
		$status = $config[0]->status;

	}
	

    ob_start();
    //echo "Yes, its working fine"; // this will not print out
	
	$siteselector_shortcode_loaded = true;
	extract(shortcode_atts(array(
		'name' => '',
	), $atts));
	
	//$button_name = "{$name}";
	$shortcodeName = "{$name}";
	if($shortcodeName != 'chooser'){return;}
	$out = '<div id="outer_container">
		<div id="container" class="centre">			

		
					<div id="title_text">Enter one or two keywords that describe the idea behind your new site</div>
		
						<div id="keyword_form" class="form_table my_border">
							<div class="row">
								<span class="keyword_cell bold" style="width:10%;max-width:10%;">keywords</span>
								<span class="keyword_input_cell"><input class="my_input" type="text" id="keyword_1" placeholder="keyword 1"/></span>
								<span class="keyword_input_cell"><input class="my_input" type="text" id="keyword_2" placeholder="keyword 2"/></span>';
								
								
								if($status != 'free'){
									$out .= '<span class="keyword_cell"><div id="suffix_button" class="large_button my_border" onclick="openSuffix(false);">suffix</div></span>';
								}
								
								
								$out .= '<span class="keyword_cell"><div class="large_button my_border" onclick="findNames();">find</div></span>
								
							</div>
						</div>
		
					<div id="suffix_box" class="form_box">
				<div id="close_suffix" class="close_button" onclick=closeBox("suffix");><img class="close_image" src="'.siteselector_URL."/media/close.png".'" /></div>
				
				<div id="" class="form_table">
					<div class="row">
						<span class="cell">Add or remove any suffix leaving a space between each one</span>
					</div>
					<div class="row">
						<span class="input_span"><input class="my_input input_span_input"  type="text" id="suffix_list" name="suffix_list" /></span>
					</div>
				</div>
					<div class="save_button"><span class="large_button my_border" onclick="saveSuffix();">save</span></span></div>
				
				
			</div>
		
					<div id="find_status_box"></div>
						
					<div id="progress_table" class="my_border">
						<!--<div class="row">-->
							<span id="spinner"></span>
							<span id="spinner_text">idle</span>
							<span id="progress_bar_span"><div id="progress_bar_div"><div id="progress_bar"></div></div></span>
							<span id="check_cancel_span"><div id="cancel_button" class="small_button my_border" onclick="cancelCheck();">cancel</div></span>		
						<!--</div>-->
					</div>
					<div id="results_table" class="centre my_border">
						<div class="row">
							<span id="available" class="available_cell">
							<div class="results_headers bold">Available names</div>
							</span>
							<span id="registered" class="registered_cell">
							<div class="results_headers bold">Registered names</div>
							</span>
							<span id="taken" class="taken_cell">
							<div class="results_headers bold">Possible Competition</div>
							</span>
						</div>
						<div id="large_image"></div>
					</div>

				</div>
				';
	
	
	
	
	
	
	
	
	
			echo $out;//to ob

			$result = ob_get_contents(); // get everything in to $result variable
			ob_end_clean();
			return $result;
	
}


function siteselector_script() {
	//available in all pages
	global $wpdb,$siteselector_shortcode_loaded;
	if(!$siteselector_shortcode_loaded){return;}
	?>
	<script>
	var taken_tally = 0;
	jQuery('#large_image').hide();
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
				jQuery('#progress_bar').css('background-color', 'hsla(0,0%,86%,1.00)');
				jQuery('#progress_bar').html(t+' of '+T);
				//jQuery('#progress_bar_div').html(tally+'/'+L);
				//process all items in r['list']
				var items = r['list'];
				var d, red;
				for(var c=0;c<items.length;c++){
					d = items[c];
					if(d['status'] == 'available'){
						var i = document.createElement('div');
						i.className = "available_item result_item";
						i.innerHTML = d['url'] + '<a style="text-decoration:none; border:none;" href="https://www.namecheap.com/domains/registration/results.aspx?domain='+d['url']+'&aff=87458" target="_blank">&shy;register</a>';
						document.getElementById('available').appendChild(i);
					}else
					if(d['status'] == 'registered'){
						//registered but not hosted
						var i = document.createElement('div');
						i.className = "registered_item result_item";
						i.innerHTML = d['url']+' <br><date>'+d['exp_date']+'</date>';
						document.getElementById('registered').appendChild(i);
					}else
					{
						//taken
						var i = document.createElement('div');
						i.className = "taken_item result_item";
						i.id = 'taken_item_'+taken_tally;
						if(d['image'] == ''){
							i.innerHTML = 'Domain name probably for sale<br><a target="_blank" href="http://'+d['url']+'">'+d['url']+'</a>';
						}
						if(d['image'] == 'white'){
							i.innerHTML = 'No image available<br><a target="_blank" href="http://'+d['url']+'">'+d['url']+'</a>';
						}
						else
						{
							if(d['redirect']){
								red = '<red>(redirects to '+d['redirect']+')</red>';
							}else
							{
								red = '';	
							}
							i.innerHTML = '<a target="_blank" href="http://'+d['url']+'"><img onmouseover=showLargeImage("'+d['image']+'","'+taken_tally+'"); onmouseout="hideLargeImage();" class="url_frame" src="'+d['image']+'"></img></a><a target="_blank" href="http://'+d['url']+'">'+d['url']+'</a>'+red;
					
						}
						document.getElementById('taken').appendChild(i);
						taken_tally++;
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
function showLargeImage(src,p){
	console.log("Showing image: "+src);
	jQuery('#large_image').html('<img src="'+src+'"/>');
	var p_offset = jQuery('#taken_item_'+p).offset();
	//var new_top = parseInt(p) * 50;
	var new_top = parseInt((0.9*p_offset.top) - 500);
	console.log("top for taken_item_"+p+": "+p_offset.top);
	var offset = jQuery('#large_image').offset();
	jQuery('#large_image').offset({ top: new_top, left: '25%'});
	jQuery('#large_image').show();
}
function hideLargeImage(){
	jQuery('#large_image').hide();
}
</script>
   <?php
}
/**
 * remove annoying footer thankyou from wordpress that stops people from entering text
 */
function hide_wordpress_thankyou() {
  echo '<style type="text/css">#wpfooter {display:none;}</style>';
}
 
add_action('admin_head', 'hide_wordpress_thankyou');
add_action( 'wp_footer', 'siteselector_script' ,200);
?>