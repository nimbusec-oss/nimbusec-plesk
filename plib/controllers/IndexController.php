<?php

require_once(pm_Context::getPlibDir() . "/library/Helpers.php");
require_once(pm_Context::getPlibDir() . "/library/lib/Nimbusec.php");

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
		pm_Settings::set("agentLog", pm_Context::getVarDir() . "/agent-plesk-plugin.log");
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

		$this->view->info = pm_Locale::lmsg('apiInfo');
		
		//read config file
		$string = file_get_contents(pm_Settings::get("agentConfig"));
		$config = json_decode($string, true);
		$apikey = $config['key'];

		//if api key and secret are not present int the key-value-store read them from the config file
		if (!empty(pm_Settings::get('apikey'))) {
			$apikey = pm_Settings::get('apikey');
		}

		$apisecret = $config['secret'];
		if (!empty(pm_Settings::get('apisecret'))) {
			$apisecret = pm_Settings::get('apisecret');
		}

		$form = new pm_Form_Simple();
		$form->addElement('text', 'apikey', array(
			'label' => 'API Key',
			'value' => $apikey,
			'required' => true,
			'validators' => array(
				array('NotEmpty', true),
			),
		));
		$form->addElement('text', 'apisecret', array(
			'label' => 'API Secret',
			'value' => $apisecret,
			'required' => true,
			'validators' => array(
				array('NotEmpty', true),
			),
		));
		$form->addElement('text', 'apiserver', array(
			'label' => 'API Server',
			'value' => $config['apiserver'],
			'required' => true,
			'validators' => array(
				array('NotEmpty', true),
			),
		));

		$buttons = array(
			"cancelLink" => pm_Context::getModulesListUrl(),
			"sendTitle" => "Download Server Agent"
		);
		
		if (pm_Settings::get("setupTabs") == "0") {
			$buttons["sendHidden"] = true;
		}
		$form->addControlButtons($buttons);

		$err = false;
		$msg = pm_Locale::lmsg('savedMessage');
		if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
			//store settings from form into key-value-store
			pm_Settings::set('apikey', $form->getValue('apikey'));
			pm_Settings::set('apisecret', $form->getValue('apisecret'));
			pm_Settings::set('apiserver', rtrim($form->getValue('apiserver'),"/"));

			//if agent key and secret present in kv-store use them
			if (!empty(pm_Settings::get('agentkey')) && !empty(pm_Settings::get('agentkey'))) {
				$config['key'] = pm_Settings::get('agentkey');
				$config['secret'] = pm_Settings::get('agentsecret');
			} else {
				//if agent key and secret not present query from api
				$nimbusec = new Modules_NimbusecAgentIntegration_Lib_Nimbusec();

				try {
					//get agent binary from api
					if (!$nimbusec->fetchAgent(pm_Context::getVarDir())) {
						//error!
						$err = true;
						$msg = pm_Locale::lmsg('downloadError');
					} else {
						//download and extract worked
						$host = Modules_NimbusecAgentIntegration_Lib_Helpers::getHost();
						$admin = Modules_NimbusecAgentIntegration_Lib_Helpers::getAdministrator();

						// upset admin and define signaturekey
						$signatureKey = md5(uniqid(rand(), true));
						$nimbusec->upsertUserWithSSO((string) $admin->admin_email, $signatureKey);

						pm_Settings::set('signaturekey', $signatureKey);

						//get new token
						$token = $nimbusec->getAgentCredentials($host["0"].'-plesk');

						pm_Settings::set('agentkey', $token['key']);
						pm_Settings::set('agentsecret', $token['secret']);
						pm_Settings::set('agenttoken-id', $token['id']);

						// {"0":"localhost.localdomain"}

						$config['key'] = pm_Settings::get('agentkey');
						$config['secret'] = pm_Settings::get('agentsecret');

						pm_Settings::set("setupTabs", "0");
					}
				} catch (NimbusecException $e) {
					$err = true;
					$msg = $e->getMessage();
				} catch (CUrlException $e) {
					$err = true;
					if (strpos($e->getMessage(), '401') || strpos($e->getMessage(), '403')) {
						$msg = pm_Locale::lmsg('invalidAPICredentials');
					} else if (strpos($e->getMessage(), '404')) {
						$msg = pm_Locale::lmsg('invalidAgentVersion');
					} else {
						$msg = $e->getMessage();
					}
				}
			}

			if (!$err) {
				$config['apiserver'] = rtrim($form->getValue('apiserver'),"/");
				$config["domains"] = new ArrayObject();
				file_put_contents(pm_Settings::get("agentConfig"), json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));				
				$this->_status->addMessage('info', $msg);
			} else {
				$this->_status->addMessage('error', $msg);
			}
			$this->_helper->json(array('redirect' => pm_Context::getActionUrl("index", "login")));
		}

		$this->view->form = $form;
	}

	// ===========================================================================================================================================
	// ===========================================================================================================================================
	// ===========================================================================================================================================

	public function issuesAction() {
		$this->view->tabs = $this->newTabs();
		$this->view->responses = array();

		// =====================================================================================

		$domains = Modules_NimbusecAgentIntegration_Lib_Helpers::getHostDomains();
		$names = array_keys($domains);
		
		$nimbusec = new Modules_NimbusecAgentIntegration_Lib_Nimbusec();
		$this->view->issues = $nimbusec->getWebshellIssuesByDomain($names);
	}

	// ===========================================================================================================================================
	// ===========================================================================================================================================
	// ===========================================================================================================================================

	public function settingsAction() {
		$this->view->tabs = $this->newTabs();
		$this->view->responses = array();

		// =====================================================================================

		$nimbusec = new Modules_NimbusecAgentIntegration_Lib_Nimbusec();
		$this->domainsView($nimbusec);

		// =====================================================================================

		$this->view->info = pm_Locale::lmsg("agentExecution");

		$task = new pm_Scheduler_Task();
		$id = pm_Settings::get('agent-schedule-id');
		$cron_default = array(
			'minute' => '30',
			'hour' => '13',
			'dom' => '*',
			'month' => '*',
			'dow' => '*',
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
							array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage("Agent successfully activated", "info"));
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
								array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage("Agent successfully deactivated", "info"));
							}
						}
					} else {
						array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage("Invalid agent status chosen", "error"));
					}
				}

				// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

				if (strpos($action, "registerSelected") !== false) {
					if (!isset($_POST["active0"])) {
						array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage("No domains selected. Please select a domain in order to register it.", "error"));
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
						array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage("An unexpected error occurred. Please check the log.", "error"));
						return;
					}
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage("Successfully registered domains with <b>{$bundleName}</b>", "info"));
				}

				// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

				if (strpos($action, "removeSelected") !== false) {
					$index = substr($action, -1);
					if (!isset($_POST["active{$index}"])) {
						array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage("No domains selected. Please select a domain in order to unregister it.", "error"));
						return;
					}

					$domains = $_POST["active{$index}"];
					$success = true;
					foreach ($domains as $domain) {
						$success = $success && $nimbusec->unregisterDomain($domain);
					}

					if (!$success) {
						array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage("An unexpected error occurred. Please check the log.", "error"));
						return;
					}
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage("Successfully unregistered domains from <b>{$_POST['bundle']}</b>", "info"));
				}

				// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

			} catch (NimbusecException $e) {
				array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage($e->getMessage(), "error"));
			} catch (CUrlException $e) {
				if (strpos($e->getMessage(), '401') || strpos($e->getMessage(), '403')) {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage(pm_Locale::lmsg('invalidAPICredentials'), "error"));
				} else if (strpos($e->getMessage(), '404')) {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage(pm_Locale::lmsg('invalidAgentVersion'), "error"));
				} else if (strpos($e->getMessage(), '409')) {
					if (strpos($e->getMessage(), "X-Nimbusec-Error") !== false) {
						array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage("X-Nimbusec-Error" . split("X-Nimbusec-Error", $e->getMessage())[1], "error"));
					} else {
						array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage($e->getMessage(), "error"));	
					}
				} else {
					array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage($e->getMessage(), "error"));
				}
			}

			$this->domainsView($nimbusec);
		}

		// =====================================================================================
		
		$config = file_get_contents(pm_Settings::get("agentConfig"));
		
		$configForm = new pm_Form_Simple();
		$configForm->addElement('textarea', 'configuration', array(
			'label' => "Configuration",
			'value' => $config,
			"style" => "margin-right: 5px; height: 400px; width: 400px"
		));

		$this->view->configInfo = pm_Locale::lmsg("agentConfiguration");
		$this->view->configForm = $configForm;
	}

	public function domainsView($nimbusec) {
		try {
			$domains = Modules_NimbusecAgentIntegration_Lib_Helpers::getHostDomains();
			$keys = array_keys($domains);

			$string = file_get_contents(pm_Settings::get("agentConfig"));
			$config = json_decode($string, true);	
			$config["domains"] = new ArrayObject();

			$fetched = $nimbusec->getBundlesWithDomains();
			foreach ($fetched as $id => $element) {
				$element["domains"] = array_filter($element["domains"], function($domain) use ($keys) {
					return in_array($domain["name"], $keys);
				});

				foreach ($element["domains"] as $domain) {
					unset($domains[$domain["name"]]);

					$directory = Modules_NimbusecAgentIntegration_Lib_Helpers::getDomainDir($domain["name"]);
					$config["domains"][$domain["name"]] = (string) $directory;
				}
				$fetched[$id]["domains"] = $element["domains"];
			}

			$this->view->domains = $domains;
			$this->view->fetched = $fetched;

			file_put_contents(pm_Settings::get("agentConfig"), json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));	

		} catch (NimbusecException $e) {
			array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage($e->getMessage(), "error"));
		} catch (CUrlException $e) {
			if (strpos($e->getMessage(), '401') || strpos($e->getMessage(), '403')) {
				array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage(pm_Locale::lmsg('invalidAPICredentials'), "error"));
			}
			if (strpos($e->getMessage(), '404')) {
				array_push($this->view->responses, Modules_NimbusecAgentIntegration_Lib_Helpers::createMessage(pm_Locale::lmsg('invalidAgentVersion'), "error"));
			}
		}
	}

	// ===========================================================================================================================================
	// ===========================================================================================================================================
	// ===========================================================================================================================================

	public function loginAction() {
		$this->view->tabs = $this->newTabs();
		$this->view->login = pm_Locale::lmsg('login');

		if ($this->getRequest()->isPost()) {
			$admin = Modules_NimbusecAgentIntegration_Lib_Helpers::getAdministrator();
			$this->_helper->json(array(
				"link" => Modules_NimbusecAgentIntegration_Lib_Helpers::getSignedLoginURL(
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

			$nimbusec = new Modules_NimbusecAgentIntegration_Lib_Nimbusec();
			$version = $nimbusec->getNewestAgentVersion($agent["os"], $agent["arch"]);
			if(intval($version) > intval($agent["version"])) {
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
			$nimbusec = new Modules_NimbusecAgentIntegration_Lib_Nimbusec();
			
			try {
				if (!$nimbusec->fetchAgent(pm_Context::getVarDir())) {
					$err = true;
					$msg = pm_Locale::lmsg('downloadError');
				}
			} catch (NimbusecException $e) {
				$err = true;
				$msg = $e->getMessage();
			} catch (CUrlException $e) {
				$err = true;
				if (strpos($e->getMessage(), '401') || strpos($e->getMessage(), '403')) {
					$msg = pm_Locale::lmsg('invalidAPICredentials');
				} else if (strpos($e->getMessage(), '404')) {
					$msg = pm_Locale::lmsg('invalidAgentVersion');
				} else {
					$msg = $e->getMessage();
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
