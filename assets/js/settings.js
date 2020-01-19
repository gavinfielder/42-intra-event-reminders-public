$(start);

/**
 * Intialization -- called when document has finished loading.
 */
function start() {
	add_event_listeners();
	read_settings(); //This must be done after adding event listeners.
}

function get_settings() {
	return (generate_key_value_pairs($("form#settings-form").serializeArray()));
}

/**
 * Adds all event listeners.
 */
function add_event_listeners() {
	$("input#write-settings").click(function(event) {
		event.preventDefault();
		$(this).attr("disabled", true).attr('value', "Submitting...");
		check_phone_number();
	});

	$("select.form-control.select").change(function() {
		if ($(this).val() == 'disable')
			$(this).parent().nextAll().attr('hidden', true);
		else
			$(this).parent().nextAll().attr('hidden', false);
	});

	$("input#cancel-sms-verif").click(function(event) {
		event.preventDefault();
		$("div#sms-verif-modal").fadeOut(150);
		reset_sms_modal(false);
		reset_submit_button();
	});

	$("input#confirm-sms-verif").click(function(event) {
		event.preventDefault();
		$(this).parent().find('input').attr('disabled', true);
		$(this).attr('value', "Verifying...");
		check_sms_code($("input#confirmation-code").val());
	});
}

/**
 * Makes a request to the server to retrieve the user's settings
 */
function read_settings() {
	$.ajax({
		type: 'POST',
		url: '/src/return-event-reminder-settings.php',
		success: function(response) {
			fill_forms(JSON.parse(response));
		},
		failure: function(response) {
			send_toast("Unable to load settings.", true);
		}
	});
}

/**
 * Fills user settings based off the settings data we grabbed from the server.
 */
function fill_forms(settings) {
	if (settings['reminder_platforms_json'])
		load_platforms(settings['reminder_platforms_json']);
	load_times(settings['reminder_times_json']);
}

/**
 * Writes user's platform settings to their respective elements if they are defined.
 */
function load_platforms(platforms) {
	load_platform_data(platforms['user-email'], 'input#user-email');
	load_platform_data(platforms['user-slack'], 'input#user-slack');
	load_platform_data(platforms['user-sms'], 'input#user-sms');
	load_platform_data(platforms['user-whatsapp'], 'input#user-whatsapp');
}

/**
 * Writes the appropriate data into a text-input field or calls to disable the field depending on whether or not the platform is available for the user's campus.
 */
function load_platform_data(platform, element) {
	if (platform === "unavailable")
		disable_platform(element);
	else if (platform)
		$(element).attr('value', platform);
}

/**
 * Disables a platforms text-input field and displays an unavailability message.
 */
function disable_platform(element) {
	$(element).attr('disabled', true);
	$(element).nextAll().html("This platform isn't available at your campus.");
}

/**
 * Writes user's platform settings to their respective elements if they are defined.
 */
function load_times(times) {
	var option;
	var options_length = 3;
	for (var i = 0; i < options_length; i++) {
		var option = i + 1;
		if (times['reminder-time-' + option + '-platform']) {
			var platform = $('select#reminder-time-' + option + '-platform');
			var time_amount = $('input#reminder-time-' + option + '-amount');
			var time_unit = $('select#reminder-time-' + option + '-unit');
			if (times['reminder-time-' + option + '-platform'] == 'disable' || times['reminder-time-' + option + '-amount'] == 0) {
				$(platform).val('disable').attr('selected', true); //Select 'No reminder'
				$(time_amount).parent().attr('hidden', true); //Hide time selection
			} else {
				$(platform).val(times['reminder-time-' + option + '-platform'].toLowerCase()).attr("selected", "true");
				$(time_amount).attr('value', times['reminder-time-' + option + '-amount']);
				$(time_unit).val(times['reminder-time-' + option + '-unit'].toLowerCase()).attr("selected", "true");
			}
		}
	}
}

/**
 * Generates key-value pairs based off of form data for easy iteration.
 */
function generate_key_value_pairs(form_data) {
	var kvp = form_data.reduce(function(k, v) {
		k[v.name] = v.value;
		return (k);
	}, new Array());
	return (kvp);
}

