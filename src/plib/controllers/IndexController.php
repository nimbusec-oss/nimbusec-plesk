<?php

class IndexController extends pm_Controller_Action {

	// Three "===" - lines as comment mean the sepearation of the action methods 
	// 		e.g (loginAction, settingsAction, ...)
	//
	// "=" as comment line means the semantic sepearation within an action 
	// 		e.g (settingsAction => sepeartion of domain related, POST related and agent config related code)
	//
	// "+" as comment line means the sepeartion of form-related code within the POST request processing 
	// 		e.g (if ($_POST == "registerDomains), if ($_POST == "unregisterDomains), ...)


	public function init() {
		parent::init();

		// Init title for all actions
		$this->view->pageTitle = 'Nimbusec Webshell Detection';
		pm_Settings::set("agentConfig", pm_Context::getVarDir() . "/agent.conf");
		pm_Settings::set("agentLog", pm_Context::getVarDir() . "/agent.log");

		pm_Settings::set("shellray", "https://shellray.com/upload");
		pm_Settings::set("portal_url", "https://portal.nimbusec.com/");

		pm_Settings::set("quarantine_root", pm_Context::getVarDir() . "/quarantine");
	}

	public function oldTabs() {
		return array(
			array(
				'title' => 'Setup',
				'action' => 'setup'
			),
		);
	}

	public function newTabs() {
		return array(
			array(
				'title' => 'Login to nimbusec',
				'action' => 'login'
			),
			array(
				"title" => "Issues",
				"action" => "issues"
			),
			array(
				"title" => "Quarantine",
				"action" => "quarantine"
			),
			array(
				'title' => 'Settings',
				'action' => 'settings',
			),
			array(
				'title' => 'Update Agent',
				'action' => 'updateagent'
			),
			array(
				'title' => 'Setup',
				'action' => 'setup',
			),
		);
	}

	public function indexAction() {
		$setupTabs = pm_Settings::get("setupTabs");
		if ($setupTabs == "0") {
			$this->_forward('login');
		} else {
			$this->_forward('setup');
		}
	}

	// ===========================================================================================================================================
	// ===========================================================================================================================================
	// ===========================================================================================================================================

	public function setupAction() {
		$setupTabs = pm_Settings::get("setupTabs");
		if ($setupTabs == "0") {
			$this->view->tabs = $this->newTabs();
		} else {
			$this->view->tabs = $this->oldTabs();
		}

		$this->view->responses = array();
		$this->view->info = pm_Locale::lmsg('apiInfo');

		if ($this->getRequest()->isPost()) {
			$action = $_POST["submit"];

			if (strpos($action, "downloadAgent") !== false) {

				if (!isset($_POST["apikey"]) || !isset($_POST["apisecret"]) || !isset($_POST["apiserver"])) {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("Please fill out all fields.", "error"));
					return;
				}

				$key = $_POST["apikey"];
				$secret = $_POST["apisecret"];
				$serverUrl = rtrim($_POST["apiserver"], "/");

				$nimbusec = Modules_NimbusecAgentIntegration_NimbusecHelper::withCred($key, $secret, $serverUrl);

				// test credentials
				$success = $nimbusec->testAPICredentials();
				if (!$success) {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("Invalid credentials. Please make sure you have the right credentials entered and try again. For more information, please check the log.", "error"));	
					return;
				}

				try {
					// fetch server agent
					$nimbusec->fetchAgent(pm_Context::getVarDir());
				} catch (Exception $e) {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("An error occurred while downloading the server agent. For more information, please check the log.", "error"));	
					pm_Log::err("Downloading server agent failed: {$e->getMessage()}");
					return;
				}

				// upsert admin and define signaturekey
				$host = Modules_NimbusecAgentIntegration_PleskHelper::getHost();
				$admin = Modules_NimbusecAgentIntegration_PleskHelper::getAdministrator();

				$signatureKey = md5(uniqid(rand(), true));

