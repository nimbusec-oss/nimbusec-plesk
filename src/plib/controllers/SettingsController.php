<?php

class SettingsController extends pm_Controller_Action
{
	use Modules_NimbusecAgentIntegration_RequestTrait;
	use Modules_NimbusecAgentIntegration_LoggingTrait;

    public function init()
    {
		parent::init();
		$this->_accessLevel = "admin";

		$this->view->pageTitle = pm_Settings::get("extension_title");
		
		$this->view->e = new Zend\Escaper\Escaper();
		$this->view->h = $this->_helper;
    }

	// shortcut for calling the PleskHelper Module
	private function createHTMLR($msg, $level) 
	{
		return Modules_NimbusecAgentIntegration_PleskHelper::createMessage($msg, $level);
	}

	public function viewAction() 
	{
		$this->view->tabs = Modules_NimbusecAgentIntegration_PleskHelper::getTabs();

		// try to fetch passed parameters (e.g from forwards)
        $this->view->response = $this->getRequest()->getParam("response");

		try {
			$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();

			// domain view
			$this->view->registered_domains = $nimbusec->groupByBundle($nimbusec->getRegisteredPleskDomains());
			$this->view->nonregistered_domains = $nimbusec->getNonRegisteredPleskDomains();

        } catch (Exception $e) {
            $this->view->response = $this->createHTMLR("Could not retrieve registered domains", "error");
        }
	}

	public function registerAction() 
	{
		$request = $this->getRequest(); 
		$valid = $this->isValidPostRequest($request, "submit", "registerSelected");
		if (!$valid) {
			$this->_forward("view", "settings");
			return;
		}
		
		$domains = $request->getPost("active");
		$bundle = $request->getPost("bundle");

		// validate given domains
		$validator = new Zend\Validator\NotEmpty();
		if (!$validator->isValid($domains)) {
			$this->_forward("view", "settings", null, [
				"response" => $this->createHTMLR($this->lmsg("settings.controller.no_domains"), "error")
			]);
			return;	
		}
		
		// validate bundle
		$bundle_elements = explode("__", $bundle);
		if (count($bundle_elements) !== 2) {
			$this->_forward("view", "settings", null, [
				"response" => $this->createHTMLR($this->lmsg("settings.controller.invalid_bundle"), "error")
			]);
			return;	
		}

		$bundle_id = $bundle_elements[0];
		$bundle_name = $bundle_elements[1];

		// validate bundle uuid
		$validator = new Zend\Validator\Uuid();
		if (!$validator->isValid($bundle_id)) {
			pm_Log::err("Domain registration: invalid bundle id");

			$this->_forward("view", "settings", null, [
				"response" => $this->createHTMLR($this->lmsg("settings.controller.invalid_bundle"), "error")
			]);
			return;	
		}
		
		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();

		// register domain
		$success = true;
		foreach ($domains as $domain) {
			$success = $success && $nimbusec->registerDomain($domain, $bundle_id);
		}

		if (!$success) {
			$this->_forward("view", "settings", null, [
				"response" => $this->createHTMLR($this->lmsg("error.unexpected"), "error")
			]);
			return;	
		}

		try {
			// sync domains in config
			$nimbusec->syncDomainInAgentConfig();
		} catch (Exception $e) {
			$this->_forward("view", "settings", null, [
				"response" => $this->createHTMLR("Could not synchronize Server Agent config", "error")
			]);
			return;
		}

		$this->_status->addInfo(sprintf($this->lmsg("settings.controller.registered"), $bundle_name));
		$this->_helper->redirector("view", "settings");
		return;
	}

	public function unregisterAction() 
	{
		$request = $this->getRequest(); 
		$valid = $this->isValidPostRequest($request, "submit", "removeSelected", true);
		if (!$valid) {
			$this->_forward("view", "settings");
			return;
		}

		$index = substr($request->getPost("submit"), -1);
	
		$domains = $request->getPost("deactive{$index}");	
		$bundle_name = $request->getPost("bundle");

		// validate given domains
		$validator = new Zend\Validator\NotEmpty();
		if (!$validator->isValid($domains)) {
			$this->_forward("view", "settings", null, [
				"response" => $this->createHTMLR($this->lmsg("settings.controller.no_domains"), "error")
			]);
			return;	
		}

		// validate bundle
		$validator = new Zend\Validator\NotEmpty();
		if (!$validator->isValid($bundle_name)) {
			$this->_forward("view", "settings", null, [
				"response" => $this->createHTMLR($this->lmsg("settings.controller.invalid_bundle"), "error")
			]);
			return;	
		}

		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();

		// unregister domains
		$success = true;
		foreach ($domains as $domain) {
			$success = $success && $nimbusec->unregisterDomain($domain);
		}

		if (!$success) {
			$this->_forward("view", "settings", null, [
				"response" => $this->createHTMLR($this->lmsg("error.unexpected"), "error")
			]);
			return;	
		}

		try {
			// sync domains in config
			$nimbusec->syncDomainInAgentConfig();
		} catch (Exception $e) {
			$this->_forward("view", "settings", null, [
				"response" => $this->createHTMLR("Could not synchronize Server Agent config", "error")
			]);
			return;
		}

		$this->_status->addInfo(sprintf($this->lmsg("settings.controller.unregistered"), $bundle_name));
		$this->_helper->redirector("view", "settings");
		return;
	}

	public function scheduleAction() 
	{
		$request = $this->getRequest(); 
		$valid = $this->isValidPostRequest($request, "submit", "schedule");
		if (!$valid) {
			$this->_forward("view", "settings");
			return;
		}

		$interval = $request->getPost("interval");
		$status = $request->getPost("status");
		$yara = $request->getPost("yara");

		// validate interval
		if ($interval !== "0" && $interval !== "12" && $interval !== "8" && $interval !== "6") {
			$this->_forward("view", "settings", null, [
				"response" => $this->createHTMLR($this->lmsg("settings.controller.invalid_interval"), "error")
			]);
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
		$id = pm_Settings::get("agent_schedule_id");
		$validator = new Zend\I18n\Validator\Alnum();

		if ($validator->isValid($id)) {
			$task = $scheduler->getTaskById($id);

			if ($task !== null) {
				try {
					$scheduler->removeTask($task);
				} catch (pm_Exception $e) {
					$this->errE($e, "Could not remove scheduled task {$id}");
					$this->_forward("view", "settings", null, [
						"response" => $this->createHTMLR("Failed to activate Server Agent", "error")
					]);
					return;	
				}
			}
		}

		// stop agent
		if ($status === "false") {
			pm_Settings::set("agent_schedule_id", false);
			pm_Settings::set("agent_schedule_interval", "0");

			pm_Settings::set("agent_scheduled", $status);
			pm_Settings::set("agent_yara", $yara);

			$this->_status->addInfo($this->lmsg("settings.controller.schedule.updated"));
			$this->_helper->redirector("view", "settings");
			return;
		}

		// start agent
		$cron = [
			"minute" 	=> "30",
			"hour" 		=> "13",
			"dom" 		=> "*",
			"month" 	=> "*",
			"dow" 		=> "*",
		];

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

		pm_Settings::set("agent_schedule_id", $task->getId());
		pm_Settings::set("agent_schedule_interval", $interval);

		pm_Settings::set("agent_scheduled", $status);
		pm_Settings::set("agent_yara", $yara);

		$this->_status->addInfo($this->lmsg("settings.controller.schedule.updated"));
		$this->_helper->redirector("view", "settings");
		return;
	}
}