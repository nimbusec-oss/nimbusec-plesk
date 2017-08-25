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

		// query store
        $agent = json_decode(pm_Settings::get("agent"), true);
		
        $form = new pm_Form_Simple();
		$form->setMethod("post");
		$form->setAction($this->_helper->url("update-agent", "agent"));

		$this->view->agent_version 	= $agent["version"];
		$this->view->agent_os 		= $agent["os"];
		$this->view->agent_arch 	= $agent["arch"];
		
		// check updateability of agent		
		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();
		$version = $nimbusec->getNewestAgentVersion($agent["os"], $agent["arch"]);

		if ($version > $agent["version"]) {
			$form->addControlButtons([
				"sendTitle" => "Update to version {$version}",
				"cancelLink" => pm_Context::getModulesListUrl(),
			]);
			$this->_status->addMessage("warning", "Your current Nimbusec Agent is outdated. Please download the newest update as soon as possible");
		} else {
			$form->addControlButtons([
				"sendHidden" => true,
				"cancelLink" => pm_Context::getModulesListUrl(),
			]);
			$this->_status->addMessage("info", "You have the newest version of the Nimbusec Agent installed");
		}

		$this->view->form = $form;
	}

	public function updateAgentAction() 
	{
		if (!$this->getRequest()->isPost()) {
			$this->_forward("view", "agent");
			return;
		}

		$err = false;
		$msg = pm_Locale::lmsg("agentUpdated");
	
		$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();
		try {
			if (!$nimbusec->fetchAgent(pm_Context::getVarDir())) {
				$err = true;
				$msg = pm_Locale::lmsg("downloadError");
			}
		} catch (Exception $e) {
			$err = true;
			$message = $e->getMessage();

			if (strpos($message, "400") !== false || strpos($message, "401") !== false || strpos($message, "403") !== false) {
				$msg = pm_Locale::lmsg("invalidAPICredentials");
			} elseif (strpos($message, "404") !== false) {
				$msg = pm_Locale::lmsg("invalidAgentVersion");
			}
		}

		if (!$err) {
			$this->_status->addMessage("info", $msg);
		} else {
			$this->_status->addMessage("error", $msg);
		}

		$this->_helper->redirector("view", "agent");
		return;
	}
}