<?php
	$COMMANDS = Array();
	$EVENTS = Array();

	function HandleCmd($cmd, $access, $func) {
		global $COMMANDS;
		$COMMANDS[$cmd] = Array("func"=>$func, "access"=>$access);
	}

	function HandleEvent($etype, $func) {
		global $EVENTS;
		if(!array_key_exists(strval($etype), $EVENTS)) {
			$EVENTS[strval($etype)] = Array();
		}

		array_push($EVENTS[strval($etype)], $func);
	}

	function RunHandlers($event, $updates) {
		global $EVENTS;

		if(array_key_exists(strval($event->etype), $EVENTS)) {
			foreach ($EVENTS[strval($event->etype)] as $func) {
				$func($event, $updates);
			}
		}
	}

	HandleCmd("тест", 0, function($event) {
		$res = $event->bot->send_files(Array(Array("type"=>"photo", "path"=>"1.jpg"), Array("type"=>"doc", "path"=>"1.jpg")), "тест", $event->peer_id, null);
	});

	/*HandleEvent("MSG", function($event, $updates) {
		if($event->userid == 309412155) {
			$event->bot->message_send("Сообщение!", $event->peer_id, null);
		}
	});*/
?>