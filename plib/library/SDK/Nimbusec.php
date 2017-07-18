<?php

/**
 * Nimbusec Helper Class
 * 
 * All public method may throw NimbusecExceptions that have to be caught in the IndexController since there they can be added to the appropriate status messages
 */
class Modules_NimbusecAgentIntegration_SDK_Nimbusec {

	private $key = '';
	private $secret = '';
	private $server = '';

	public static function withCred($key, $secret, $server) {
		$instance = new self();
		$instance->setKey($key);
		$instance->setSecret($secret);
		$instance->setServer($server);
		return $instance;
	}

	public function __construct() {
		pm_Context::init('nimbusec-agent-integration');

		//read necessary properties from key-value-store and store them into class variables
		$this->key = pm_Settings::get('apikey');
		$this->secret = pm_Settings::get('apisecret');
		$this->server = pm_Settings::get('apiserver');
	}

	public function setKey($key) {
		$this->key = $key;
	}

	public function setSecret($secret) {
		$this->secret = $secret;
	}

	public function setServer($server) {
		$this->server = $server;
	}

	public function testAPICredentials() {
		$api = new Modules_NimbusecAgentIntegration_SDK_NimbusecAPI($this->key, $this->secret, $this->server);

		try {
			$api->findBundles();
		} catch (NimbusecException $e) {
			pm_Log::err("A nimbusec error occured: " . $e->getMessage());
			return false;

		} catch (CUrlException $e) {
			pm_Log::err("Failed while trying to connect to API: " . $e->getMessage());
			if (empty($e->getMessage())) {
				pm_Log::err("Invalid server url entered.");
			}

			if (strpos($e->getMessage(), '400') || strpos($e->getMessage(), '401') || strpos($e->getMessage(), '403')) {
				pm_Log::err("Wrong API credentials. Please make sure that the key and secret are right.");
			} else if (strpos($e->getMessage(), '404')) {
				pm_Log::err("404 indicates a wrong server url. Please check {$server} to make sure it's right.");
			}
			return false;

		} catch (Exception $e) {
			pm_Log::err("Unexpected exception raised. " . $e->getMessage());
			return false;

		}
		return true;
	}

	public function registerDomain($domain, $bundle) {
		$api = new Modules_NimbusecAgentIntegration_SDK_NimbusecAPI($this->key, $this->secret, $this->server);

		$scheme = "http";
		$domain = array(
			"scheme" => $scheme,
			"name" => $domain,
			"deepScan" => $scheme . '://' . $domain,
			"fastScans" => array(
				$scheme . '://' . $domain
			),
			"bundle" => $bundle
		);

		$api->createDomain($domain);
		return true;
	}

	public function unregisterDomain($domain) {
		$api = new Modules_NimbusecAgentIntegration_SDK_NimbusecAPI($this->key, $this->secret, $this->server);
		$domains = $api->findDomains("name=\"$domain\"");

		if (count($domains) != 1) {
			pm_Log::err("found more than one domain in the API for {$domain}: " . count($domains));
			return false;
		}

		$api->deleteDomain($domains[0]["id"]);
		return true;
	}

	/**
	 * query all active bundles from the api
	 * @return array list of all active bundles
	 */
	public function getBundles() {
		$api = new Modules_NimbusecAgentIntegration_SDK_NimbusecAPI($this->key, $this->secret, $this->server);
		$bundles = $api->findBundles();
		
		return $bundles;
	}

	public function getBundlesWithDomains() {
		$fetched = $this->getBundles();

		$api = new Modules_NimbusecAgentIntegration_SDK_NimbusecAPI($this->key, $this->secret, $this->server);

		$bundles = array();
		foreach ($fetched as $bundle) {
			$bundles[$bundle["id"]]["bundle"] = $bundle;
			$bundles[$bundle["id"]]["bundle"]["display"] = sprintf("%s (used %d / %d)", $bundle["name"], $bundle["active"], $bundle["contingent"]);
			$bundles[$bundle["id"]]["domains"] = $api->findDomains("bundle=\"{$bundle['id']}\"");
		}

		return $bundles;
	}

