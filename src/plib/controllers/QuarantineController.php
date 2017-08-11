<?php

class QuarantineController extends pm_Controller_Action
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

	private function createJSONR($msg) 
	{
		return Modules_NimbusecAgentIntegration_PleskHelper::createJSONR($msg);
	}

	public function viewAction() 
	{
		$this->view->tabs = Modules_NimbusecAgentIntegration_PleskHelper::getTabs();

		// try to fetch passed parameters (e.g from forwards)
        $this->view->response = $this->getRequest()->getParam("response");
		$this->view->root_path = "/";
	}

	public function fetchAction() 
	{
		$request = $this->getRequest(); 
		$valid = Modules_NimbusecAgentIntegration_PleskHelper::isValidPostRequest($request, "action", "fetch");
		if (!$valid) {
			$this->_helper->json(array(
				"error" => $this->createJSONR("Quarantine: Invalid request")
			));
			return;
		}

		$path = $request->getPost("path");

		// valdiate path
		$validator = new Zend\Validator\NotEmpty();
		if (!$validator->isValid($path)) {
			$this->_helper->json(array(
				"error" => $this->createJSONR("Quarantine: Invalid path given")
			));
		}

		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();
		
		$path = $nimbusec->resolvePath($path);
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
}