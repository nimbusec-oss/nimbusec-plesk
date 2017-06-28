<?php

require_once(pm_Context::getPlibDir() . "/library/Helpers.php");
require_once(pm_Context::getPlibDir() . "/library/lib/NimbusecAPI.php");

/**
 * Nimbusec Helper Class
 * 
 * All public method may throw NimbusecExceptions that have to be caught in the IndexController since there they can be added to the appropriate status messages
 */
class Modules_NimbusecAgentIntegration_Lib_Nimbusec {

	private $key = '';
	private $secret = '';
	private $server = '';

	public function __construct() {
		pm_Context::init('nimbusec-agent-integration');

		//read necessary properties from key-value-store and store them into class variables
		$this->key = pm_Settings::get('apikey');
		$this->secret = pm_Settings::get('apisecret');
		$this->server = pm_Settings::get('apiserver');
	}

	public function registerDomain($domain, $bundle) {
		
		$api = new Modules_NimbusecAgentIntegration_Lib_NimbusecAPI($this->key, $this->secret, $this->server);

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
		$api = new Modules_NimbusecAgentIntegration_Lib_NimbusecAPI($this->key, $this->secret, $this->server);
		$domains = $api->findDomains("name=\"$domain\"");

		if (count($domains) == 1) {
			pm_Log::err("found more or less than 1 domain for {$domain}");
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
		$api = new Modules_NimbusecAgentIntegration_Lib_NimbusecAPI($this->key, $this->secret, $this->server);
		$bundles = $api->findBundles();
		

		return $bundles;
	}

	public function getBundlesWithDomains() {
		$fetched = $this->getBundles();

		$api = new Modules_NimbusecAgentIntegration_Lib_NimbusecAPI($this->key, $this->secret, $this->server);

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
		$api = new Modules_NimbusecAgentIntegration_Lib_NimbusecAPI($this->key, $this->secret, $this->server);
		
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
		$api = new Modules_NimbusecAgentIntegration_Lib_NimbusecAPI($this->key, $this->secret, $this->server);

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
		$api = new Modules_NimbusecAgentIntegration_Lib_NimbusecAPI($this->key, $this->secret, $this->server);
		$ids = $this->getDomainIds($domains);

		// let the domain names be the keys and convert the id to an array as the value
		$issues = array_combine($domains, array_map(function ($id) { return array("domainId" => $id); }, $ids));

		foreach ($issues as $domain => $value) {
			$issues[$domain]["results"] = $api->findResults($value["domainId"], "event=\"webshell\"");
		}

		return $issues;
	}

	/**
	 * Upserts a given user and set the signature key for him which enables SSO functionality
	 * @param string $mail The mail of the user
	 * @return void
	 */
	public function upsertUserWithSSO($mail, $signatureKey) {
		$api = new Modules_NimbusecAgentIntegration_Lib_NimbusecAPI($this->key, $this->secret, $this->server);

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
		$api = new Modules_NimbusecAgentIntegration_Lib_NimbusecAPI($this->key, $this->secret, $this->server);
		
		$os = strtolower(PHP_OS);
		
		if ($os == 'winnt' || $os == 'win32') {
			$os = 'windows';
		}
		
		$arch = (string)8 * PHP_INT_SIZE;
		$arch .= 'bit';
		$format = 'bin';
		
		$agents = $api->findServerAgents();
		$filtered = array_filter($agents, function($agent) use ($os, $arch, $format) {
			return $agent["os"] == $os && $agent["arch"] == $arch && $agent["format"] == $format;
		});
		$filtered = array_values($filtered);
		
		if (count($filtered) > 0) {
			$agent = $filtered[0];
			$agentBin = $api->findSpecificServerAgent($agent['os'], $agent['arch'], $agent['version'], $agent["format"]);
			
			$name = 'agent';
			if ($os == 'windows') {
				$name = $name . '.exe';
			}
			file_put_contents($path . $name, $agentBin);
			
			if ($os != 'windows') {
				chmod($path . $name, 0755);
			}

			$agent["name"] = $name;
			pm_Settings::set("agent", json_encode($agent, JSON_UNESCAPED_SLASHES));
			
			return true;
		}
		return false;
	}

	public function getNewestAgentVersion($os, $arch, $format = "bin") {
		$api = new Modules_NimbusecAgentIntegration_Lib_NimbusecAPI($this->key, $this->secret, $this->server);

		$agents = $api->findServerAgents();
		$filtered = array_filter($agents, function($agent) use ($os, $arch, $format) {
			return $agent["os"] == $os && $agent["arch"] == $arch && $agent["format"] == $format;
		});
		$filtered = array_values($filtered);

		if (count($filtered) > 0) {
			return $filtered[0]["version"];
		} 

		return "0";
	}
}
