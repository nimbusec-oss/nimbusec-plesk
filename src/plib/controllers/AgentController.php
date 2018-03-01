<?php

class AgentController extends pm_Controller_Action
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

		// query store
        $agent = json_decode(pm_Settings::get("agent"), true);

		$this->view->agent_version 	= $agent["version"];
		$this->view->agent_os 		= $agent["os"];
		$this->view->agent_arch 	= $agent["arch"];

		$this->view->agent_outdated = "false";

		// check updateability of agent		
		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();
		$version = $nimbusec->getNewestAgentVersion($agent["os"], $agent["arch"]);

		if ($version > $agent["version"]) {
			$this->view->agent_outdated = "true";
			$this->view->update_version = $version;
			$this->_status->addWarning($this->lmsg("agent.controller.outdated"));

		} else {
			$this->_status->addInfo($this->lmsg("agent.controller.not_outdated"));
		}

		// config view
		$this->view->configuration = file_get_contents(pm_Settings::get("agent_config"));
	}

	public function updateAgentAction() 
	{
		$request = $this->getRequest(); 
		$valid = $this->isValidPostRequest($request, "submit", "updateAgent");
		if (!$valid) {
			$this->_forward("view", "agent");
			return;
		}

		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();
		try {
			// fetch server agent
			$nimbusec->fetchAgent(pm_Settings::get("agent_dir"));
		} catch (Exception $e) {
			$this->errE($e, "Could not download Server Agent");

			$this->_forward("view", "agent", null, [
				"response" => $this->createHTMLR($this->lmsg("error.download_agent"), "error")
			]);
			return;
		}

		$this->_status->addInfo($this->lmsg("agent.controller.updated"));
		$this->_helper->redirector("view", "agent");
		return;
	}

	public function scheduleAction() 
	{
		$request = $this->getRequest(); 
		$valid = $this->isValidPostRequest($request, "submit", "schedule");
		if (!$valid) {
			$this->_forward("view", "agent");
			return;
		}

		$interval = $request->getPost("interval");
		$status = $request->getPost("status");
		$yara = $request->getPost("yara");

		// validate interval
		if ($interval !== "0" && $interval !== "12" && $interval !== "8" && $interval !== "6") {
			$this->_forward("view", "agent", null, [
				"response" => $this->createHTMLR($this->lmsg("agent.controller.invalid_interval"), "error")
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
					$this->_forward("view", "agent", null, [
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

			$this->_status->addInfo($this->lmsg("agent.controller.schedule.updated"));
			$this->_helper->redirector("view", "agent");
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
		$task->setCmd("agent.php");
		$task->setSchedule($cron);

		$scheduler->putTask($task);

		pm_Settings::set("agent_schedule_id", $task->getId());
		pm_Settings::set("agent_schedule_interval", $interval);

		pm_Settings::set("agent_scheduled", $status);
		pm_Settings::set("agent_yara", $yara);

		$this->_status->addInfo($this->lmsg("agent.controller.schedule.updated"));
		$this->_helper->redirector("view", "agent");
		return;
	}
}