// variables
var tally = L = U = 0;
var list = [];
var perm_offset = 5;
var suffix_list = ['.com','.net'];
var low_goes = 10;
//
jQuery(document).ready(function(){
		

	if(jQuery('#reload').val() == 'yes'){
		jQuery("#reloadForm").submit();
	}
	jQuery('.form_box').hide();
	jQuery('#progress_table').hide();
	jQuery('#find_status_box').hide();
	jQuery('#confirm_window').hide();
	if(jQuery('#goes_left').html() > low_goes || jQuery('#goes_left').html() < 1){
   		jQuery('#buy_reminder_window').hide();
	}
	
});


//

//
function cancelCheck(){
	U = L = tally = 0;
	list = [];
	jQuery('#progress_table').hide();
}
//

//
function setIframeSrc(frame_id, source) {
  var iframe1 = document.getElementById(frame_id);
  var iframeLoadTimeout = setTimeout(function(frame_id) {
      //window.stop();
	  document.getElementById('taken').removeChild(iframe1);
      consolelog("iframe load timed out, iframe removed");
  }, 5000);
  iframe1.src = source;
  iframe1.onload = function() {
      clearTimeout(iframeLoadTimeout); 
      //alert("iframe has loaded successfully!");
  }
}
//
function removeElement(index){
	var elem,parent; 
	if(elem = document.getElementById(index)){
		var parent = elem.parentNode;
		parent.removeChild(elem);
	}
}
//
function emptyTable(t,o){
	//var rows = document.getElementsByName('parameter_row');
	var rows = t.childNodes;
	var n = rows.length;
	var c = 0;
	console.log('n '+n);
	for(c=o;c<n;c++){
		if(rows[c] && rows[c].innerHTML){	
			console.log('removing table row '+c+' contents: '+rows[c].innerHTML);
			t.removeChild(rows[c]);
		}
	}
	rows = t.childNodes;
	n = rows.length;
	console.log('new n: '+n);
	if(n > o){
		if(rows[n-1].innerHTML){
			setTimeout(function() {
				emptyTable(t,o);
			}, 1000);
		}
	}
	return true;
}
//
function openSuffix(dim){
	var text = '';
	for(var n=0;n<suffix_list.length;n++){
		text = text + ' ' + suffix_list[n];
	}
	jQuery('#suffix_list').val(text);
	openBox('suffix',dim);
}
function saveSuffix(){
	closeBox('suffix');
	suffix_list = jQuery('#suffix_list').val().split(' ');
	suffix_list = suffix_list.filter(function(i){ return i != '' });
	for(var n=0;n<suffix_list.length;n++){
		console.log('suffix: '+suffix_list[n]);
	}
}
//
function closeBox(id){
	jQuery('#'+id+'_box').hide();
	unDimPage();
}
//
function sendForm(form){
	if(checkFormValid(form)){
		document.getElementById(form+'_form').submit();
	}
}
//
function openBox(box,dim){
	var cont = document.getElementById(box+'_box');
	//dim the rest
	if(dim){
		dimPage(box+'_box');
	}
	jQuery('#'+box+'_box').show();
	//put borders back to default colour
	if(document.getElementById(box+'_form')){
		var inputs = document.getElementById(box+'_form').getElementsByTagName('input');
		for(var c=0;c<inputs.length;c++){
			inputs[c].style.borderColor = '';
		}
	}
	//reset error mesage
	if(document.getElementById(box+'_status_box')){
		var error_box = document.getElementById(box+'_status_box');
		error_box.innerHTML = '';
	}
	
	//
	cont.style.display = 'inline-block';
	var outer = document.getElementById('outer_container');
	if(box != 'suffix'){
		outer.scrollIntoView(true);
	}
}
//
function sendLogin(){
	var name = jQuery('#login_name').val();
	var password = jQuery('#login_password').val();
	jQuery.ajax({
		url: "com.php",
		data: {
			login_name: name,
			login_password: password
		},
	
		method: "post",

		success: function(r){
			console.log("Ajax success "+r);
			statusBox = document.getElementById('login_status_box');
			if(r == 'success'){
				//submit form
				document.getElementById('login_form').submit();
			}else
			{
				if(r == 'Wrong password'){
					jQuery('#login_password').addClass('highlight');
				}else
				if(r == 'Wrong username'){
					jQuery('#login_name').addClass('highlight');
				}
				statusBox.innerHTML = r;
			}		
			
		},
		error: function(jqXHR,error, errorThrown,data) {  
			//console.log(jqXHR.responseText + ' '+error+' '+errorThrown+' '+data);
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
		timeout: 10000
	});
}
//

//
function checkFormValid(form){
	//check all form_status_icon items have a tick as source
	var items = document.getElementsByName(form+'_status_icon');
	for(var n=0;n<items.length;n++){
		if(items[n].src.indexOf("blue_tick") < 0){
			return false;	
		}
	}
	return true;
}
//
function validateContactForm(){
	//check fields
	if(!checkName())return false;
	if(!checkEmail())return false;
	if(!checkMessage())return false;
	if(!checkSpam())return false;
	return true;
}
function checkName(form){
	var error_box = document.getElementById(form+'_status_box');
	var name_box = document.getElementById(form+'_name');
	var name = name_box.value;
	if(name.length < 3){
		name_box.style.borderColor = '#EC0834';
		error_box.innerHTML = 'Please choose a username with at least 3 characters';
		return false;
	}else
	{
		//check not in use
		checkDbFor(form,'name',name);
		name_box.style.borderColor = '';
		error_box.innerHTML = '';
	}
	return true;
}
function checkPassword(form){
	var error_box = document.getElementById(form+'_status_box');
	var pass_box = document.getElementById(form+'_password');
	var input_status_box = document.getElementById(form+'_password_status_box');
	var pass = pass_box.value;
	if(pass.length < 8){
		pass_box.style.borderColor = '#EC0834';
		error_box.innerHTML = 'Please choose a password with at least 8 characters';
		input_status_box.innerHTML = '<img name="'+form+'_status_icon" src="media/black_cross.png" class="icon"></img>';
		return false;
	}else
	{
		pass_box.style.borderColor = '';
		error_box.innerHTML = '';
		input_status_box.innerHTML = '<img name="'+form+'_status_icon" src="media/blue_tick.png" class="icon"></img>';
	}
	return true;
}
function checkPasswordRepeat(form){
	var error_box = document.getElementById(form+'_status_box');
	var pass_box = document.getElementById(form+'_password');
	var input_status_box = document.getElementById(form+'_password_r_status_box');
	var pass_box_r = document.getElementById(form+'_password_r');
	var pass = pass_box.value;
	var pass_r = pass_box_r.value;
	if(pass != pass_r){
		pass_box_r.style.borderColor = '#EC0834';
		error_box.innerHTML = 'Passwords don\'t match';
		input_status_box.innerHTML = '<img name="'+form+'_status_icon" src="media/black_cross.png" class="icon"></img>';
		return false;
	}else
	{
		pass_box_r.style.borderColor = '';
		error_box.innerHTML = '';
		input_status_box.innerHTML = '<img name="'+form+'_status_icon" src="media/blue_tick.png" class="icon"></img>';
	}
	return true;
}
function checkEmail(form){
	var error_box = document.getElementById(form+'_status_box');
	var email_box = document.getElementById(form+'_email');
	var input_status_box = document.getElementById(form+'_email_status_box');
	var email = email_box.value;
	if(email.indexOf('@') < 0){
		email_box.style.borderColor = '#EC0834';
		error_box.innerHTML = 'Please supply a valid email address';
		input_status_box.innerHTML = '<img name="'+form+'_status_icon" src="media/black_cross.png" class="icon"></img>';
		return false;
	}else
	{
		if(form == 'register'){
			//check not in use
			checkDbFor(form,'email',email);
		}
		email_box.style.borderColor = '';
		error_box.innerHTML = '';
		input_status_box.innerHTML = '<img name="'+form+'_status_icon" src="media/blue_tick.png" class="icon"></img>';
	}
	return true;
}
function checkMessage(form){
	var error_box = document.getElementById('contact_status_box');
	var message_box = document.getElementById('contact_message');
	var input_status_box = document.getElementById(form+'_message_status_box');
	var message = message_box.value;
	if(message.length < 30){
		message_box.style.borderColor = '#EC0834';
		error_box.innerHTML = 'Please supply more details';
		input_status_box.innerHTML = '<img name="'+form+'_status_icon" src="media/black_cross.png" class="icon"></img>';
		return false;
	}else
	{
		message_box.style.borderColor = '';
		error_box.innerHTML = '';
		input_status_box.innerHTML = '<img name="'+form+'_status_icon" src="media/blue_tick.png" class="icon"></img>';
	}
	return true;
}
function checkSpam(form){
	var error_box = document.getElementById(form+'_status_box');
	var spam_box = document.getElementById(form+'_spam');
	var input_status_box = document.getElementById(form+'_spam_status_box');
	var spam_number = document.getElementById('spam_number').value;//system number
	var spam = parseInt(spam_box.value);//user input
	//get 2nd and 4th digits
	var first = parseInt(spam_number.charAt(1));
	var fourth = parseInt(spam_number.charAt(3));
	//console.log('first: '+first+' fourth: '+fourth+' answer: '+spam);
	if(first + fourth != spam){
		spam_box.style.borderColor = '#EC0834';
		spam_box.value = '';
		error_box.innerHTML = 'Please correct your anti-spam answer';
		input_status_box.innerHTML = '<img name="'+form+'_status_icon" src="media/black_cross.png" class="icon"></img>';
		return false;
	}else
	{
		spam_box.style.borderColor = '';
		error_box.innerHTML = '';
		input_status_box.innerHTML = '<img name="'+form+'_status_icon" src="media/blue_tick.png" class="icon"></img>';
	}
	return true;
}
function openContactSuccess(){
	var cont = document.getElementById('contact_success_box');
	dimPage('contact_success_box');
	cont.style.opacity = 1.0;
	cont.style.display = 'inline-block';
}
function closeContactSuccess(){
	var box = document.getElementById('contact_success_box');
	box.style.display = 'none';
	unDimPage();
}
function dimPage(except){
	//dim the rest
	var dim_area = document.getElementById('outer_container');
	var children = dim_area.children;
	for(var n=0;n<children.length;n++){
	  if(children[n].id !== except){
	   children[n].style.opacity = 0.2; 
	   //console.log(children[n].id);
	  }
	}
}
function unDimPage(){
	var dim_area = document.getElementById('outer_container');
	var children = dim_area.children;
	for(var n=0;n<children.length;n++){        
	   children[n].style.opacity = 1.0; 
	}
}
function dialog(task,detail){
	//task is the id of the form, detail is the dialog presented
	var message_window = document.getElementById('message_window');
	message_window.style.display =  'inline';
	var header = document.getElementById('message_header');
	header.innerHTML = task;
	var message_body = document.getElementById('message_body');
	message_body.innerHTML = detail;
	var submit_button = document.getElementById('dialog_submit_button');
	submit_button.setAttribute('onclick',"submitDialogForm('"+task+"');");
}
function cancelDialog(){
	var message_window = document.getElementById('message_window');
	message_window.style.display =  'none';
}
function submitDialogForm(task){
	//task is the id of the form to be submitted
	var message_window = document.getElementById('message_window');
	message_window.style.display =  'none';
	if(task == 'login'){
		openBox('login',true);
		return false;
	}
	if(task == 'subscribe'){
		openSubscribe();
		return false;
	}
	var form_id = task+'Form';
	var theForm = document.getElementById(form_id);	
	theForm.submit();
}
//
function openConfirm(detail){
	//task is the id of the form, detail is the dialog presented
	//var confirm_window = document.getElementById('confirm_window');
	//confirm_window.style.display =  'inline';
	var confirm_body = document.getElementById('confirm_body');
	confirm_body.innerHTML = detail;
	setTimeout(function() {
		cancelConfirm();
	}, 6000);
	jQuery('#confirm_window').show();
}
function cancelConfirm(){
	//var confirm_window = document.getElementById('confirm_window');
	//confirm_window.style.display =  'none';
	jQuery('#confirm_window').hide(1200);
}
