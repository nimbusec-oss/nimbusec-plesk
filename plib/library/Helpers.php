<?php

class Modules_NimbusecAgentIntegration_Lib_Helpers {
    public static function getSignedLoginURL($userName, $userSecret) {

		// get time with milliseconds ~true timestamp (hack because PHP has no long)
		$time = time();

		// encode with BCrypt
		$signature = password_hash($userName . $time . $userSecret, PASSWORD_BCRYPT);

		// previous PHP bcrypt version had a security bug in their implementation. To distinguish
		// older signatures from (safe) new ones, they changed the prefix to $2y$. The nimbusec
		// dashboard does not work with the PHP prefix, so just set the 'standard' $2a$ ;)
		$signature = str_replace("$2y$", "$2a$", $signature);

		// build the final SSO String
		$ssoString = sprintf("%slogin/signed?user=%s&time=%d&sig=%s", "https://portal.nimbusec.com/", $userName, $time, $signature);
		return $ssoString;
	}

	public static function getAdministrator() {
		$request = <<<DATA
<server>
	<get>
		<admin/>
	</get>
</server>
DATA;

		$resp = pm_ApiRpc::getService()->call($request);
		return $resp->server->get->result->admin;
	}

    //get hostname from plesk api
    public static function getHost() {
		$request = <<<DATA
<server>
	<get>
		<gen_info/>
	</get>
</server>
DATA;

		$resp = pm_ApiRpc::getService()->call($request);
		return $resp->server->get->result->gen_info->server_name;
	}

	public static function getHostDomains() {
		$api = pm_ApiRpc::getService();
		$request = <<<DATA
<webspace>
	<get>
		<filter/>
		<dataset>
			<gen_info/>
			<hosting-basic/>
		</dataset>
	</get>
</webspace>
DATA;

		$resp = $api->call($request);

		$domains = array();
		foreach ($resp->webspace->get->result as $host) {
			$domain = rtrim((string) $host->data->gen_info->name, "/");
			foreach ($host->data->hosting->vrt_hst->property as $prop) {
				if ($prop->name == 'www_root') {
					$directory = (string) $prop->value;
					$domains[$domain] = $directory;
				}
			}
		}

		return $domains;
	}

	//get htdocs dir for given domain from plesk api
	public static function getDomainDir($domain) {
		$request = <<<DATA
<webspace>
	<get>
		<filter>
			<name>$domain</name>
		</filter>
		<dataset>
			<hosting/>
		</dataset>
	</get>
</webspace>	
DATA;

		$resp = pm_ApiRpc::getService()->call($request);

		foreach ($resp->webspace->get->result[0]->data->hosting->vrt_hst->property as $prop) {
			if ($prop->name == 'www_root') {
				return $prop->value;
			}
		}
		return false;
	}

	public static function createMessage($msg, $level) {
		$title = $level;
		if ($level == "info") {
			$title = "information";
		}

		$title = ucfirst($title);
		return "<div class='msg-box msg-{$level}'>
					<div class='msg-content'>
						<span class='title'>
							{$title}:
						</span>
						{$msg}
					</div>
				</div>";
	}

	public static function logger($level, $msg, $data = array()) {
		$message = sprintf("[%s][%s] %s", date("Y-m-d h:m:s"), strtoupper($level), $msg . PHP_EOL);
		file_put_contents(pm_Settings::get("agentLog"), $message, FILE_APPEND);
		
		if ($data != array() && $data != "") {
			file_put_contents(pm_Settings::get("agentLog"), json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), FILE_APPEND);
		}
	}
}

?>