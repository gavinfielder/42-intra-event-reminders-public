<?php

require_once(__DIR__ . '/src/master.php');

if (!isset($_SESSION['saved_reminder_times_json'])
		|| !isset($_SESSION['saved_reminder_platforms_json'])
		|| !isset($_SESSION['saved_phone_number'])) {
	die(header("HTTP/1.0 400 Bad Request"));
}
$number = $_SESSION['saved_phone_number'];

?>

<html>
<head>
	<title>Confirm Opt-in</title>
	<link href="assets/css/style.css" rel="stylesheet" type="text/css">
</head>
<body>
	<h1>Confirm Opt-in to Receive SMS Messages</h1>
	<p>If you were directed to this page, you have either selected SMS for the first time or have changed your number. We sent a confirmation code to <?php echo "$number"; ?>. Check your messages and enter the confirmation code below to proceed.</p>
	<form id="optin-form" class="simple-form form-horizontal app-form" role="form" novalidate="novalidate" id="myform1" enctype="multipart/form-data" action="src/route_settings_update_request.php" accept-charset="UTF-8" method="post">
		<ul class="list-group">
			<li class="list-group-item">
				<div class="form-group">
					<label class="col-sm-2 control-label" for="user-email">
						Confirmation Code:
					</label>
					<div class="col-sm-5">
						<input class="form-control" type="text" name="confirmation-code" id="confirmation-code">
						<p class="help-block">
							Enter the confirmation code you received.
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
	<p>Note: Standard message and data rates may apply, as determined by your carrier. To permanently opt out of all further messages, you can text STOP to the number you receive reminders from. Alternatively, you can visit <?php echo "<a href=\"".FT_API_REDIRECT_URI."\">".FT_API_REDIRECT_URI."</a>"; ?> to change your notification settings.</p>
</body>
</html>
