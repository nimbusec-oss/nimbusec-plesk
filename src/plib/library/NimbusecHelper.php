<?php

use Nimbusec\API as API;

/**
 * Nimbusec Helper Class
 */
class Modules_NimbusecAgentIntegration_NimbusecHelper
{
    private $key = "";
    private $secret = "";
    private $server = "";

    public static function withCred($key, $secret, $server)
    {
        $instance = new self();
        $instance->setKey($key);
        $instance->setSecret($secret);
        $instance->setServer($server);
        return $instance;
    }

    public function __construct()
    {
        pm_Context::init("nimbusec-agent-integration");

        //read necessary properties from key-value-store and store them into class variables
        $this->key 		= pm_Settings::get("api_key");
        $this->secret 	= pm_Settings::get("api_secret");
        $this->server 	= pm_Settings::get("api_server");
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function setSecret($secret)
    {
        $this->secret = $secret;
    }

    public function setServer($server)
    {
        $this->server = $server;
    }

    public function areValidAPICredentials()
    {
        $api = new API($this->key, $this->secret, $this->server);

        try {
            $api->findBundles();
        } catch (Exception $e) {
            $message = $e->getMessage();
            $reason = "";

            if (strpos($message, "400") !== false || strpos($message, "401") !== false || strpos($message, "403") !== false) {
                $reason = "Wrong API credentials. Please make sure that the key and secret are right.";
            } elseif (strpos($message, "404") !== false) {
                $reason = "404 indicates a wrong server url. Please check {$server} to make sure it's right.";
            }

            pm_Log::err("Failed while trying to connect to API: {$message}. {$reason}");
            return false;
        }
        return true;
    }

    // Syncs the registered domains within plesk with the agent config
    public function syncDomainInAgentConfig() 
    {
        $registered = $this->getRegisteredPleskDomains();

        // update config
        $config = json_decode(file_get_contents(pm_Settings::get("agent_config")), true);
        $config["domains"] = new ArrayObject();

        foreach ($registered as $domain => $directory) {
            $config["domains"][$domain] = $directory;
        }

        file_put_contents(pm_Settings::get("agent_config"), json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    public function getRegisteredPleskDomains()
    {
        // domains in Nimbusec
        $api = new API($this->key, $this->secret, $this->server);
        $fetched = $api->findDomains();

        // array flip swtiches values to keys
        $nimbusec_domains = array_flip(array_map(function($domain) {
            return $domain["name"];
        }, $fetched));

        // domains in plesk
        $plesk_domains = Modules_NimbusecAgentIntegration_PleskHelper::getHostDomains();

        // intersect = registered
        return array_intersect_key($plesk_domains, $nimbusec_domains);
    }

    public function getNonRegisteredPleskDomains() 
    {
        // registered domains
        $registered_plesk_domains = $this->getRegisteredPleskDomains();

        // domains in plesk
        $plesk_domains = Modules_NimbusecAgentIntegration_PleskHelper::getHostDomains();

        return array_diff_key($plesk_domains, $registered_plesk_domains);
    }

    public function groupByBundle($domains)
    {
        $api = new API($this->key, $this->secret, $this->server);

        $bundles = array();
        foreach ($api->findBundles() as $bundle) {
            $bundles[$bundle["id"]]["bundle"] = $bundle;
            $bundles[$bundle["id"]]["bundle"]["display"] = sprintf("%s (used %d / %d)", $bundle["name"], $bundle["active"], $bundle["contingent"]);
            $bundles[$bundle["id"]]["domains"] = array();
        }

        foreach ($domains as $name => $directory) {
            $fetched = $api->findDomains("name=\"{$name}\"");
            if (count($fetched) != 1) {
                pm_Log::err("found more than one domain in the API for {$name}: " . count($fetched));
                return false;
            }

            // append
            $domain = $fetched[0];
            array_push($bundles[$domain["bundle"]]["domains"], array(
                "name" => $name,
                "directory" => $directory
            ));
        }

        return $bundles;
    }

    public function registerDomain($domain, $bundle)
    {
        $api = new API($this->key, $this->secret, $this->server);

        $scheme = "http";
        $domain = array(
			"scheme" 	=> $scheme,
			"name" 		=> $domain,
			"deepScan" 	=> "{$scheme}://{$domain}",
			"fastScans" => array(
				"{$scheme}://{$domain}"
			),
			"bundle" 	=> $bundle
		);

        $api->createDomain($domain);
        return true;
    }

    public function unregisterDomain($domain)
    {
        $api = new API($this->key, $this->secret, $this->server);
        $domains = $api->findDomains("name=\"$domain\"");

        if (count($domains) != 1) {
            pm_Log::err("found more than one domain in the API for {$domain}: " . count($domains));
            return false;
        }

        $api->deleteDomain($domains[0]["id"]);
        return true;
    }

    /**
     * create a new token for the server agent
     * @param string $name name for the new server agent token
     * @return array array/object with data of the created token
     */
    public function getAgentCredentials($name)
    {
        $api = new API($this->key, $this->secret, $this->server);
		
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

    public function appendDomainIds($domain_names)
    {
        $api = new API($this->key, $this->secret, $this->server);

        $query = "";
        foreach ($domain_names as $index => $domain) {
            $query .= "name=\"{$domain}\"";

            if (($index + 1) < count($domain_names)) {
                $query .= " or ";
            }
        }
        $fetched = $api->findDomains($query);

        if (count($fetched) != count($domain_names)) {
            $difference = array_diff($domain_names, array_map(function ($domain) {
                return $domain["name"];
            }, $fetched));
			
            pm_Log::err(sprintf(
			    "not all domain in Plesk were found by the API. exceptions are [%s]",
							join(", ", $difference)
			));
            return;
        }

        // sort both array to prevent wrong associations
        sort($domain_names);
        usort($fetched, function ($a, $b) {
            if ($a["name"] == $b["name"]) {
                return 0;
            }
            return ($a["name"] < $b["name"]) ? -1 : 1;
        });

        return array_combine($domain_names, array_map(function ($fetchedDomain) {
            return array("domainId" => $fetchedDomain["id"]);
        }, $fetched));
    }

    public function getWebshellIssuesByDomain($domain_names)
    {
        $api = new API($this->key, $this->secret, $this->server);
        $issues = $this->appendDomainIds($domain_names);

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

    public function filterByQuarantined($issues) 
    {
        // filter by quarantined files
        $quarantine = Modules_NimbusecAgentIntegration_PleskHelper::getQuarantine();

        foreach ($quarantine as $domain => $files) {
            // filter only quarantined domain which has been detected as issues
            if (!array_key_exists($domain, $issues)) {
                continue;
            }

            // save the indices of the issues
            $indices = array();
            foreach ($files as $key => $value) {
                $index = array_search($value["old"], array_column($issues[$domain]["results"], "resource"));
                if ($index === false) {
                    continue;
                }

                array_push($indices, $index);
            }

            foreach ($indices as $index) {
                unset($issues[$domain]["results"][$index]);
				
                // if the domain has no results, delete it
                if (count($issues[$domain]["results"]) == 0) {
                    unset($issues[$domain]);
                }
            }
        }

        return $issues;
    }

    public function getNewestAgentVersion($os, $arch, $format = "bin")
    {
        $api = new API($this->key, $this->secret, $this->server);

        $agents = $api->findServerAgents();
        $filtered = array_filter($agents, function ($agent) use ($os, $arch, $format) {
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
    public function upsertUserWithSSO($mail, $signatureKey)
    {
        $api = new API($this->key, $this->secret, $this->server);

        $users = $api->findUsers("login=\"{$mail}\"");
        if (count($users) > 0) {
            $user = $users[0];
            $user["signatureKey"] = $signatureKey;

            $api->updateUser($user["id"], $user);
            return;
        }

        $user = array(
			"login" 		=> $mail,
			"mail" 			=> $mail,
			"role" 			=> "admin",
			"signatureKey" 	=> $signatureKey
		);
        $api->createUser($user);
    }

    public function resolvePath($path)
    {
        $subpaths = array_filter(explode("/", $path));
        if (!in_array("quarantine", $subpaths)) {
            array_splice($subpaths, 0, 0, array("quarantine"));
        }

        return implode("/", $subpaths);
    }
	
    /**
     * download the agent from the API and unpack it into the given directory
     * @param string $path path into which the downloaded agent should be extracted
     * @return boolean indicates whether extracting the token worked
     */
    public function fetchAgent($path)
    {
        $api = new API($this->key, $this->secret, $this->server);

        $platform = pm_ProductInfo::getPlatform();
        $os = $platform == pm_ProductInfo::PLATFORM_UNIX ? "linux" : "windows";

        $arch = pm_ProductInfo::getOsArch();
        $arch = $arch == pm_ProductInfo::ARCH_32 ? "32bit" : "64bit";

        $format = "bin";
		
        // look up for agents
        $agents = $api->findServerAgents();
        $filtered = array_filter($agents, function ($agent) use ($os, $arch, $format) {
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

    public function fetchQuarantine($path)
    {
        $fragments = array_filter(explode("/", $path));
        $quarantine = Modules_NimbusecAgentIntegration_PleskHelper::getQuarantine();

        $fetched = array();
        if (count($fragments) == 0) {
            pm_Log::err("Invalid path given: {$path}");
            return array();
        }

        // root
        if (count($fragments) == 1 && $fragments[0] == "quarantine") {
            foreach ($quarantine as $domain => $files) {
                array_push($fetched, array(
					"type" 	=> 0,
					"name" 	=> $domain,
					"count" => count($files)
				));
            }

            return $fetched;
        }

        // domain
        if (count($fragments) == 2) {
            $domain = $fragments[1];

            // fetch root directory of domain
            $root = "/";
            try {
                $root = pm_Domain::getByName($domain)->getDocumentRoot();
            } catch (Exception $e) {
                pm_Log::err("Domain {$domain} not found: {$e->getMessage()}");
                return array();
            }

            foreach ($quarantine[$domain] as $id => $value) {
                array_push($fetched, array(
					"id"			=> $id,
					"type" 			=> 1,
					"name" 			=> pathinfo($value["path"], PATHINFO_BASENAME),

					// path with domain as root
					"old" 			=> pathinfo(explode($root, $value["old"])[1], PATHINFO_DIRNAME),
					"create_date" 	=> date("M d, Y h:i A", $value["create_date"]),
					"filesize" 		=> Modules_NimbusecAgentIntegration_PleskHelper::formatBytes($value["filesize"]),
					"owner" 		=> $value["owner"],
					"group" 		=> $value["group"],
					"permission" 	=> Modules_NimbusecAgentIntegration_PleskHelper::formatPermission($value["permission"])
				));
            }
        }

        // file
        if (count($fragments) == 3) {
            $domain = $fragments[1];
            $file = $fragments[2];

            $value = $quarantine[$domain][$file];
			
            array_push($fetched, array(
				"id" 	=> $file,
				"type" 	=> 2,
				"name" 	=> pathinfo($value["path"], PATHINFO_BASENAME),
				"path" 	=> $value["path"]
			));
        }

        return $fetched;
    }

    public function moveToQuarantine($domain, $file)
    {
        $fileManager = new pm_ServerFileManager();

        if (!$fileManager->fileExists($file)) {
            throw new Exception("File {$file} not existing. Cannot be moved into quarantine.");
        }

        // create quarantine directory
        $src = pm_Settings::get("quarantine_root");
        if (!$fileManager->fileExists($src)) {
            try {
                $fileManager->mkdir($src);
            } catch (Exception $e) {
                throw new Exception("Creating a quarantine directory failed: {$e->getMessage()}");
            }
        }

        // create domain dir if not already existing
        $domainDir = "{$src}/{$domain}";
        if (!$fileManager->fileExists($domainDir)) {
            try {
                $fileManager->mkdir($domainDir);
            } catch (Exception $e) {
                throw new Exception("Creating a quarantine directory failed: {$e->getMessage()}");
            }
        }

        // move the file to quarantine
        $dst = "{$src}/{$domain}/" . pathinfo($file, PATHINFO_BASENAME);
        try {
            $fileManager->moveFile($file, $dst);
        } catch (Exception $e) {
            throw new Exception("Couldn't move {$file} into quarantine {$dst}: {$e->getMessage()}");
        }

        // save in store
        $quarantine = Modules_NimbusecAgentIntegration_PleskHelper::getQuarantine();

        if (!array_key_exists($domain, $quarantine)) {
            $quarantine[$domain] = array();
        }

        $owner = fileowner($dst) != false ? posix_getpwuid(fileowner($dst))["name"] : "unknown";
        if ($owner == "") {
            $owner = fileowner($dst);
        }

        $group = filegroup($dst) != false ? posix_getgrgid(filegroup($dst))["name"] : "unknown";
        if ($group == "") {
            $group = filegroup($dst);
        }

        $filesize = filesize($dst) != false ? filesize($dst) : "unknown";

        $fileId = Modules_NimbusecAgentIntegration_PleskHelper::uuidv4();
        $quarantine[$domain][$fileId] = array(
			"old" 			=> $file,
			"path" 			=> $dst,
			"create_date" 	=> time(),
			"filesize" 		=> $filesize,
			"owner" 		=> $owner,
			"group" 		=> $group,
			"permission" 	=> decoct(fileperms($dst) & 0777)
		);

        try {
            Modules_NimbusecAgentIntegration_PleskHelper::setQuarantine($quarantine);
        } catch (Exception $e) {
            
            // revert file movement
            try {
                $fileManager->moveFile($dst, $file);
            } catch (Exception $e) {
                throw new Exception("Couldn't move {$dst} back from quarantine {$file}: {$e->getMessage()}");
            }

            // pass exception
            throw $e;
        }
    }

    public function markAsFalsePositive($domain, $resultId, $file)
    {
        $success = $this->sendToShellray($file);
        if (!$success) {
            return false;
        }

        return $this->updateResultStatus($domain, $resultId);
    }

    private function updateResultStatus($domain, $resultId)
    {
        $api = new API($this->key, $this->secret, $this->server);
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

	public function unquarantine($path) 
    {
		$fragments = array_filter(explode("/", $path));
		if (count($fragments) == 0) {
			pm_Log::err("Invalid path: {$path}");
			return;
		}

		$quarantine_root = pm_Settings::get("quarantine_root");
		$quarantine = Modules_NimbusecAgentIntegration_PleskHelper::getQuarantine();

		$fileManager = new pm_ServerFileManager();

		if (count($fragments) == 2) {
			$domain = $fragments[1];

			$files = $quarantine[$domain];
			foreach ($files as $file => $value) {
				try {
					$fileManager->moveFile($value["path"], $value["old"]);
				} catch (Exception $e) {
					pm_Log::err("Couldn't revert {$value['path']} from quarantine: {$e->getMessage()}");
					return false;
				}

				unset($quarantine[$domain][$file]);
			}

			// remove folder
			unset($quarantine[$domain]);
			$fileManager->removeDirectory("{$quarantine_root}/{$domain}");
		}

		// file
		if (count($fragments) == 3) {
			$domain = $fragments[1];
			$file = $fragments[2];

			$value = $quarantine[$domain][$file];
			
			try {
				$fileManager->moveFile($value["path"], $value["old"]);
			} catch (Exception $e) {
				pm_Log::err("Couldn't revert {$value['path']} from quarantine: {$e->getMessage()}");
				return false;
			}

			unset($quarantine[$domain][$file]);

			// clean up when no files left
			if (count($quarantine[$domain]) == 0) {
				$fileManager->removeDirectory("{$quarantine_root}/{$domain}");
				unset($quarantine[$domain]);
			}
		}

        // update quarantine store
        Modules_NimbusecAgentIntegration_PleskHelper::setQuarantine($quarantine);
        return true;
	}

	public function deleteQuarantined($path) 
    {
		$fragments = array_filter(explode("/", $path));
		if (count($fragments) == 0) {
			pm_Log::err("Invalid path: {$path}");
			return;
		}

		$quarantine_root = pm_Settings::get("quarantine_root");
		$quarantine = Modules_NimbusecAgentIntegration_PleskHelper::getQuarantine();

		$fileManager = new pm_ServerFileManager();

		if (count($fragments) == 2) {
			$domain = $fragments[1];

			$files = $quarantine[$domain];
			foreach ($files as $file => $value) {
				try {
					$fileManager->removeFile($value["path"]);
				} catch (Exception $e) {
					pm_Log::err("Couldn't delete {$value['path']} from quarantine: {$e->getMessage()}");
					return false;
				}

				unset($quarantine[$domain][$file]);
			}

			// remove folder
			unset($quarantine[$domain]);
			$fileManager->removeDirectory("{$quarantine_root}/{$domain}");
		}

		// file
		if (count($fragments) == 3) {
			$domain = $fragments[1];
			$file = $fragments[2];

			$value = $quarantine[$domain][$file];
			
			try {
				$fileManager->removeFile($value["path"]);
			} catch (Exception $e) {
				pm_Log::err("Couldn't delete {$value['path']}: {$e->getMessage()}");
				return false;
			}

			unset($quarantine[$domain][$file]);

			// clean up when no files left
			if (count($quarantine[$domain]) == 0) {
				$fileManager->removeDirectory("{$quarantine_root}/{$domain}");
				unset($quarantine[$domain]);	
			}
		}

        // update quarantine store
        Modules_NimbusecAgentIntegration_PleskHelper::setQuarantine($quarantine);
        return true;
	}

    private function sendToShellray($file)
    {
        $handler = curl_init(pm_Settings::get("shellray_url"));
		
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
