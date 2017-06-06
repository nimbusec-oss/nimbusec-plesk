<?php

require_once(pm_Context::getPlibDir() . "/helpers/Helpers.php");
require_once(pm_Context::getPlibDir() . "/library/lib/Nimbusec.php");

class IndexController extends pm_Controller_Action {

	public function init() {
		parent::init();

		// Init title for all actions
		$this->view->pageTitle = 'nimbusec Agent';
		pm_Settings::set("agentConfig", pm_Context::getVarDir() . "/agent.conf");
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
				'title' => 'Domains',
				'action' => 'domains',
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

		$form->addControlButtons(array(
			"sendTitle" => "Download Server Agent",
			'cancelLink' => pm_Context::getModulesListUrl(),
		));

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
						$host = Helpers::getHost();
						$admin = Helpers::getAdministrator();

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
				file_put_contents(pm_Settings::get("agentConfig"), json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));				
				$this->_status->addMessage('info', $msg);
			} else {
				$this->_status->addMessage('error', $msg);
			}
			$this->_helper->json(array('redirect' => pm_Context::getActionUrl("index", "login")));
		}

		$this->view->form = $form;
	}

	//get all domains on the host from plesk api
	private function getDomainData() {
		
		$domains = Helpers::getHostDomains();
		$string = file_get_contents(pm_Settings::get("agentConfig"));
		$config = json_decode($string, true);

		/*if (isset($_POST['submitted'])) {

			//todo: check domain
			//if returns true add to config
			$domainsAdd = array();
			$domains = Helpers::buildDomainArray($_POST['active']);
			$nimbusec = new Modules_NimbusecAgentIntegration_Lib_Nimbusec();
			$err = false;
			foreach ($domains as $do => $di) {
				try {
					if ($nimbusec->checkDomain($do, $_POST['bundle'])) {
						$domainsAdd[$do] = $di;
					}
				} catch (NimbusecException $e) {
					if (!strpos($e->getMessage(), '409')) {
						$err = true;
						$this->_status->addError($e->getMessage());
					}
				} catch (CUrlException $e) {
					
					if (strpos($e->getMessage(), '401') || strpos($e->getMessage(), '403')) {
						$err = true;
						$this->_status->addError(pm_Locale::lmsg('invalidAPICredentials'));
						
					}
					if (strpos($e->getMessage(), '404')) {
						$err = true;
						$this->_status->addError(pm_Locale::lmsg('invalidAgentVersion'));
					}
					if (strpos($e->getMessage(), '409')) {
						$this->_status->addError('Domain '.$do.' is known on your account but seems to be disabled. Please add it to a bundle in order to allow enabling it for the agent');
					}
				} catch (Exception $e) {
					$this->_status->addError($e->getMessage());
				}
				
			}
			
			if (!$err) {
				$config['domains'] = $domainsAdd;

				file_put_contents(pm_Settings::get("agentConfig"), json_encode($config, JSON_UNESCAPED_SLASHES));
				$this->view->submitted = true;
				$this->view->infomsg = pm_Locale::lmsg('savedMessage');
			}
		}*/
		$listData = array();
		foreach ($domains as $domain => $directory) {
			$box = "<input type='checkbox' name='active[]' value='{$domain}'";
			if (in_array($domain, array_keys($config["domains"]))) {
				$box .= " checked ";
			}
			$box .= '/>';

			$listData[] = array(
				'column1' => $box,
				'column2' => $domain,
				'column3' => $directory,
			);
		}

		$list = new pm_View_List_Simple($this->view, $this->_request);
		$list->setData($listData);
		$list->setColumns(array(
			'column1' => array(
				'title' => '<input type="checkbox" name="act" onclick="updateState()" id="act"> Add/Remove Domain',
				'noEscape' => true,
				'sortable' => false,
			),
			'column2' => array(
				'title' => 'Domain',
				'noEscape' => true,
				'searchable' => true,
			),
			'column3' => array(
				'title' => pm_Locale::lmsg('directory'),
				'noEscape' => true,
			),
		));
		$list->setDataUrl(array('action' => 'list-data'));

		return $list;
	}

	public function listDataAction() {
		$list = $this->getDomainData();
		$this->_helper->json($list->fetchData());
	}

	public function domainsAction() {
		$this->view->tabs = $this->newTabs();
		$this->view->list = $this->getDomainData();
		
		$form = new pm_Form_Simple();
		$form->setAttrib("name", "domainForm");

		$nimbusec = new Modules_NimbusecAgentIntegration_Lib_Nimbusec();
		try {
			$bundles = $nimbusec->getBundles();

			$bundleForm = array();
			foreach($bundles as $bundle) {
				$bundleForm[$bundle["id"]] = sprintf("%s (used %d / %d)", $bundle["name"], $bundle["active"], $bundle["contingent"]);
			}
			$this->view->bundles = $bundleForm;

		} catch (NimbusecException $e) {
			$this->_status->addError($e->getMessage());
		} catch (CUrlException $e) {
			$err = true;
			if (strpos($e->getMessage(), '401') || strpos($e->getMessage(), '403')) {
				$this->_status->addError(pm_Locale::lmsg('invalidAPICredentials'));
			}
			if (strpos($e->getMessage(), '404')) {
				$this->_status->addError(pm_Locale::lmsg('invalidAgentVersion'));
			}
		}

		if ($this->getRequest()->isPost()) {
			$bundle = $_POST["bundle"];
			$domains = Helpers::buildDomainArray($_POST['active']);
			foreach($domains as $domain => $directory) {
				$nimbusec = new Modules_NimbusecAgentIntegration_Lib_Nimbusec();
				$err = false;
				foreach ($domains as $do => $di) {
						try {
							if ($nimbusec->checkDomain($do, $_POST['bundle'])) {
								$domainsAdd[$do] = $di;
							}
						} catch (NimbusecException $e) {
							if (!strpos($e->getMessage(), '409')) {
								$err = true;
								$this->_status->addError($e->getMessage());
							}
						} catch (CUrlException $e) {
							
							if (strpos($e->getMessage(), '401') || strpos($e->getMessage(), '403')) {
								$err = true;
								$this->_status->addError(pm_Locale::lmsg('invalidAPICredentials'));
								
							}
							if (strpos($e->getMessage(), '404')) {
								$err = true;
								$this->_status->addError(pm_Locale::lmsg('invalidAgentVersion'));
							}
							if (strpos($e->getMessage(), '409')) {
								$this->_status->addError('Domain '.$do.' is known on your account but seems to be disabled. Please add it to a bundle in order to allow enabling it for the agent');
							}
						} catch (Exception $e) {
							$this->_status->addError($e->getMessage());
						}
						
					}
					
					if (!$err) {
						$config['domains'] = $domainsAdd;

						file_put_contents(pm_Settings::get("agentConfig"), json_encode($config, JSON_UNESCAPED_SLASHES));
						$this->view->submitted = true;
						$this->view->infomsg = pm_Locale::lmsg('savedMessage');
					}
			}

			return;
		}
	}

	public function settingsAction() {
		$this->view->tabs = $this->newTabs();

		// =====================================================================================
		
		$config = file_get_contents(pm_Settings::get("agentConfig"));
		
		$configForm = new pm_Form_Simple();
		$configForm->addElement('textarea', 'configuration', array(
			'label' => "Configuration",
			'value' => $config,
			"style" => "margin-right: 5px",
			"attribs" => array("disabled" => "disabled"),
		));

		$this->view->configInfo = pm_Locale::lmsg("agentConfiguration");
		$this->view->configForm = $configForm;

		// =====================================================================================

		$this->view->info = pm_Locale::lmsg("agentExecution");
		$agent = json_decode(pm_Settings::get("agent"), true);

		$id = pm_Settings::get('agent-schedule-id');
		$cron_default = array(
			'minute' => '30',
			'hour' => '13',
			'dom' => '*',
			'month' => '*',
			'dow' => '*',
		);

		$form = new pm_Form_Simple();

		$status = 'inactive';
		if (!empty($id)) {
			$status = 'active';
		}
		$form->addElement('checkbox', 'status', array(
			'label' => 'Status (please check or uncheck to enable or disable the agent execution)',
			'value' => pm_Settings::get('agentStatus'),
			"style" => "margin-right: 5px",
		));
						
		$labelYara = "Activate Yara";
		$attrib = array("disabled" => "disabled");
		$value = pm_Settings::get("agentYara");
		if ($value == null) {
			$value = "0";
		}
		if ($agent["arch"] == "32bit") {
			$labelYara .= " (not supported with 32bit agent)";
		}
		if ($agent["arch"] == "64bit") {
			$attrib = array();
		}
		$form->addElement('checkbox', 'yara', array(
			'label' => $labelYara,
			'value' => $value,
			"style" => "margin-right: 5px",
			"attribs" => $attrib
		));

		$form->addElement('select', 'interval', array(
			'label' => 'Agent Scan Interval',
			'multiOptions' => array(
				'0' => pm_Locale::lmsg('once'),
				'12' => pm_Locale::lmsg('twice'),
				'8' => pm_Locale::lmsg('threeTimes'),
				'6' => pm_Locale::lmsg('fourTimes'),
			),
			'value' => pm_Settings::get('schedule-interval'),
			'required' => true,
		));
		$form->addControlButtons(array(
			"sendTitle" => "Save settings",
			'cancelLink' => pm_Context::getModulesListUrl(),
		));

		$task = new pm_Scheduler_Task();
		if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
			$cron = $cron_default;
			if ($form->getValue('interval') == '12') {
				$cron['hour'] = '1,13';
			} else if ($form->getValue('interval') == '8') {
				$cron['hour'] = '1,9,17';
			} else if ($form->getValue('interval') == '6') {
				$cron['hour'] = '1,7,13,19';
			}

			pm_Settings::set('agentStatus', $form->getValue('status'));
			pm_Settings::set("agentYara", $form->getValue("yara"));

			if ($form->getValue('status') == '1') {
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
					pm_Settings::set('schedule-interval', $form->getValue('interval'));
					$this->_status->addMessage('info', 'agent successfully activated');
				}
			}

			if ($form->getValue('status') == '0') {
				if (!empty($id)) {
					try {
						$task = pm_Scheduler::getInstance()->getTaskById($id);
						pm_Scheduler::getInstance()->removeTask($task);

						pm_Settings::set('agent-schedule-id', FALSE);
					} catch (pm_Exception $e) {
						
					} finally {

						pm_Settings::set('agent-schedule-id', FALSE);
						$this->_status->addMessage('info', 'agent successfully deactivated');
					}
				}
			}
			 $this->_helper->json(array('redirect' => pm_Context::getActionUrl('index', 'exec')));	
		}

		$this->view->form = $form;
	}

	public function loginAction() {
		$this->view->tabs = $this->newTabs();
		$this->view->login = pm_Locale::lmsg('login');

		if ($this->getRequest()->isPost()) {
			$admin = Helpers::getAdministrator();
			$this->_helper->json(array(
				"link" => Helpers::getSignedLoginURL(
					(string) $admin->admin_email, 
					pm_Settings::get('signaturekey')
			)));
		}
	}

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
				$this->_status->addMessage('warning', "Your current nimbusec Agent is outdated. Please download the newest update as soon as possible");
			} else {
				$this->_status->addMessage('info', "You have the newest version of the nimbusec Agent installed");
				$form->addControlButtons(array(
					"sendHidden" => true,
					'cancelLink' => pm_Context::getModulesListUrl(),
				));
			}
		} else {
			$this->_status->addMessage('warning', "Your current nimbusec Agent is outdated. Please download the newest update as soon as possible");
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
				//get agent binary from api
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