	/**
	 * create a new token for the server agent
	 * @param string $name name for the new server agent token
	 * @return array array/object with data of the created token
	 */
	public function getAgentCredentials($name) {
		$api = new Modules_NimbusecAgentIntegration_SDK_NimbusecAPI($this->key, $this->secret, $this->server);
		
		$storedToken = $api->findAgentToken("name=\"$name\"");
		
		if (count($storedToken) > 0) {
			return $storedToken[0];
		} else {
			$token = array(
				'name' => (string) $name,
			);

			return $api->createAgentToken($token);
		}
	}

	public function getDomainIds($domains) {
		$api = new Modules_NimbusecAgentIntegration_SDK_NimbusecAPI($this->key, $this->secret, $this->server);

		$query = "";
		foreach ($domains as $index => $domain) {
			$query .= "name=\"{$domain}\"";

			if (($index + 1) < count($domains)) {
				$query .= " or ";
			}
		}
		$fetched = $api->findDomains($query);

		if (count($fetched) != count($domains)) {
			$difference = array_diff($fetched, $domains);
			
			pm_Log::err(sprintf("not all domain in Plesk were found by the API. exceptions are [%s]", 
							join(", ", $difference)));
			return;
		}

		return array_map(function($domain) { return $domain["id"]; }, $fetched);
	}

	public function getWebshellIssuesByDomain($domains) {
		$api = new Modules_NimbusecAgentIntegration_SDK_NimbusecAPI($this->key, $this->secret, $this->server);
		$ids = $this->getDomainIds($domains);

		// let the domain names be the keys and convert the id to an array as the value
		$issues = array_combine($domains, array_map(function ($id) { return array("domainId" => $id); }, $ids));

		foreach ($issues as $domain => $value) {
			$results = $api->findResults($value["domainId"], "event=\"webshell\" and status=\"1\"");
			
			// when having no results, don't add them to the issue list
			if (count($results) == 0) {
				unset($issues[$domain]);
				continue;
			}

			$issues[$domain]["results"] = $results;
		}

		return $issues;
	}

	public function getNewestAgentVersion($os, $arch, $format = "bin") {
		$api = new Modules_NimbusecAgentIntegration_SDK_NimbusecAPI($this->key, $this->secret, $this->server);

		$agents = $api->findServerAgents();
		$filtered = array_filter($agents, function($agent) use ($os, $arch, $format) {
			return $agent["os"] == $os && $agent["arch"] == $arch && $agent["format"] == $format;
		});
		$filtered = array_values($filtered);

		if (count($filtered) > 0) {
			return $filtered[0]["version"];
		} 

		return 0;
	}

	/**
	 * Upserts a given user and set the signature key for him which enables SSO functionality
	 * @param string $mail The mail of the user
	 * @return void
	 */
	public function upsertUserWithSSO($mail, $signatureKey) {
		$api = new Modules_NimbusecAgentIntegration_SDK_NimbusecAPI($this->key, $this->secret, $this->server);

		$users = $api->findUsers("login=\"{$mail}\"");
		if (count($users) > 0) {
			$user = $users[0];
			$user["signatureKey"] = $signatureKey;

			$api->updateUser($user["id"], $user);
			return;
		}

		$user = array(
			"login" => $mail,
			"mail" => $mail,
			"role" => "admin",
			"signatureKey" => $signatureKey
		);
		$api->createUser($user);
	}
	
	/**
	 * download the agent from the API and unpack it into the given directory
	 * @param string $path path into which the downloaded agent should be extracted
	 * @return boolean indicates whether extracting the token worked
	 */
	public function fetchAgent($path) {
		$api = new Modules_NimbusecAgentIntegration_SDK_NimbusecAPI($this->key, $this->secret, $this->server);

		$platform = pm_ProductInfo::getPlatform();
		$os = $platform == pm_ProductInfo::PLATFORM_UNIX ? "linux" : "windows";

		$arch = pm_ProductInfo::getOsArch();
		$arch = $arch == pm_ProductInfo::ARCH_32 ? "32bit" : "64bit";

		$format = "bin";
		
		// look up for agents
		$agents = $api->findServerAgents();
		$filtered = array_filter($agents, function($agent) use ($os, $arch, $format) {
			return $agent["os"] == $os && $agent["arch"] == $arch && $agent["format"] == $format;
		});
		$filtered = array_values($filtered);

		if (count($filtered) == 0) {
			pm_Log::err("No agent found for following requirements: {$os}, {$arch}, {$format}");
			throw new Exception("No server agents found");
		}
		
		$agent = $filtered[0];
		$agentBin = $api->findSpecificServerAgent($agent['os'], $agent['arch'], $agent['version'], $agent["format"]);
		
		// save binary
		$name = $platform == pm_ProductInfo::PLATFORM_UNIX ? "agent" : "agent.exe";
		file_put_contents($path . $name, $agentBin);
		
		// give permissions
		if ($platform == pm_ProductInfo::PLATFORM_UNIX) {
			chmod($path . $name, 0755);
		}

		$agent["name"] = $name;
		pm_Settings::set("agent", json_encode($agent, JSON_UNESCAPED_SLASHES));
	}

