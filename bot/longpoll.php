<?php
	include "utils.php";
	include "commands.php";

	$BASE_URL = "https://api.vk.com/method/";

	class Event {
		var $text;
		var $peer_id;
		var $userid;
		var $updates;
		var $bot;
		var $etype;
		var $args;
		var $splited;

		function group($bot, $updates) {
			$this->etype = $updates["type"];
			$this->bot = $bot;

			$object = $updates["object"];

			if($this->etype == "message_new") {
				$this->etype = "MSG";
				$this->text = $object["text"];
				$this->peer_id = $object["peer_id"];
				$this->userid = $object["from_id"];
		
				$this->splited = explode(" ", mb_strtolower($this->text, "UTF-8"));
			}
		}

		function page($bot, $updates) {
			$this->etype = $updates[0];
			$this->bot = $bot;

			if($this->etype == 4) {
				$this->etype = "MSG";
				$this->text = $updates[5];
				$this->peer_id = $updates[3];

				if($this->peer_id > 2000000000) {
					$this->userid = $updates[6]["from"];
				} else {
					$this->userid = $updates[3];
				}

				$this->splited = explode(" ", mb_strtolower($this->text, "UTF-8"));
			}
		}
	}

	class Bot {
		public $token;
		public $names;
		public $version;
		public $is_group;
		public $id;
		public $bot_id;
		public $main;
		public $poll;

		function __construct($cfg) {
			if(!array_key_exists("names", $cfg)) {
				return;
			}

			try {
				$this->bot_id = $cfg["bot_id"];
				$this->token = $cfg["token"];
				$this->names = $cfg["names"];
				$this->version = $cfg["v"];
				$this->is_group = $cfg["is_group"] == "1";
			} catch(Exception $e) {
				$this->token = null;
			}
			
			if(array_key_exists("main", $cfg)) {
				$this->main = $cfg["main"];
			} else {
				$this->main = -1;
			}

			$this->poll = new LPData();
		}

		function method($method, $params) {
			global $BASE_URL;
			if($params == null) {
				$params = Array();
			}

			if(!array_key_exists("access_token", $params)) {
				$params["access_token"] = $this->token;
			}

			if(!array_key_exists("v", $params)) {
				$params["v"] = $this->version;
			}

			return request($BASE_URL . $method, $params);
		}

		function message_send($text, $peer_id, $params) {
			if($params == null) {
				$params = Array();
			}

			$params["message"] = $text;
			$params["peer_id"] = $peer_id;

			if(!array_key_exists("random_id", $params)) {
				$params["random_id"] = 0;
			}

			return $this->method("messages.send", $params);
		}


	 	function send_files($files, $message, $peer_id, $params) {
	 		$servers = Array();
	 		$attachment = "";

	 		if($params == null) {
	 			$params = Array();
	 		}

	 		foreach ($files as $file) {
	 			$type = $file["type"];

	 			if($type == "photo") {
	 				$method = "photos.getMessagesUploadServer";
	 				$field = "file1";
	 			} else {
	 				$method = "docs.getMessagesUploadServer";
	 				$field = "file";
	 			}

	 			if(!array_key_exists($type, $servers)) {
	 				$servers[$type] = $this->method($method, Array("peer_id"=>$peer_id));
	 			}

				if(array_key_exists("error", $servers[$type])) {
					echo $type.": ".$servers[$type]["error"]["error_msg"]."\n";
					continue;
				}
	 			
	 			$response = upload_file($servers[$type]["response"]["upload_url"], $file["path"], $field);

	 			print_r(""); // ¯\_(ツ)_/¯

	 			if(array_key_exists("error", $response)) {
					echo $type.": ".$response["error"]["error_msg"]."\n";
					continue;
				}

				if($type == "photo") {
					$method = "photos.saveMessagesPhoto";
					$UploadParams = Array("album_id"=>-3, "server"=>$response["server"], "photo"=>$response["photo"], "hash"=>$response["hash"]);
					$field = 0;
				} else {
					$UploadParams = Array("file"=>$response["file"]);
					$method = "docs.save";
					$field = "doc";
				}

				$img = $this->method($method, $UploadParams);

				if(array_key_exists("error", $img)) {
					echo $type.": ".$img["error"]["error_msg"]."\n";
					continue;
				}

				$attachment = $attachment.$type.$img["response"][$field]["owner_id"]."_".$img["response"][$field]["id"].",";
 			}	

	 		return $this->message_send($message, $peer_id, Array("attachment"=>$attachment));
	 	}
	}

	class LPData {
		var $server;
		var $key;
		var $ts; 

		function GetLongPoll($bot) {
			$server;

			if($bot->token == null) {
				$this->server = null;
				return;
			}

			if($bot->is_group) {
				$id = $bot->method("groups.getById", null);

				if(array_key_exists("error", $id)) {
					$this->server = null;
					return;
				} 

				$bot->id = $id["response"][0]["id"];
				$server = $bot->method("groups.getLongPollServer", Array("group_id"=>$bot->id))["response"];

				echo "LongPoll ".strval($bot->id). " получен!\n";

				$this->server = $server["server"];
				$this->key = $server["key"];
				$this->ts = $server["ts"];
			} else {
				$id = $bot->method("users.get", null);

				if(array_key_exists("error", $id)) {
					$this->server = null;
					return;
				} 

				$bot->id = $id["response"][0]["id"];
				$server = $bot->method("messages.getLongPollServer", null)["response"];

				echo "LongPoll ".strval($bot->id). " получен!\n";
				$this->server = "https://".$server["server"];
				$this->key = $server["key"];
				$this->ts = $server["ts"];
			}
		}

		function Updates($bot) {
			global $COMMANDS;

			while (true) {
				$updates = request($this->server, Array("key"=>$this->key, "ts"=>$this->ts, "wait"=>30, "version"=>"3", "mode"=>2, "act"=>"a_check"));

				if(!array_key_exists("ts", $updates)) {
					$this->GetLongPoll($bot);
				}

				$this->ts = $updates["ts"];

				if(!array_key_exists("updates", $updates)) {
					continue;
				}

				foreach ($updates["updates"] as $update) {
					$event = new Event();

					if($bot->is_group) {
						$event->group($bot, $update);
					} else {
						$event->page($bot, $update);
					}

					RunHandlers($event, $update);

					if($event->etype == "MSG") {
						if(count($event->splited) > 1) { 
							if($bot->main == $bot->bot_id or $bot->bot_id == -1) {
								if(in_array($event->splited[0], $event->bot->names)) {
									$event->args = implode("", array_slice(str_split($event->text), count(str_split($event->splited[0]))+count(str_split($event->splited[1]))+2));
									
									if(array_key_exists($event->splited[1], $COMMANDS)) {
										$cmd = $COMMANDS[$event->splited[1]];

										$cmd["func"]($event);
									} else {
										$event->bot->message_send("Команда не найдена!", $event->peer_id, null);
									}
								}
							}
						}						
					}
				}
			}
		}
	}

	class LongPoll {
		var $bots;

		function __construct($bots) {
			$this->bots = $bots;
		}

		function Listen() {
			foreach ($this->bots as $bot) {
				$bot->poll->GetLongPoll($bot);

				if($bot->poll->server) {
					if(!extension_loaded("pthreads")) {
						echo "pthreads не установлен. Будет запущен только 1 бот!\n";
						$bot->poll->Updates($bot);
					} else {
						$thread = new Thread($bot->poll->Updates, $bot);
						$thread.start();
					}
				} else {
					echo "Бот с id ".strval($bot->bot_id)." не запустился!\n";
				}
			}
		}
	}
?>