function check_phone_number() {
	$.ajax({
		type: 'POST',
		url: '/src/check-phone-number.php',
		data: {
			'user-sms': get_reminder_platforms_json(get_settings())['user-sms']
		},
		success: function(response) {
			switch (response) {
				case "success":
					save_settings();
					break;

				case "verify":
					$("div#sms-verif-modal").fadeIn(150);
					break;
	
				case "invalid":
					send_toast("Invalid phone number.", true);
					$("span#invalid-phone-number").fadeIn(300);
					reset_submit_button();
					break;

				default:
					send_toast("Unable to process request.", true);
					break;
			}
		},
		error: function(response) {
			send_toast(response, true);
		}
	});
}

function check_sms_code(code) {
	$.ajax({
		type: 'POST',
		url: '/src/check-sms-code.php',
		data: {
			"code": code
		},
		success: function (response) {
			send_toast(response);
			reset_sms_modal(true);
			$("div#sms-verif-modal").fadeOut(150);
			save_settings();
		},
		error: function () {
			reset_sms_modal(false);
			send_toast("Unable to process request.", true);
		}
	});
}

function reset_sms_modal(clear_confirmation_code) {
	$("input#confirm-sms-verif").parent().find('input').attr('disabled', false);
	$("input#confirm-sms-verif").attr('value', "Confirm");
	if (clear_confirmation_code)
		$("input#confirmation-code").val('');
}

function reset_submit_button() {
	$("input#write-settings").attr('disabled', false).attr('value', 'Submit');
}

function save_settings() {
	var settings = get_settings();
	var platforms_json = JSON.stringify(get_reminder_platforms_json(settings));
	var times_json = JSON.stringify(get_reminder_times_json(settings));
	write_settings(platforms_json, times_json);
}

/**
 * Writes the user's settings to the settings database.
 */
function write_settings(platforms_json, times_json) {
	$('.error-msg').fadeOut(300);
	$.ajax({
		type: 'POST',
		url: '/src/write-event-reminder-settings.php',
		data: {
			"reminder_times_json": times_json,
			"reminder_platforms_json": platforms_json
		},
		success: function (response) {
			send_toast(response);
		},
		error: function (response) {
			if (response.statusText == 'Invalid Email') {
				$('span#invalid-email').fadeIn(300);
				send_toast(response.statusText, true);
			} else if (response.statusText == 'No Platforms Given') {
				$('div#no-platforms-given').fadeIn(300);
				send_toast(response.statusText, true);
			} else if (response.statusText.includes('Invalid Amount')) {
				var response_id = parseInt(response.statusText.substring(response.statusText.length - 1));
				send_toast('Invalid reminder time amount.', true);
				$('span#invalid-time-' + response_id).fadeIn(300);
			} else
				send_toast("Unable to process request.", true);
		},
		complete: function() {
			reset_submit_button();
		}
	});
}

function send_toast(message, error) {
	Toastify({
		text: message,
		gravity: "top",
		position: "right",
		backgroundColor: (!error ? "#5cb85c" : "#D8636F"),
		duration: 2200
	}).showToast();
}

/**
 * Strips the necessary platform information from the form and formats it for storage.
 */
function get_reminder_platforms_json(data) {
	var platforms = {
		"user-email": data['user-email'].trim(),
		"user-slack": data['user-slack'].trim(),
		"user-sms": data['user-sms'].trim(),
		"user-whatsapp": data['user-whatsapp']//.trim()
	};
	return (platforms);
}

/**
 * Strips the necessary time information from the form and formats it for storage.
 */
function get_reminder_times_json(data) {
	var times = {
		"reminder-time-1-amount": data['reminder-time-1-amount'],
		"reminder-time-1-platform": data['reminder-time-1-platform'],
		"reminder-time-1-unit": data['reminder-time-1-unit'],
		"reminder-time-2-amount": data['reminder-time-2-amount'],
		"reminder-time-2-platform": data['reminder-time-2-platform'],
		"reminder-time-2-unit": data['reminder-time-2-unit'],
		"reminder-time-3-amount": data['reminder-time-3-amount'],
		"reminder-time-3-platform": data['reminder-time-3-platform'],
		"reminder-time-3-unit": data['reminder-time-3-unit']
	};
	return (times);
}
