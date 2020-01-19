<?php

require_once(__DIR__ . '/src/master.php');
require_once(__DIR__ . '/src/FtApiHandler.php');
require_once(__DIR__ . '/src/FakeFtApiHandler.php');

?>
<html>
<head>
	<title>Testing</title>
	<link href="assets/css/style.css" rel="stylesheet" type="text/css">
	<script src="assets/js/jquery-3.4.1.min.js"></script>
</head>
<body>
	<?php if (isset($_GET['action_taken']) && $_GET['action_taken'] == 'registered')
		echo "<p>Successfully registered!</p>";
	?>
	<h1>Fake 42 Intra Events</h1>
	<p>These events are for testing the 42 Event Reminders system. You can register for one of these events with the form below.</p>
	<div><?php 
		$auth = new HttpAuthenticationHandler();
		$auth->authorize();
		$token = $auth->get_current_token();
		$real_api = new FtApiHandler($token);
		$fake_api = new FakeFtApiHandler();
		$events = $fake_api->get_upcoming_events();
		$now = time();
		echo "<table class=\"fake-api-testing\">";
		foreach ($events as $event_id => $event) {
			echo "<tr><td>";
			echo "<h5 class=\"fake-api-testing\">".$event['name']."</h5>";
			echo "<ul class=\"fake-api-testing\"><li>Location: ".$event['location']."</li>";
			$tm = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $event['begin_at']);
			$tm->setTimeZone(new DateTimeZone('America/Tijuana'));
			echo "<li>Time: ".$tm->format('g:ia \o\n l jS F Y \(\P\S\T\)')."</li></ul>";
			echo "</td><td class=\"fake-api-testing-debug-column\">";
			$seconds = strtotime($event['begin_at']) - $now;
			echo "<div class=\"event_in text-size-10-fix\">Event ".$event['event_id']." takes place in <span class=\"event_in_seconds\">$seconds</span> seconds.";
			echo "<span class=\"event_in_formatted\"></span></div></td></tr>";
		}
	?></div>
	<table class="fake-api-testing"><tr><td>
	<h2>Register for one of the above events</h2>
	<form id="register-form" class="simple-form form-horizontal app-form fake-api-testing" role="form" novalidate="novalidate" id="myform1" enctype="multipart/form-data" action="scripts/register-user.php" accept-charset="UTF-8" method="get">
		<ul class="list-group">
			<li class="list-group-item">
				<div class="form-group">
					<div class="col-sm-12">
						<select class="form-control select" name="select_event" id="event_id">
							<?php
								$fake_api = new FakeFtApiHandler();
								$_SESSION['user_id'] = $real_api->access_me()['user_id'];
								$events = $fake_api->get_upcoming_events();
								foreach ($events as $event_id => $event) {
									echo "<option value=\"$event_id\">".$event['name']."</option>";
								}
							?>
						</select>
						<p class="help-block">
							Select which event you wish to register to.
						</p>
					</div>
				</div>
			</li>
		</ul>
		<div class="form-group">
				<div class="col-sm-10 col-sm-offset-2">
				<input id="submit_button" type="submit" value="Submit" class="btn btn-primary">
			</div>
		</div>
	</form>
	</td></tr></table>
		<script>
			var time_divs = $('div.event_in');
			setInterval(function() {
				time_divs.each(function() {
					seconds_span = $(this).find('.event_in_seconds');
					format_span = $(this).find('.event_in_formatted');
					seconds = (parseFloat(seconds_span.html()) - 1);
					minutes = seconds / 60.0;
					hours = seconds / 3600.0;
					days = seconds / 86400.0;
					seconds_span.html(seconds);
					format_span.html(`<br>minutes: ${minutes}<br>hours: ${hours}<br>days: ${days} days`);
				});
			}, 1000);
		</script>
	</script>
</body>
</html>