				try {
					$nimbusec->upsertUserWithSSO((string) $admin->admin_email, $signatureKey);
				} catch (Exception $e) {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("An error occurred while establishing a Single Sign On connection. For more information, please check the log.", "error"));	
					pm_Log::err("Upserting administrator failed: {$e->getMessage()}");
					return;
				}

				pm_Settings::set('signaturekey', $signatureKey);

				// retrieving agent token
				$token = array();
				try {
					$token = $nimbusec->getAgentCredentials($host["0"].'-plesk');
				} catch (Exception $e) {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("An error occurred while retrieving Agent credentials. For more information, please check the log.", "error"));	
					pm_Log::err("Upserting administrator failed: {$e->getMessage()}");
					return;
				}

				// store agent credentials
				pm_Settings::set('agentkey', $token['key']);
				pm_Settings::set('agentsecret', $token['secret']);
				pm_Settings::set('agenttoken-id', $token['id']);

				$configString = file_get_contents(pm_Settings::get("agentConfig"));
				$config = json_decode($configString, true);
				
				$config['key'] = pm_Settings::get('agentkey');
				$config['secret'] = pm_Settings::get('agentsecret');
				$config['apiserver'] = $serverUrl;

				// store api credentials
				pm_Settings::set('apikey', $key);
				pm_Settings::set('apisecret', $secret);
				pm_Settings::set('apiserver', $serverUrl);

				// enable other view
				pm_Settings::set("setupTabs", "0");

				// write agent config
				$config["domains"] = new ArrayObject();
				file_put_contents(pm_Settings::get("agentConfig"), json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));	

				// redirect to new view
				$this->_status->addMessage('info', "Server Agent successfully installed");
				$redirect = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . pm_Context::getActionUrl('index', 'login');

				header("Location: {$redirect}", true, 303);
				die();
			}
		}
	}

	// ===========================================================================================================================================
	// ===========================================================================================================================================
	// ===========================================================================================================================================

	public function issuesAction() {
		$this->view->tabs = $this->newTabs();
		$this->view->responses = array();

		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();

		// =====================================================================================

		$this->issuesView($nimbusec);

		// =====================================================================================

		if ($this->getRequest()->isPost()) {
			$action = $_POST["action"];
			if (!isset($action)) {
				array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("Invalid request", "error"));
				return;
			}

			// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

			if (strpos($action, "falsePositive") !== false) {
				if (!isset($_POST["domain"]) || !isset($_POST["resultId"]) || !isset($_POST["file"])) {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("Not enough POST params given", "error"));
					return;
				}

				$domain = $_POST["domain"];
				$resultId = $_POST["resultId"];
				$file = $_POST["file"];

				try {
					$success = $nimbusec->markAsFalsePositive($domain, $resultId, $file);
					if ($success) {
						array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("Successfully marked {$file} as False Positive.", "info"));
					} else {
						array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("An error occurred. Please check the log files.", "error"));
					}

				} catch (Exception $e) {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage($e->getMessage(), "error"));	
				}
			}

			// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

			if (strpos($action, "moveToQuarantine") !== false) {
				if (!isset($_POST["domain"]) || !isset($_POST["file"])) {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("Not enough POST params given", "error"));
					return;
				}

				$domain = $_POST["domain"];
				$file = $_POST["file"];

				$success = $nimbusec->moveToQuarantine($domain, $file);
				if ($success) {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("Successfully moved {$file} into Quarantine.", "info"));
				} else {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("An error occurred. Please check the log files.", "error"));
				}
			}

			// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

			$this->issuesView($nimbusec);
		}
	}

	public function issuesView($nimbusec) {
		$domains = Modules_NimbusecAgentIntegration_PleskHelper::getHostDomains();
		$domainNames = array_keys($domains);

		$issues = $nimbusec->getWebshellIssuesByDomain($domainNames);

		// filter by quarantined files
		$quarantine = json_decode(pm_Settings::get("quarantine"), true);
		if ($quarantine == null) {
			$quarantine = array();
		}

		foreach ($quarantine as $domain => $files) {
			// filter only quarantined domain which has been detected as issues
			if (!array_key_exists($domain, $issues)) {
				continue;
			}

			// save the indices of the issues
			$indices = array();
			foreach($files as $key => $value) {
				$index = array_search($value["old"], array_column($issues[$domain]["results"], "path"));
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

		$this->view->issues = $issues;
	}

	// ===========================================================================================================================================
	// ===========================================================================================================================================
	// ===========================================================================================================================================

	public function quarantineAction() {
		$this->view->tabs = $this->newTabs();
		$this->view->responses = array();

		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();

		// =====================================================================================

		if ($this->getRequest()->isPost()) {
			$action = $_POST["action"];

			if (!isset($action)) {
				$this->_helper->json(
					array(
						"error" => Modules_NimbusecAgentIntegration_PleskHelper::createJSONR("Invalid request")
					)
				);
				return;
			}

			// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

			if (strpos($action, "fetch") !== false) {

				if (!isset($_POST["path"])) {
					$this->_helper->json(
						array(
							"error" => Modules_NimbusecAgentIntegration_PleskHelper::createJSONR("Not enough POST params given")
						)
					);
					return;
				}

				$path = $nimbusec->resolvePath($_POST["path"]);

				$fetched = $nimbusec->fetchQuarantine($path);
				$html = Modules_NimbusecAgentIntegration_PleskHelper::createQNavigationBar($path) .
							Modules_NimbusecAgentIntegration_PleskHelper::createQTreeView($path, $fetched);

				$this->_helper->json(
					array(
						"files" => $fetched,
						"html" 	=> $html,
						"path" 	=> $path,
						"error" => false
					)
				);
				return;
			}

			// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

		}

		$this->view->root_path = "/";
	}

	// ===========================================================================================================================================
	// ===========================================================================================================================================
	// ===========================================================================================================================================

	public function settingsAction() {
		$this->view->tabs = $this->newTabs();
		$this->view->responses = array();

		// =====================================================================================

		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();
		$this->domainsView($nimbusec);
		$this->configView();

		// =====================================================================================

		$this->view->info = pm_Locale::lmsg("agentExecution");

		$task = new pm_Scheduler_Task();
		$id = pm_Settings::get('agent-schedule-id');
		$cron_default = array(
			'minute' 	=> '30',
			'hour' 		=> '13',
			'dom' 		=> '*',
			'month' 	=> '*',
			'dow' 		=> '*',
		);

		if ($this->getRequest()->isPost()) {
			$action = $_POST["submit"];

			try {

				// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

				if (strpos($action, "schedule") !== false) {
					$interval = $_POST["interval"];
					$status = $_POST["status"];
					$yara = $_POST["yara"]; 

					if (!isset($_POST["status"])) {
						$status = "0";
					}

					if (!isset($_POST["yara"])) {
						$yara = "0";
					}

					$cron = $cron_default;
					if ($interval == '12') {
						$cron['hour'] = '1,13';
					} else if ($interval == '8') {
						$cron['hour'] = '1,9,17';
					} else if ($interval == '6') {
						$cron['hour'] = '1,7,13,19';
					}

					pm_Settings::set('agentStatus', $status);
					if ($status == "0") {
						$yara = "0";	
					}

					pm_Settings::set("agentYara", $yara);

					if ($status == "1") {
						try {
							if (!empty($id)) {
								$task = pm_Scheduler::getInstance()->getTaskById($id);
								pm_Scheduler::getInstance()->removeTask($task);
							}
						} catch (pm_Exception $e) {
						} finally {
							$task = new pm_Scheduler_Task();
							$task->setCmd('run.php');
							$task->setSchedule($cron);

							pm_Scheduler::getInstance()->putTask($task);

							pm_Settings::set('agent-schedule-id', $task->getId());
							pm_Settings::set('schedule-interval', $interval);
							array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("Agent successfully activated", "info"));
						}
					} else if ($status == "0") {
						if (!empty($id)) {
							try {
								$task = pm_Scheduler::getInstance()->getTaskById($id);

								pm_Scheduler::getInstance()->removeTask($task);
								pm_Settings::set('agent-schedule-id', FALSE);
								pm_Settings::set('schedule-interval', "0");
							} catch (pm_Exception $e) {
							} finally {
								pm_Settings::set('agent-schedule-id', FALSE);
								pm_Settings::set('schedule-interval', "0");
								array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("Agent successfully deactivated", "info"));
							}
						}
					} else {
						array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("Invalid agent status chosen", "error"));
					}
				}

				// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

				if (strpos($action, "registerSelected") !== false) {
					if (!isset($_POST["active0"])) {
						array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("No domains selected. Please select a domain in order to register it.", "error"));
						return;
					}

					$domains = $_POST["active0"];
					
					$bundleString = split("__", $_POST["bundle"]);
					$bundleId = $bundleString[0];
					$bundleName = $bundleString[1];

					$success = true;
					foreach ($domains as $domain) {
						$success = $success && $nimbusec->registerDomain($domain, $bundleId);
					}

					if (!$success) {
						array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("An unexpected error occurred. Please check the log.", "error"));
						return;
					}
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("Successfully registered domains with <b>{$bundleName}</b>", "info"));
				}

				// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

				if (strpos($action, "removeSelected") !== false) {
					$index = substr($action, -1);
					if (!isset($_POST["active{$index}"])) {
						array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("No domains selected. Please select a domain in order to unregister it.", "error"));
						return;
					}

					$domains = $_POST["active{$index}"];
					$success = true;
					foreach ($domains as $domain) {
						$success = $success && $nimbusec->unregisterDomain($domain);
					}

					if (!$success) {
						array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("An unexpected error occurred. Please check the log.", "error"));
						return;
					}
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage("Successfully unregistered domains from <b>{$_POST['bundle']}</b>", "info"));
				}

				// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

			} catch (Exception $e) {
				if (strpos($e->getMessage(), '401') !== false || strpos($e->getMessage(), '403') !== false) {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage(pm_Locale::lmsg('invalidAPICredentials'), "error"));
				} else if (strpos($e->getMessage(), '404') !== false) {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage(pm_Locale::lmsg('invalidAgentVersion'), "error"));
				} else {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage($e->getMessage(), "error"));	
				}
			}

			$this->domainsView($nimbusec);
			$this->configView();
		}
	}

	public function domainsView($nimbusec) {
		try {

			// domains found in Plesk
			$domains = Modules_NimbusecAgentIntegration_PleskHelper::getHostDomains();
			$keys = array_keys($domains);

			$string = file_get_contents(pm_Settings::get("agentConfig"));
			$config = json_decode($string, true);	
			$config["domains"] = new ArrayObject();

			// domains grouped by bundle from API
			$fetched = $nimbusec->getBundlesWithDomains();
			foreach ($fetched as $id => $element) {

				// allow only Plesk domains which are already in the API to be seen 
				$element["domains"] = array_filter($element["domains"], function($domain) use ($keys) {
					return in_array($domain["name"], $keys);
				});

				foreach ($element["domains"] as $domain) {
					// remove already registered domains from the set of Plesk ones
					// $domains = unregistered from Plesk
					// $fetched = registered by API
					unset($domains[$domain["name"]]);

					$directory = Modules_NimbusecAgentIntegration_PleskHelper::getDomainDir($domain["name"]);
					// add registered to agent config
					$config["domains"][$domain["name"]] = (string) $directory;
				}

				$fetched[$id]["domains"] = $element["domains"];
			}

			$this->view->domains = $domains;
			$this->view->fetched = $fetched;

			file_put_contents(pm_Settings::get("agentConfig"), json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));	

		} catch (Exception $e) {
			array_push($this->view->responses, Modules_NimbusecAgentIntegration_PleskHelper::createMessage($e->getMessage(), "error"));	
		}
	}

	public function configView() {
		$config = file_get_contents(pm_Settings::get("agentConfig"));

		$this->view->configInfo = pm_Locale::lmsg("agentConfiguration");
		$this->view->configuration = $config;
	}

	// ===========================================================================================================================================
	// ===========================================================================================================================================
	// ===========================================================================================================================================

	public function loginAction() {
		$this->view->tabs = $this->newTabs();
		$this->view->login = pm_Locale::lmsg('login');

		if ($this->getRequest()->isPost()) {
			$admin = Modules_NimbusecAgentIntegration_PleskHelper::getAdministrator();
			$this->_helper->json(array(
				"link" => Modules_NimbusecAgentIntegration_PleskHelper::getSignedLoginURL(
					(string) $admin->admin_email, 
					pm_Settings::get('signaturekey')
			)));
		}
	}

	// ===========================================================================================================================================
	// ===========================================================================================================================================
	// ===========================================================================================================================================

	public function updateagentAction() {
		$this->view->tabs = $this->newTabs();
		$this->view->info = pm_Locale::lmsg("updateAgent");

		$agent = json_decode(pm_Settings::get("agent"), true);
		$this->view->installed = ($agent != null);
		
		$form = new pm_Form_Simple();
		if ($this->view->installed) {
			$this->view->agentStatus = pm_Locale::lmsg("agentInstalled");
			$this->view->agentVersion = $agent["version"];
			$this->view->agentOs = ucfirst($agent["os"]);
			$this->view->agentArch = $agent["arch"];

			$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();
			$version = $nimbusec->getNewestAgentVersion($agent["os"], $agent["arch"]);
			if($version > $agent["version"]) {
				$form->addControlButtons(array(
					'sendTitle' => "Update to version {$version}",
					'cancelLink' => pm_Context::getModulesListUrl(),
				));
				$this->_status->addMessage('warning', "Your current Nimbusec Agent is outdated. Please download the newest update as soon as possible");
			} else {
				$this->_status->addMessage('info', "You have the newest version of the Nimbusec Agent installed");
				$form->addControlButtons(array(
					"sendHidden" => true,
					'cancelLink' => pm_Context::getModulesListUrl(),
				));
			}
		} else {
			$this->_status->addMessage('warning', "Your current Nimbusec Agent is outdated. Please download the newest update as soon as possible");
			$this->view->agentStatus = pm_Locale::lmsg("agentNotInstalled");
			$form->addControlButtons(array(
				'sendTitle' => 'Update',
				'cancelLink' => pm_Context::getModulesListUrl(),
			));
		}

		if ($this->getRequest()->isPost()) {
			$err = false;
			$msg = pm_Locale::lmsg('agentUpdated');
		
			//if agent key and secret not present query from api
			$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();
			
			try {
				if (!$nimbusec->fetchAgent(pm_Context::getVarDir())) {
					$err = true;
					$msg = pm_Locale::lmsg('downloadError');
				}

			} catch (Exception $e) {
				$err = true;
				$message = $e->getMessage();

				if (strpos($message, '400') !== false || strpos($message, '401') !== false || strpos($message, '403') !== false) {
					$msg = pm_Locale::lmsg('invalidAPICredentials');
				} else if (strpos($message, '404') !== false) {
					$msg = pm_Locale::lmsg('invalidAgentVersion');
				}
			}

			if (!$err) {
				$this->_status->addMessage('info', $msg);
			} else {
				$this->_status->addMessage('error', $msg);
			}
			$this->_helper->json(array('redirect' => pm_Context::getActionUrl('index', 'updateagent')));
		}

		$this->view->form = $form;
	}
}
