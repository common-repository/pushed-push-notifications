jQuery( document ).ready(function() {
	loadCredentials(jQuery("select[name='pushed_target_type[text_string]']").val());

	jQuery("select[name='pushed_target_type[text_string]']").on('change', function() {
		loadCredentials(this.value);
	});

	jQuery("#verify_pushed_credentials").on('click', function() {
		verifyCredentials(jQuery("select[name='pushed_target_type[text_string]']").val());
	});
});

function loadCredentials(source)
{
	resetResultBox();

	switch(source) {
		case 'multiple':
			hide('single');
			show('multiple');
		break;
		case 'app':
		case 'channel':
			hide('multiple');
			show('single');
			jQuery("textarea[name='pushed_sources[text_area]']").html('');
		break;
		default:
			hide('multiple');
			hide('single');
			jQuery("textarea[name='pushed_sources[text_area]']").html('');
			return false;
		break;
	}	
}

function show(selector)
{
	jQuery("." + selector + " input").removeAttr('disabled');
	jQuery("." + selector).removeClass('hidden');
}

function hide(selector)
{
	jQuery("." + selector + " input").attr('disabled','disabled');
	jQuery("." + selector).addClass('hidden');
}

function connectionError(source)
{
	var message = "<b>Could not connect to Pushed API.</b> This is not necessarily a bad thing. At some circumstances outgoing traffic is blocked depending on your server/browser settings.&nbsp;<br/>";
	message += "&nbsp;&nbsp;You can manually check your credentials here: <a href=\"" + buildVerificationLink() + "\" target=\"_blank\">https://account.pushed.co/integrations/wordpress</a>.&nbsp;<br/>";
	message += "&nbsp;&nbsp;If the error keeps reproducing <a href=\"https://about.pushed.co/support\" target=\"_blank\">Contact Us</a>.&nbsp;";

	jQuery("#results_section_body").addClass('pushed_error_msg');
	jQuery("#results_section_body").html(message);
	if (source != 'multiple') {
		jQuery("input[type='submit']").removeAttr('disabled');
	}

	var alert_message = "Could not connect to Pushed API. Please use manual verification link.";
	alert(alert_message);
}

function resetResultBox()
{
	jQuery("#results_section_body").html('');
	jQuery("#results_section_body").removeClass('pushed_success_msg pushed_error_msg pushed_warning_msg');
}

function showError(message)
{
	resetResultBox();
	jQuery("#results_section_body").addClass('pushed_error_msg');
	jQuery("#results_section_body").html(message);
	alert(message);
}

function buildVerificationLink()
{
	var url = 'https://account.pushed.co/integrations/wordpress?';
	url += 'target=' + jQuery('select[name="pushed_target_type[text_string]"]').val();
	url += '&alias=' + jQuery('input[name="pushed_target_alias[text_string]"]').val();
	url += '&app_key=' + jQuery('input[name="pushed_app_key[text_string]"]').val();
	url += '&app_secret=' + jQuery('input[name="pushed_app_secret[text_string]"]').val();

	return url;
}

function verifyCredentials(source)
{
	resetResultBox();

	var message = '<img src="https://s3-eu-west-1.amazonaws.com/pushed.co/media/wordpress-plugin/loader.gif"> Verifying credentials with Pushed API... ';
	var loading_message = message;
	jQuery("#results_section_body").addClass('pushed_warning_msg');
	jQuery("#results_section_body").html(message);

	var params = {};
	params.action = 'validate_pushed_credentials';
	params.source = source;

	switch(source) {
		case 'multiple':
			params.api_key = jQuery("input[name='pushed_api_key[text_string]']").val();
			params.fetch_sources = true;
		break;
		case 'app':
		case 'channel':
			params.target_alias = jQuery("input[name='pushed_target_alias[text_string]']").val();
			params.app_key = jQuery("input[name='pushed_app_key[text_string]']").val();
			params.app_secret = jQuery("input[name='pushed_app_secret[text_string]']").val();
		break;
		default:
			showError('Please select a valid source.');
			return false;
		break;
	}

	// Loop through each param to check it has a value
	for (var key in params) {
		if (params.hasOwnProperty(key)) {
			if (params[key] === "") {
				showError(key  + ' is empty!');
				return false;
			}
		}
	}

	setTimeout(function(){
		if (jQuery("#results_section_body").html() == loading_message) {
			connectionError(source);
		}
	}, 1000);

	jQuery.post(ajaxurl, params, function(data) {
		var response = jQuery.parseJSON(data);

		if (response === null || response === undefined) {
			connectionError(source);
			return false;
		}

		message = typeof response.error != "undefined" ? response.error.message : response.response.message;
		var success = typeof response.error == "undefined";
		
		if (success && source == 'multiple') {
			var sources = response.response.data.sources;
			if (sources.length === 0) {
				showError('Credentials are valid, but no elegible applications or channels were found. Please create an app in Pushed in order to send notifications.');
				return false;
			} else {
				jQuery("textarea[name='pushed_sources[text_area]']").html(JSON.stringify(sources));
			}
		}

		resetResultBox();

		if (success) {
			jQuery("#results_section_body").addClass('pushed_success_msg');
			jQuery("input[type='submit']").removeAttr('disabled');
		} else {
			jQuery("#results_section_body").addClass('pushed_error_msg');
		}
		
		jQuery("#results_section_body").html(message);
		alert(message);
	})
	.error(function(){
		connectionError(source);
		return false;
	});
}