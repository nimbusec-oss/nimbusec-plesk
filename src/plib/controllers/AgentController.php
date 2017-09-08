<?php

class AgentController extends pm_Controller_Action
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
			$this->_status->addMessage("warning", $this->lmsg("agent.controller.outdated"));

		} else {
			$this->_status->addMessage("info", $this->lmsg("agent.controller.not_outdated"));
		}
	}

	public function updateAgentAction() 
	{
		$request = $this->getRequest(); 
		$valid = Modules_NimbusecAgentIntegration_PleskHelper::isValidPostRequest($request, "submit", "updateAgent");
		if (!$valid) {
			$this->_forward("view", "agent");
			return;
		}

		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();
		try {
			// fetch server agent
			$nimbusec->fetchAgent(pm_Context::getVarDir());
		} catch (Exception $e) {
			pm_Log::err("Downloading server agent failed: {$e->getMessage()}");
			
			$this->_forward("view", "agent", null, [
				"response" => $this->createHTMLR($this->lmsg("error.download_agent"), "error")
			]);
			return;
		}

		$this->_status->addMessage("info", $this->lmsg("agent.controller.updated"));
		$this->_helper->redirector("view", "agent");
		return;
	}
}