	public function moveToQuarantine($domain, $file) {
		$fileManager = new pm_ServerFileManager();

		if (!$fileManager->fileExists($file)) {
			pm_Log::err("File {$file} not existing. Cannot be moved into quarantine.");
			return false;
		}

		// create quarantine directory
		$src = pm_Context::getVarDir() . "/quarantine";
		if (!$fileManager->fileExists($src)) {

			try {
				$fileManager->mkdir($src);
			} catch (Exception $e) {
				pm_Log::err("Creating a quarantine directory failed: {$e->getMessage()}");
				return false;
			}
		}

		// create domain dir if not already existing
		$domainDir = "{$src}/{$domain}";
		if (!$fileManager->fileExists($domainDir)) {
			
			try {
				$fileManager->mkdir($domainDir);
			} catch (Exception $e) {
				pm_Log::err("Creating a quarantine directory failed: {$e->getMessage()}");
				return false;
			}
		}

		// move the file to quarantine
		$dst = "{$src}/{$domain}/" . pathinfo($file, PATHINFO_BASENAME);
		try {
			$fileManager->moveFile($file, $dst);
		} catch (Exception $e) {
			pm_Log::err("Couldn't move {$file} into quarantine {$dst}: {$e->getMessage()}");
			return false;
		}

		// save in store
		$quarantine = json_decode(pm_Settings::get("quarantine"), true);
		if (!array_key_exists($domain, $quarantine)) {
			$quarantine[$domain] = array();
		}

		$quarantine[$domain][$file] = array(
			"resource" => $file,
			"path" => $dst
		);
		pm_Settings::set("quarantine", json_encode($quarantine));

		return true;
	}

	public function markAsFalsePositive($domain, $resultId, $file) {
		return $this->updateResultStatus($domain, $resultId) && $this->sendToShellray($file);
	}

	private function updateResultStatus($domain, $resultId) {
		$api = new Modules_NimbusecAgentIntegration_SDK_NimbusecAPI($this->key, $this->secret, $this->server);
		$domains = $api->findDomains("name=\"$domain\"");

		if (count($domains) != 1) {
			pm_Log::err("found " . count($domains) . " domains for {$domain}");
			return false;
		}

		$api->updateResult($domains[0]["id"], $resultId, array(
			"status" => 3
		));

		return true;
	}

	private function sendToShellray($file) {
		$handler = curl_init(pm_Settings::get("shellray"));
		
		curl_setopt_array($handler, array(
			CURLOPT_CONNECTTIMEOUT 	=> 10,
			CURLOPT_FRESH_CONNECT 	=> true,
			CURLOPT_HEADER 			=> true,
			CURLOPT_RETURNTRANSFER 	=> true,
			CURLOPT_POST 			=> true,
				CURLOPT_POSTFIELDS 	=> array(
					"file" => new CURLFile($file)
				),
			CURLOPT_VERBOSE 		=> true
		));

		$header 		= array();
		$content 		= curl_exec($handler);
		$error 			= curl_errno($handler);
		$errorMsg 		= curl_error($handler);
		$http_code 		= curl_getinfo($handler, CURLINFO_HTTP_CODE);
		$content_length = curl_getinfo($handler, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
		curl_close($handler);

		$header["http_code"] = $http_code;
		$header["download_content_length"] = $content_length;
		$header["content"] = $content;
		$header["error"] = $error;
		$header["errorMsg"] = $errorMsg;

		if ($header["http_code"] != 200) {
			pm_Log::err("Response from shellray.com resulted in {$header['http_code']}. Full response: " . 
				json_encode($header, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
			return false;
		}

		pm_Log::info("Content: " . substr($header["content"], $header["download_content_length"]));
		return true;
	}
}
