<?php
	include "./bot/longpoll.php";

	$cfgs = json_decode(file_get_contents('./bots.json', true), true)["bots"];
	$bots = Array();

	foreach ($cfgs as $cfg) {
		array_push($bots, new Bot($cfg));		
	}

	$LP = new LongPoll($bots);
	$LP->Listen();
?>