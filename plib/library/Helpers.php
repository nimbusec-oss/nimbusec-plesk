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
		$domains = array();

		$fetched = pm_Domain::getAllDomains();
		$filtered = array_filter($fetched, function($domain) {
			return $domain->hasHosting();
		});

		foreach ($filtered as $domain) {
			$name = $domain->getDisplayName();
			$path = $domain->getDocumentRoot();

			$domains[$name] = $path;
		}

		return $domains;
	}

	// get htdocs dir for given domain from plesk api
	public static function getDomainDir($domain) {
		$fetched = pm_Domain::getByName($domain);

		if (!$fetched->hasHosting()) {
			return false;
		}

		return $fetched->getDocumentRoot();
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
}

?>