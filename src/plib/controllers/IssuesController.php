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
		$domains_names = array_keys($domains);

		// get issues
        $issues = $nimbusec->getWebshellIssuesByDomain($domain_names);

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
            foreach ($files as $key => $value) {
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

        $this->view->colors = array("#bbb", "#fdd835", "#f44336");
        $this->view->issues = $issues;
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
		$success = $nimbusec->moveToQuarantine($domain, $file);
		if (!$success) {
			$this->_forward("view", "issues", null, array(
				"response" => $this->createHTMLR("Quarantine: An error occurred. Please check the log files.", "error")
			));
			return;	
		}

		$this->_status->addMessage("info", "Successfully moved {$file} into Quarantine.");
		$this->_helper->redirector("view", "issues");
		return;

	}
}