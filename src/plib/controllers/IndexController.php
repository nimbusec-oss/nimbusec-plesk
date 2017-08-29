<?php

class IndexController extends pm_Controller_Action
{
    public function init()
    {
        parent::init();
        $this->initPleskStore();

        $this->view->pageTitle = pm_Settings::get("extension_title");

		$this->view->e = new Zend\Escaper\Escaper();
        $this->view->h = $this->_helper;
    }

    private function initPleskStore()
    {
        pm_Settings::set("extension_title", "Nimbusec Webshell Detection");

        pm_Settings::set("agent_config", pm_Context::getVarDir() . "/agent.conf");
        pm_Settings::set("agent_log", pm_Context::getVarDir() . "/agent.log");

        pm_Settings::set("shellray_url", "https://shellray.com/upload");
        pm_Settings::set("portal_url", "https://portal.nimbusec.com/");
        pm_Settings::set("api_url", "https://api.nimbusec.com");

        pm_Settings::set("quarantine_root", pm_Context::getVarDir() . "/quarantine");
    }

    public function indexAction()
    {
        $installed = pm_Settings::get("extension_installed");
        if ($installed !== "true") {
            $this->_forward("view", "setup");
            return;
        }

        $this->_forward("view");
    }

    public function viewAction()
    {
        $this->view->tabs = Modules_NimbusecAgentIntegration_PleskHelper::getTabs();

        if ($this->getRequest()->isPost()) {
            $admin = Modules_NimbusecAgentIntegration_PleskHelper::getAdministrator();

            $this->_helper->json([
				"link" => Modules_NimbusecAgentIntegration_PleskHelper::getSignedLoginURL((string) $admin->admin_email, pm_Settings::get('signaturekey'))
            ]);
        }
    }
}
