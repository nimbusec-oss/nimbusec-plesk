<?php

class IssuesController extends pm_Controller_Action
{
    public function init()
    {
        parent::init();

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

		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();

		// get registered plesk domains
		$domains = $nimbusec->getRegisteredPleskDomains();
		$domain_names = array_keys($domains);

		// get issues
        $issues = $nimbusec->getWebshellIssuesByDomain($domain_names);
		$filtered = $nimbusec->filterByQuarantined($issues);

        $this->view->colors = array("#bbb", "#fdd835", "#f44336");
        $this->view->issues = $filtered;
		$this->view->quarantine_state = pm_Settings::get("quarantine_state", "1");
	}

	public function falsePositiveAction() 
	{
		$request = $this->getRequest(); 
		$valid = Modules_NimbusecAgentIntegration_PleskHelper::isValidPostRequest($request, "action", "falsePositive");
		if (!$valid) {
			$this->_forward("view", "issues");
			return;
		}

		$domain = $request->getPost("domain");
		$result_id = $request->getPost("resultId");
		$file = $request->getPost("file");

		// validate domain
		$validator = new Zend\Validator\Hostname();
		if (!$validator->isValid($domain)) {
			$this->_forward("view", "issues", null, array(
				"response" => $this->createHTMLR("False positive: " . current($validator->getMessages()) . ".", "error")
			));
			return;	
		}

		// validate result id
		$validator = new Zend\Validator\Digits();
		if (!$validator->isValid($result_id)) {
			$this->_forward("view", "issues", null, array(
				"response" => $this->createHTMLR("False positive: invalid result id.", "error")
			));
			return;	
		}

		// validate file
		$fileManager = new pm_ServerFileManager();
		if (!$fileManager->fileExists($file)) {
            $this->_forward("view", "issues", null, array(
				"response" => $this->createHTMLR("False positive: given file does not exist.", "error")
			));
			return;	
        }

		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();

		try {
			$success = $nimbusec->markAsFalsePositive($domain, $result_id, $file);
			if (!$success) {
				$this->_forward("view", "issues", null, array(
					"response" => $this->createHTMLR("False positive: An error occurred. Please check the log files.", "error")
				));
				return;	
			}

			$this->_status->addMessage("info", "Successfully marked {$file} as False Positive.");
			$this->_helper->redirector("view", "issues");
			return;

		} catch (Exception $e) {
			$this->_forward("view", "issues", null, array(
				"response" => $this->createHTMLR("False positive: {$e->getMessage()}", "error")
			));
			return;	
		}
	}

	public function quarantineAction() 
	{
		$request = $this->getRequest(); 
		$valid = Modules_NimbusecAgentIntegration_PleskHelper::isValidPostRequest($request, "action", "moveToQuarantine");
		if (!$valid) {
			$this->_forward("view", "issues");
			return;
		}

		$domain = $request->getPost("domain");
		$file = $request->getPost("file");

		// validate domain
		$validator = new Zend\Validator\Hostname();
		if (!$validator->isValid($domain)) {
			$this->_forward("view", "issues", null, array(
				"response" => $this->createHTMLR("Quarantine: " . current($validator->getMessages()) . ".", "error")
			));
			return;	
		}

		// validate file
		$fileManager = new pm_ServerFileManager();
		if (!$fileManager->fileExists($file)) {
            $this->_forward("view", "issues", null, array(
				"response" => $this->createHTMLR("Quarantine: given file does not exist.", "error")
			));
			return;
        }

		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();
		try {
			$nimbusec->moveToQuarantine($domain, $file);
		} catch (Exception $e) {
			$this->_forward("view", "issues", null, array(
				"response" => $this->createHTMLR("Quarantine: {$e->getMessage()}", "error")
			));
			return;
		}

		$this->_status->addMessage("info", "Successfully moved {$file} into Quarantine.");
		$this->_helper->redirector("view", "issues");
		return;
	}

