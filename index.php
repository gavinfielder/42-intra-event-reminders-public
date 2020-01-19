<?php

require_once(__DIR__ . '/src/master.php');

$api = EventRemindersFactory::Create('IFtApiHandler', true);
$me = $api->access_me();
$_SESSION['user_id'] = $me['user_id'];
$_SESSION['campus_id'] = $me['campus_id'];

?>

<html>
<head>
	<title>42 Event Reminder Settings</title>
	<link href="assets/css/style.css" rel="stylesheet" type="text/css">
	<link href="assets/css/toastify.css" rel="stylesheet" type="text/css">
</head>

<body>
	<div id="sms-verif-modal" class="modal">
		<div class="modal-window">
			<div class="modal-content">
				<div class="modal-title">Confirm Opt-in to Receive SMS Reminders</div>
				<form id="optin-form" class="simple-form form-horizontal app-form" role="form" novalidate="novalidate" enctype="multipart/form-data" accept-charset="UTF-8" method="post">
					<ul class="list-group">
						<li class="list-group-item">
							<div class="modal-form-group">
								<label class="control-label" for="user-email">
									Confirmation Code:
								</label>
								<input class="form-control small-number-input" type="number" name="confirmation-code" id="confirmation-code">
								<p class="help-block modal-help-block">
									Enter the 6-digit confirmation code we sent.
								</p>
							</div>
						</li>
					</ul>
					<div class="form-group">
						<div>
							<input id="confirm-sms-verif" type="submit" value="Confirm" class="btn btn-primary modal-button">
							<input id="cancel-sms-verif" type="submit" value="Cancel" class="btn btn-primary red-bg modal-button">
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
	<div id="content-container">
	<h1>42 Event Reminder Settings</h1>
	<form id="settings-form" class="simple-form form-horizontal app-form" role="form" novalidate="novalidate" id="myform1" enctype="multipart/form-data" action="src/route_settings_update_request.php" accept-charset="UTF-8" method="post">
		<ul class="list-group">
			<li class="list-group-item">
				<div class="text-center mb-4 p-2 text-muted text-size-14-fix">
					<b>Platforms</b>
					<br>
					<small>
						How would you like to receive your reminders?
					</small>
				</div>

				<div class="form-group">
					<label class="col-sm-2 control-label" for="user-email">
						Email:
					</label>
					<div class="col-sm-5">
						<input class="form-control" type="text" name="user-email" id="user-email">
						<p class="help-block">
							<span id='invalid-email' class='error-msg'>Please enter a valid email, or leave it blank.<br></span>
							Specify the email you wish email notifications be sent to.
						</p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-2 control-label" for="user-slack">
						Slack:
					</label>
					<div class="col-sm-5">
						<input class="form-control" type="text" name="user-slack" id="user-slack">
						<p class="help-block">
							Specify either the display name or your username for the slack account you wish slack messages be sent to.
						</p>
					</div>
				</div>

				<div class="form-group" id="phone-number-input-container">
					<label class="col-sm-2 control-label" for="user-sms">
						SMS:
					</label>
					<div class="col-sm-5">
						<input class="form-control" type="text" name="user-sms" id="user-sms">
						<p class="help-block">
							<span id='invalid-phone-number' class='error-msg'>Please enter a valid phone number, or leave it blank.<br></span>
							Specify the number you wish SMS notifications be sent to.<br><b>Note:</b> Standard message and data rates may apply.
						</p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-2 control-label" for="user-whatsapp">
						WhatsApp:
					</label>
					<div class="col-sm-5">
						<input class="form-control" type="text" name="user-whatsapp" id="user-whatsapp">
						<p class="help-block">
							Specify the WhatsApp number you wish messages to be sent to.
						</p>
					</div>
				</div>
			</li>
			<li class="list-group-item">
				<div class="text-center mb-4 p-2 text-muted text-size-14-fix">
					<b>Notifications</b>
					<br>
					<small>
						When and how do you want to receive reminders?<br>
						Reminders can be sent anytime from 6 days to 1 minute prior to an event.<br>
						Opt out by selecting 'No reminder' from the drop-down menus.
					</small>
				</div>

				<div class="form-group">
					<label class="col-sm-2 control-label" for="reminder-platform-1">
						Platform:
					</label>
					<div class="col-sm-2">
						<select class="form-control select" name="reminder-time-1-platform" id="reminder-time-1-platform">
							<option value="email">Email</option>
							<option value="slack">Slack</option>
							<option value="sms">SMS</option>
							<option value="whatsapp">WhatsApp</option>
							<option value="disable">No reminder</option>
						</select>
						<p class="help-block">
							Where should we send your first reminder?
						</p>
					</div>
					<div class="col-sm-3">
						<input class="form-control number-input-form" type="number" min="1" name="reminder-time-1-amount" id="reminder-time-1-amount">
						<select class="form-control select mini-form" name="reminder-time-1-unit" id="reminder-time-1-unit">
							<option value="minutes">minutes</option>
							<option value="hours">hours</option>
							<option value="days">days</option>
						</select>
						<p class="help-block">
							<span id='invalid-time-1' class='error-msg'>Please enter a valid time amount.<br></span>
							When do you want to receive your first reminder? 
						</p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-2 control-label" for="reminder-time-2">
						Platform:
					</label>
					<div class="col-sm-2">
						<select class="form-control select" name="reminder-time-2-platform" id="reminder-time-2-platform">
							<option value="email">Email</option>
							<option value="slack">Slack</option>
							<option value="sms">SMS</option>
							<option value="whatsapp">WhatsApp</option>
							<option value="disable">No reminder</option>
						</select>
						<p class="help-block">
							Where should we send your second reminder?
						</p>
					</div>
					<div class="col-sm-3">
						<input class="form-control number-input-form" type="number" min="1" name="reminder-time-2-amount" id="reminder-time-2-amount">
						<select class="form-control select mini-form" name="reminder-time-2-unit" id="reminder-time-2-unit">
							<option value="minutes">minutes</option>
							<option value="hours">hours</option>
							<option value="days">days</option>
						</select>
						<p class="help-block">
							<span id='invalid-time-2' class='error-msg'>Please enter a valid time amount.<br></span>
							When do you want to receive your second reminder?
						</p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-2 control-label" for="reminder-platform-3">
						Platform:
					</label>
					<div class="col-sm-2">
						<select class="form-control select" name="reminder-time-3-platform" id="reminder-time-3-platform">
							<option value="email">Email</option>
							<option value="slack">Slack</option>
							<option value="sms">SMS</option>
							<option value="whatsapp">WhatsApp</option>
							<option value="disable">No reminder</option>
						</select>
						<p class="help-block">
							Where should we send your third reminder?
						</p>
					</div>
					<div class="col-sm-3">
						<input class="form-control number-input-form" type="number" min="1" name="reminder-time-3-amount" id="reminder-time-3-amount">
						<select class="form-control select mini-form" name="reminder-time-3-unit" id="reminder-time-3-unit">
							<option value="minutes">minutes</option>
							<option value="hours">hours</option>
							<option value="days">days</option>
						</select>
						<p class="help-block">
							<span id='invalid-time-3' class='error-msg'>Please enter a valid time amount.<br></span>
							When do you want to receive your third reminder?
						</p>
					</div>
				</div>
			</li>
		</ul>

		<div class="form-group">
			<div class="col-sm-10 col-sm-offset-2">
				<input id="write-settings" type="submit" value="Submit" class="btn btn-primary">
			</div>
		</div>
	</form>
	<script src="assets/js/jquery-3.4.1.min.js"></script>
	<script src="assets/js/toastify.js"></script>
	<script src="assets/js/settings.js"></script>
	</div>
</body>
</html>
