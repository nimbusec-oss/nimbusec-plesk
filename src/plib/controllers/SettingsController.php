<?php

class SettingsController extends pm_Controller_Action
{
    public function init()
    {
        parent::init();

		$this->view->pageTitle = pm_Settings::get("extension_title");
		
		$this->view->e = new Zend\Escaper\Escaper();
		$this->view->h = $this->_helper;
    }

	// shortcut for calling the PleskHelper Module
	private function createHTMLR($msg, $level) {
		return Modules_NimbusecAgentIntegration_PleskHelper::createMessage($msg, $level);
	}

	public function viewAction() 
	{
		$this->view->tabs = Modules_NimbusecAgentIntegration_PleskHelper::getTabs();

		// try to fetch passed parameters (e.g from forwards)
        $this->view->response = $this->getRequest()->getParam("response");

		// domain view
		try {

			$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();

			// domains found in Plesk
            $domains = Modules_NimbusecAgentIntegration_PleskHelper::getHostDomains();
            $keys = array_keys($domains);

            $string = file_get_contents(pm_Settings::get("agent_config"));
            $config = json_decode($string, true);
            $config["domains"] = new ArrayObject();

            // domains grouped by bundle from API
            $fetched = $nimbusec->getBundlesWithDomains();
            foreach ($fetched as $id => $element) {

				// allow only Plesk domains which are already in the API to be seen
                $element["domains"] = array_filter($element["domains"], function ($domain) use ($keys) {
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

            file_put_contents(pm_Settings::get("agent_config"), json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } catch (Exception $e) {
            $this->view->response = $this->createHTMLR($e->getMessage(), "error");
        }

		// config view
		$this->view->configuration = file_get_contents(pm_Settings::get("agent_config"));
	}

	public function registerAction() 
	{
		$request = $this->getRequest(); 
		$valid = Modules_NimbusecAgentIntegration_PleskHelper::isValidPostRequest($request, "submit", "registerSelected");
		if (!$valid) {
			$this->_forward("view", "settings");
			return;
		}
		
		$domains = $request->getPost("active0");
		$bundle = $request->getPost("bundle");

		// validate given domains
		$validator = new Zend\Validator\NotEmpty();
		if (!$validator->isValid($domains)) {
			$this->_forward("view", "settings", null, array(
				"response" => $this->createHTMLR("Domain registration: No domains selected. Please select a domain in order to register it.", "error")
			));
			return;	
		}
		
		// validate bundle
		$bundle_elements = split("__", $bundle);
		if (count($bundle_elements) !== 2) {
			$this->_forward("view", "settings", null, array(
				"response" => $this->createHTMLR("Domain registration: Invalid bundle chosen.", "error")
			));
			return;	
		}

		$bundle_id = $bundle_elements[0];
		$bundle_name = $bundle_elements[1];

		// validate bundle uuid
		$validator = new Zend\Validator\Uuid();
		if (!$validator->isValid($bundle_id)) {
			pm_Log::err("Domain registration: invalid bundle id");

			$this->_forward("view", "settings", null, array(
				"response" => $this->createHTMLR("Domain registration: " . current($validator->getMessages()) . ".", "error")
			));
			return;	
		}
		
		try {
			$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();

			// register domain
			$success = true;
			foreach ($domains as $domain) {
				$success = $success && $nimbusec->registerDomain($domain, $bundle_id);
			}

			if (!$success) {
				$this->_forward("view", "settings", null, array(
					"response" => $this->createHTMLR("Domain registration: An unexpected error occurred. Please check the log.", "error")
				));
				return;	
			}

		} catch (Exception $e) {
			$this->_forward("view", "settings", null, array(
				"response" => $this->createHTMLR($e->getMessage(), "error")
			));
			return;	
		}

		$this->_status->addMessage("info", "Successfully registered domains with {$bundle_name}");
		$this->_helper->redirector("view", "settings");
		return;
	}

	public function unregisterAction() 
	{
		$request = $this->getRequest(); 
		$valid = Modules_NimbusecAgentIntegration_PleskHelper::isValidPostRequest($request, "submit", "removeSelected", true);
		if (!$valid) {
			$this->_forward("view", "settings");
			return;
		}

		$index = substr($request->getPost("submit"), -1);
	
		$domains = $request->getPost("active{$index}");	
		$bundle = $request->getPost("bundle");

		// validate given domains
		$validator = new Zend\Validator\NotEmpty();
		if (!$validator->isValid($domains)) {
			$this->_forward("view", "settings", null, array(
				"response" => $this->createHTMLR("Domain unregistration: No domains selected. Please select a domain in order to unregister it.", "error")
			));
			return;	
		}

		// validate bundle
		$validator = new Zend\Validator\NotEmpty();
		if (!$validator->isValid($bundle)) {
			$this->_forward("view", "settings", null, array(
				"response" => $this->createHTMLR("Domain unregistration: Invalid bundle chosen.", "error")
			));
			return;	
		}

		try {
			$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();

			// unregister domains
			$success = true;
			foreach ($domains as $domain) {
				$success = $success && $nimbusec->unregisterDomain($domain);
			}

			if (!$success) {
				$this->_forward("view", "settings", null, array(
					"response" => $this->createHTMLR("Domain unregistration: An unexpected error occurred. Please check the log.", "error")
				));
				return;	
			}

		} catch (Exception $e) {
			$this->_forward("view", "settings", null, array(
				"response" => $this->createHTMLR($e->getMessage(), "error")
			));
			return;	
		}

		$this->_status->addMessage("info", "Successfully unregistered domains from {$bundle}");
		$this->_helper->redirector("view", "settings");
		return;
	}

	public function scheduleAction() 
	{
		$request = $this->getRequest(); 
		$valid = Modules_NimbusecAgentIntegration_PleskHelper::isValidPostRequest($request, "submit", "schedule");
		if (!$valid) {
			$this->_forward("view", "settings");
			return;
		}

		$interval = $request->getPost("interval");
		$status = $request->getPost("status");
		$yara = $request->getPost("yara");

		// validate interval
		if ($interval !== "0" && $interval !== "12" && $interval !== "8" && $interval !== "6") {
			$this->_forward("view", "settings", null, array(
				"response" => $this->createHTMLR("Scheduling: invalid interval given", "error")
			));
			return;	
		}

		// validate status
		if ($status !== "true") {
			$status = "false"; 
		}

		// validate yara
		if ($yara !== "true") {
			$yara = "false"; 
		}

		// if no status is set, then unset yara rules
		if ($status === "false") {
			$yara = "false";
		}

		// get plesk scheduler
		$scheduler = pm_Scheduler::getInstance();

		// prevention: remove the task if existing
		$id = pm_Settings::get("agent-schedule-id");
		$validator = new Zend\I18n\Validator\Alnum();

		if ($validator->isValid($id)) {
			$task = $scheduler->getTaskById($id);

			if ($task !== null) {
				$scheduler->removeTask($task);
			}
		}

		// stop agent
		if ($status === "false") {
			pm_Settings::set("agent-schedule-id", false);
			pm_Settings::set("schedule-interval", "0");

			pm_Settings::set("agent_scheduled", $status);
			pm_Settings::set("agent_yara", $yara);

			$this->_status->addMessage("info", "Agent successfully deactivated");
			$this->_helper->redirector("view", "settings");
			return;
		}

		// start agent
		$cron = array(
			"minute" 	=> "30",
			"hour" 		=> "13",
			"dom" 		=> "*",
			"month" 	=> "*",
			"dow" 		=> "*",
		);

		switch ($interval) {
			case "0": 
				$cron["hour"] = "13"; break;
			case "12": 
				$cron["hour"] = "1,13"; break;
			case "8": 
				$cron["hour"] = "1,9,17"; break;
			case "6": 
				$cron["hour"] = "1,7,13,19"; break;
		}

		// schedule agent
		$task = new pm_Scheduler_Task();
		$task->setCmd("run.php");
		$task->setSchedule($cron);

		$scheduler->putTask($task);

		pm_Settings::set("agent-schedule-id", $task->getId());
		pm_Settings::set("schedule-interval", $interval);

		pm_Settings::set("agent_scheduled", $status);
		pm_Settings::set("agent_yara", $yara);

		$this->_status->addMessage("info", "Agent successfully activated");
		$this->_helper->redirector("view", "settings");
		return;
	}
}