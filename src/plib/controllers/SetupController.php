<?php

class SetupController extends pm_Controller_Action
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

        $this->view->api_key = pm_Settings::get("api_key", pm_Locale::lmsg("setup.controller.placeholder.api_key"));
        $this->view->api_secret = pm_Settings::get("api_secret", pm_Locale::lmsg("setup.controller.placeholder.api_secret"));
        $this->view->api_server = pm_Settings::get("api_url");

		$this->view->extension_installed = pm_Settings::get("extension_installed");
	}

	public function downloadAgentAction() 
	{
		$request = $this->getRequest(); 
		$valid = Modules_NimbusecAgentIntegration_PleskHelper::isValidPostRequest($request, "submit", "downloadAgent");
		if (!$valid) {
			$this->_forward("view", "setup");
			return;
		}

		$api_key = $request->getPost("api_key");
		$api_secret = $request->getPost("api_secret");
		$api_server = rtrim($request->getPost("api_server"), "/");

		// validate credentials (zend i18n has extended validators)
		$validator = new Zend\I18n\Validator\Alnum();
		if (!$validator->isValid($api_key) || !$validator->isValid($api_secret)) {
			$this->_forward("view", "setup", null, [
				"response" => $this->createHTMLR(pm_Locale::lmsg("error.api_credentials"), "error")
			]);
			return;
		}

		// validate url
		$validator = new Zend\Validator\Uri();
		if (!$validator->isValid($api_server)) {
			$this->_forward("view", "setup", null, [
				"response" => $this->createHTMLR(pm_Locale::lmsg("error.api_url"), "error")
			]);
			return;	
		}

		// test credentials
		$nimbusec = Modules_NimbusecAgentIntegration_NimbusecHelper::withCred($api_key, $api_secret, $api_server);
		if (!$nimbusec->areValidAPICredentials()) {
			$this->_forward("view", "setup", null, [
				"response" => $this->createHTMLR(pm_Locale::lmsg("error.api_credentials"), "error")
			]);
			return;
		}

		try {
			// fetch server agent
			$nimbusec->fetchAgent(pm_Context::getVarDir());
		} catch (Exception $e) {
			pm_Log::err("Downloading server agent failed: {$e->getMessage()}");
			
			$this->_forward("view", "setup", null, [
				"response" => $this->createHTMLR(pm_Locale::lmsg("error.download_agent"), "error")
			]);
			return;
		}

		// upsert admin and define signaturekey
		$host = Modules_NimbusecAgentIntegration_PleskHelper::getHost();
		$admin = Modules_NimbusecAgentIntegration_PleskHelper::getAdministrator();

		// generate signature key
		$signatureKey = md5(uniqid(rand(), true));

		try {
			$nimbusec->upsertUserWithSSO((string) $admin->admin_email, $signatureKey);
		} catch (Exception $e) {
			pm_Log::err("Upserting administrator failed: {$e->getMessage()}");

			$this->_forward("view", "setup", null, [
				"response" => $this->createHTMLR(pm_Locale::lmsg("error.enable_sso"), "error")
			]);
			return;
		}

		// store signature key
		pm_Settings::set("signaturekey", $signatureKey);

		// retrieving agent token
		$token = [];
		try {
			$token = $nimbusec->getAgentCredentials("{$host['0']}-plesk");
		} catch (Exception $e) {
			pm_Log::err("Retrieving agent token failed: {$e->getMessage()}");

			$this->_forward("view", "setup", null, [
				"response" => $this->createHTMLR(pm_Locale::lmsg("error.token_retrieval"), "error")
			]);
			return;
		}

		// store agent credentials
		pm_Settings::set("agent_key", $token["key"]);
		pm_Settings::set("agent_secret", $token["secret"]);
		pm_Settings::set("agent_tokenid", $token["id"]);

		// write agent config
		$config = json_decode(file_get_contents(pm_Settings::get("agent_config")), true);
		
		$config["key"] = pm_Settings::get("agent_key");
		$config["secret"] = pm_Settings::get("agent_secret");
		$config["apiserver"] = $api_server;
		$config["domains"] = new ArrayObject();
		file_put_contents(pm_Settings::get("agent_config"), json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

		// sync domains in config
		$nimbusec->syncDomainInAgentConfig();

		// store api credentials
		pm_Settings::set("api_key", $api_key);
		pm_Settings::set("api_secret", $api_secret);
		pm_Settings::set("api_server", $api_server);

		pm_Settings::set("extension_installed", "true");

		// redirect to new view
		$this->_status->addMessage("info", pm_Locale::lmsg("setup.controller.installed"));
		$this->_helper->redirector("view", "dashboard");
		return;
	}
}