	public function bulkQuarantineAction()
	{
		$request = $this->getRequest(); 
		$valid = Modules_NimbusecAgentIntegration_PleskHelper::isValidPostRequest($request, "action", "bulk-quarantine");
		if (!$valid) {
			$this->_helper->json(array(
				"error" => $this->createHTMLR("Bulk quarantine: Invalid request.", "error")
			));
			return;
		}

		$issues = $request->getPost("issues");

		// validate given issues
		$validator = new Zend\Validator\NotEmpty();
		if (!$validator->isValid($issues)) {
			$this->_helper->json(array(
				"error" => $this->createHTMLR("Bulk quarantine: No issues selected. Please select a issues in order to quarantine.", "error")
			));
			return;
		}

		$issues = json_decode($issues, true);
		if (count($issues) === 0) {
			$this->_helper->json(array(
				"error" => $this->createHTMLR("Bulk quarantine: No issues selected. Please select a issues in order to quarantine.", "error")
			));
			return;
		}

		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();

		foreach ($issues as $issue) {
			try {
				$nimbusec->moveToQuarantine($issue["domain"], $issue["file"]);
			} catch (Exception $e) {
				$this->_helper->json(array(
					"error" => $this->createHTMLR("Bulk quarantine: Something went wrong while quarantining {$issue['file']} for {$issue['domain']}: {$e->getMessage()}", "error")
				));
				return;
			}
		}

		$this->_helper->json(array(
			"html" => $this->createHTMLR("Bulk quarantine: Successfully moved the selected domains into quarantine.", "info")
		));
		return;
	}

	public function scheduleQuarantineAction() 
	{
		$request = $this->getRequest(); 
		$valid = Modules_NimbusecAgentIntegration_PleskHelper::isValidPostRequest($request, "action", "scheduleQuarantine");
		if (!$valid) {
			$this->_forward("view", "issues");
			return;
		}

		$states = $request->getPost("quarantine-state");

		// validate states
		$validator = new Zend\Validator\NotEmpty();
		if (!$validator->isValid($states)) {
			$this->_forward("view", "issues", null, array(
				"response" => $this->createHTMLR("Quarantine: Invalid schedule.", "error")
			));
			return;	
		}

		// calc state
		$state = array_reduce($states, function($acc, $curr) { return $acc + intval($curr); }, 0);

		// 1 == none
		// 3 == yellow
		// 6 == red
		// 9 == red & yellow
		if (!in_array($state, array(1, 3, 6, 9))) {
			$this->_forward("view", "issues", null, array(
				"response" => $this->createHTMLR("Quarantine: Invalid schedule.", "error")
			));
			return;
		}

		// get plesk scheduler
		$scheduler = pm_Scheduler::getInstance();

		// prevention: remove the task if existing
		$id = pm_Settings::get("quarantine_schedule_id");
		$validator = new Zend\I18n\Validator\Alnum();

		if ($validator->isValid($id)) {
			$task = $scheduler->getTaskById($id);

			if ($task !== null) {
				$scheduler->removeTask($task);
			}
		}

		// disable quarantine
		if ($state == 1) {
			pm_Settings::set("quarantine_schedule_id", false);
			pm_Settings::set("quarantine_level", "0");

			$this->_status->addMessage("info", "Automatic issue quarantining disabled");
			$this->_helper->redirector("view", "issues");
			return;
		}

		// 1 == yellow
		// 3 == red
		// 1_3 == red & yellow
		$quarantine_level = "";
		switch ($state) {
			case 3: 
				$quarantine_leqvel = "1"; break;
			case 6: 
				$quarantine_level = "3"; break;
			case 9: 
				$quarantine_level = "1_3"; break;
		}

		// schedule quarantining
		$task = new pm_Scheduler_Task();
		$task->setCmd("quarantine.php");
		$task->setSchedule(pm_Scheduler::$EVERY_5_MIN);

		$scheduler->putTask($task);

		pm_Settings::set("quarantine_schedule_id", $task->getId());
		pm_Settings::set("quarantine_level", $quarantine_level);
		pm_Settings::set("quarantine_state", $state);

		$this->_status->addMessage("info", "Automatic issue quarantining enabled");
		$this->_helper->redirector("view", "issues");
		return;
	}
}