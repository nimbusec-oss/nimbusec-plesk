<?php

class Modules_NimbusecAgentIntegration_PleskHelper
{
    public static function getTabs()
    {
        $installed = pm_Settings::get("extension_installed");
        if ($installed !== "true") {
            return [
				["title" => "Setup", "action" => "view", "controller" => "setup"],
            ];
        }

        return [
			["title" => "Dashboard",           "action" => "view", "controller" => "dashboard"],
			["title" => "Quarantine",          "action" => "view", "controller" => "quarantine"],
			["title" => "Settings",            "action" => "view", "controller" => "settings"],
			["title" => "Agent Overview",      "action" => "view", "controller" => "agent"],
			["title" => "Setup",               "action" => "view", "controller" => "setup"],
        ];
    }

    public static function setQuarantine($quarantine_store)
    {
        $encoded = json_encode($quarantine_store);
        
        // Plesk allowes the store to have a maximum length of 2000 chars.
        // To prevent this, cut the store into multiple parts, each of length 1980. 
        // Use the rest 20 chars for referencing to the next store.
        $splitted = str_split($encoded, 1980);

        for ($i = 0; $i < count($splitted); $i++) {
            $store_part = $splitted[$i];

            // only append reference if there is a next store
            if (($i + 1) < count($splitted)) {
                $store_part .= "_qref_" . strval($i + 1);
            }

            $key = "quarantine_{$i}";
            if ($i === 0) {
                $key = "quarantine";
            }

            pm_Settings::set($key, $store_part);
        }
    }

    public static function getQuarantine()
    {
        $quarantine_store = pm_Settings::get("quarantine", "");
        if ($quarantine_store === "") {
            return [];
        }

        return json_decode(Modules_NimbusecAgentIntegration_PleskHelper::getFullQuarantineStore($quarantine_store), true);
    }

    // fetch all stores with their references
    private static function getFullQuarantineStore($quarantine_store) 
    {
        // are there references to other stores?
        $stores = explode("_qref_", $quarantine_store);
        if (count($stores) === 1) {
            return $stores[0];
        }

        $ref_index = $stores[1];
        $next = pm_Settings::get("quarantine_{$ref_index}", "");
        if ($next === "") {
            pm_Log::err("Quarantine: invalid store: quarantine_{$ref_index}");
            throw new Exception(pm_Locale::lmsg("quarantine.controller.invalid_store"));
        }

        return $stores[0] . Modules_NimbusecAgentIntegration_PleskHelper::getFullQuarantineStore($next);
    }

    public static function isValidPostRequest($request, $form_event = "action", $expected_action, $dynamic_action = false) 
    {
        // is post
		if (!$request->isPost()) {
			return false;
		}

        // does the given form element exist
		$fetched_action = $request->getPost($form_event);
		if (!isset($fetched_action)) {
			return false;
		}

        if (!$dynamic_action) {       
            // does the form element equals the given action
            if ($fetched_action !== $expected_action) {
                return false;
            }
        }

        if ($dynamic_action) {
            // does the form element starts with the given action
            if (strpos($fetched_action, $expected_action) === false) {
                return false;
            }
        }

		return true;
    }
    
    public static function formatPermission($permissions)
    {
        // skip file type
        if (strlen($permissions) > 3) {
            $permissions = substr($permissions, -3);
        }

        $scopes = str_split($permissions);
		
        $human = "";
        foreach ($scopes as $scope) {
            $human .= (intval($scope) >= 4) ? "r" : "-";
            $human .= (intval($scope) >= 2) ? "w" : "-";
            $human .= (intval($scope) >= 1) ? "x" : "-";
        }

        return $human;
    }

    public static function formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = ["B", "KB", "MB", "GB", "TB"];

        return round(pow(1024, $base - floor($base)), $precision) . " " . $suffixes[floor($base)];
    }

    public static function uuidv4()
    {
        return sprintf(
		    "%04x%04x-%04x-%04x-%04x-%04x%04x%04x",

		// 32 bits for "time_low"
		mt_rand(0, 0xffff),
		    mt_rand(0, 0xffff),

		// 16 bits for "time_mid"
		mt_rand(0, 0xffff),

		// 16 bits for "time_hi_and_version",
		// four most significant bits holds version number 4
		mt_rand(0, 0x0fff) | 0x4000,

		// 16 bits, 8 bits for "clk_seq_hi_res",
		// 8 bits for "clk_seq_low",
		// two most significant bits holds zero and one for variant DCE1.1
		mt_rand(0, 0x3fff) | 0x8000,

		// 48 bits for "node"
		mt_rand(0, 0xffff),
		    mt_rand(0, 0xffff),
		    mt_rand(0, 0xffff)
		);
    }

    public static function getAdministrator()
    {
        $request = <<<DATA
<server>
	<get>
		<admin/>
	</get>
</server>
DATA;

        $resp = pm_ApiRpc::getService()->call($request);
        return $resp->server->get->result->admin;
    }

    //get hostname from plesk api
    public static function getHost()
    {
        $request = <<<DATA
<server>
	<get>
		<gen_info/>
	</get>
</server>
DATA;

        $resp = pm_ApiRpc::getService()->call($request);
        return $resp->server->get->result->gen_info->server_name;
    }

    public static function getHostDomains()
    {
        $domains = [];

        $fetched = pm_Domain::getAllDomains();
        $filtered = array_filter($fetched, function ($domain) {
            return $domain->hasHosting();
        });

        foreach ($filtered as $domain) {
            $name = $domain->getDisplayName();
            $path = $domain->getDocumentRoot();

            $domains[$name] = $path;
        }

        return $domains;
    }

    // get htdocs dir for given domain from plesk api
    public static function getDomainDir($domain)
    {
        $fetched = pm_Domain::getByName($domain);

        if (!$fetched->hasHosting()) {
            return false;
        }

        return $fetched->getDocumentRoot();
    }

    public static function createMessage($msg, $level)
    {
        $title = $level;
        if ($level == "info") {
            $title = "information";
        }

        $title = ucfirst($title);

        // return html template
        return "
        <div class='msg-box msg-{$level}'>
            <div class='msg-content'>
                <span class='title'>
                    {$title}:
                </span>

                {$msg}
            </div>
        </div>";
    }

    public static function createQNavigationBar($path, $display_name = "")
    {
        // template variable
        $navigation_bar = "";

        $subpaths = array_filter(explode("/", $path));

        $partial_path = "";
        for ($i = 0; $i < count($subpaths); $i++) {
            $subpath = $subpaths[$i];
            $partial_path .= "{$subpath}/";

            // for the last layer, take the replacement
            if ($i === 2) {
                $subpath = $display_name;
            }

            $navigation_bar .= "
            <li>
                <a id='subpath' path='{$partial_path}'>
                    <span>
                        {$subpath}
                    </span>
                </a>
            </li>";
        }

        // return html template
        return "
        <div class='pathbar'>
            <ul>
                {$navigation_bar}
            </ul>
        </div>";
    }

	public static function createQOptions($path, $helper) 
    {
        // return html template
		return "
        <div class='form-row'>
            <div class='field-name' style='margin-left: .6%;'>
                " . pm_Locale::lmsg("dashboard.view.bulk_actions") . "
            </div>

            <div class='field-value' style='margin-bottom: .5%;'>
                <a onclick='bulk_request(\"{$path}\", \"unquarantine-bulk\", updateHandler, \"" . $helper->url("unquarantine-bulk", "quarantine") . "\");' 
                        style='color: #353535;'>

                    <i class='mdi mdi-bug'></i>
                    <span>" . pm_Locale::lmsg("quarantine.controller.unquarantine") . "</span>
                </a>
            </div>

        </div>";
	}

	public static function createQTreeView($path, $files, $helper)
    {
		if (count($files) == 0) {
			return "";
		}

        // detect type
        $type = $files[0]["type"];

        // file view
        if ($type == 2) {
            return self::createQTreeViewFile($path, $files);
        }
        
        // build tree view
        $tree_view = "";
        if ($type === 0) {
            $tree_view = self::createQTreeViewDir($path, $files, $helper);
        } elseif ($type === 1) {
            $tree_view = self::createQTreeViewDomain($path, $files, $helper);
        }

        // return html template
        return "
        <div class='list'>
            <table>
                {$tree_view}
            </table>
        </div>";
    }

    private static function createQTreeViewDir($path, $files, $helper)
    {
        $table_body = "";
        foreach ($files as $file) {
            $table_body .= "
            <tr>
                <td>
                    <input type='checkbox' id='select'/>
                    <a id='subpath' path='{$path}/{$file['name']}'>
                        <i class='mdi mdi-folder'></i>
                        <span>{$file['name']}</span>
                    </a>
                </td>
                <td>{$file['count']}</td>
                <td>
                    <a onclick='request_wrapper(\"{$path}/{$file['name']}\", \"unquarantine\", updateHandler, \"" . $helper->url("unquarantine", "quarantine") . "\");'>
                        <i class='mdi mdi-bug'></i>
                        <span>" . pm_Locale::lmsg("quarantine.controller.unquarantine") . "</span>
                    </a>
                </td>
            </tr>";
        }

        // return html template
        return "
        <thead>
            <tr>
                <th style='width: 30%;'><input type='checkbox' id='select-all'/> Name</th>
                <th>" . pm_Locale::lmsg("quarantine.controller.no_of_files") . "</th>
                <th>" . pm_Locale::lmsg("quarantine.controller.action") . "</th>
            </tr>
        </thead>
        <tbody>
            {$table_body}
        </tbody>";
    }

    private static function createQTreeViewDomain($path, $files, $helper)
    {
        $table_body = "";
        foreach ($files as $file) {
            $table_body .= "
            <tr>
                <td>
                    <input type='checkbox' id='select'/>
                    <a id='subpath' path='{$path}/{$file['id']}'>
                        <i class='mdi mdi-file-outline'></i>
                        <span>{$file['name']}</span>
                    </a>
                </td>
                <td>{$file['create_date']}</td>
                <td>{$file['old']}</td>
                <td>{$file['filesize']}</td>
                <td>{$file['permission']}</td>
                <td>{$file['owner']}</td>
                <td>{$file['group']}</td>
                <td>
                    <a onclick='request_wrapper(\"{$path}/{$file['id']}\", \"unquarantine\", updateHandler, \"" . $helper->url("unquarantine", "quarantine") . "\");'>
                        <i class='mdi mdi-bug'></i>
                        <span>" . pm_Locale::lmsg("quarantine.controller.unquarantine") . "</span>
                    </a>
                </td>
            </tr>";
        }

        // return html template
        return "
        <thead>
            <tr>
                <th style='width: 30%;'><input type='checkbox' id='select-all'/> Name</th>
                <th>" . pm_Locale::lmsg("quarantine.controller.quarantined_on") . "</th>
                <th>" . pm_Locale::lmsg("quarantine.controller.old_path") . "</th>
                <th>" . pm_Locale::lmsg("quarantine.controller.filesize") . "</th>
                <th>" . pm_Locale::lmsg("dashboard.view.permission") . "</th>
                <th>" . pm_Locale::lmsg("dashboard.view.owner") . "</th>
                <th>" . pm_Locale::lmsg("dashboard.view.group") . "</th>
                <th>" . pm_Locale::lmsg("quarantine.controller.action") . "</th>
            </tr>
        </thead>
        <tbody>
            {$table_body}
        </tbody>";
    }

    private static function createQTreeViewFile($path, $files)
    {
        $code = trim(htmlentities(file_get_contents($files[0]["path"])));
        
        // return html template
        return "<pre style='white-space: pre-wrap;'><code class='php'>{$code}</code></pre>";
    }

    public static function createFormRow($title, $value)
    {
        return "
        <div class='form-row'>
            <div class='field-name'>
                {$title}
            </div>
            <div class='field-value'>
                {$value}
            </div>
        </div>";
    }

    public static function createSelectIssuesByDomain($domain)
    {
        return "
        <div style='margin-top: 5px;'>
            <a id='issue-link-{$domain}'>
                " . pm_Locale::lmsg("dashboard.view.select_issues") . "
            </a>
        </div>";
    }

    public static function createSeperator()
    {
        return "<div class='btns-box'></div>";
    }

    public static function createIssuePanel($domain, $issue, $helper)
    {
        $colors = ["#bbb", "#fdd835", "#f44336"];

        $left_bubble = $issue['severity'] == 1 ? $colors[1] : $colors[0];
        $right_bubble = $issue['severity'] > 1 ? $colors[2] : $colors[0];

        return "
        <div class='panel panel-collapsible p-promo panel-collapsed'>
            <div class='panel-wrap'>

                <div class='panel-heading'>
                    <div class='panel-heading-wrap'>
                        <span class='panel-control'>

                            <div style='margin-left: -140px; color: #303030; margin-top: 2px;display: inline-block;'>
                                    " . date('m/d/o h:i A', $issue['lastDate'] / 1000) . "                                           
                            </div>
                            <div style='margin-left: -246px; display: inline-block;'>

                                <form id='falsePositive' method='post' action='" . $helper->url('false-positive', 'dashboard') . "'>
                                    <input name='action' value='falsePositive' type='hidden'/>
                                    <input name='domain' value='{$domain}' type='hidden'/>
                                    <input name='resultId' value='{$issue['id']}' type='hidden'/>
                                    <input name='file' value='{$issue['resource']}' type='hidden'>
                                    <a onclick='sendForm(this);'>
                                        <i class='mdi mdi-flag'></i>
                                        <span class='button-text'>    
                                            <span>
                                                " . pm_Locale::lmsg('dashboard.view.false_positive') . "
                                            </span>
                                        </span>
                                        <span class='button-loading' style='display: none;'>
                                            <span style='margin-right: 5px;'>
                                                Please Wait <i class='fa fa-spinner fa-fw fa-spin'></i>
                                            </span>
                                        </span>
                                    </a>
                                </form>

                            </div>
                            <div style='margin-left: -256px; margin-top: 2px;display: inline-block;'>

                                <form id='moveToQuarantine' method='post' action='" . $helper->url('quarantine', 'dashboard') . "'>
                                    <input name='action' value='moveToQuarantine' type='hidden'>
                                    <input name='domain' value='{$domain}' type='hidden'>
                                    <input name='file' value='{$issue['resource']}' type='hidden'>
                                    <a onclick='sendForm(this);'>
                                        <i class='mdi mdi-bug'></i>
                                        <span class='button-text'>    
                                            <span>
                                                " . pm_Locale::lmsg('dashboard.view.quarantine') . "
                                            </span>
                                        </span>
                                        <span class='button-loading' style='display: none;'>
                                            <span style='margin-right: 5px;'>
                                                Please Wait <i class='fa fa-spinner fa-fw fa-spin'></i></span>
                                        </span>
                                    </a>
                                </form>

                            </div>

                        </span>

                        <div class='panel-heading-name'>
                            <span style='margin-right: 5px'>
                                <input type='checkbox' id='issue-{$domain}'/>
                                <i class='mdi mdi-checkbox-blank-circle' style='color: {$left_bubble}'></i>
                                <i class='mdi mdi-checkbox-blank-circle' style='color: {$right_bubble}'></i>
                            </span>

                            <span style='font-size: 13px'>
                                {$issue['resource']}
                            </span>
                        </div>

                    </div>
                </div>

                <div class='panel-content'>
                    <div class='panel-content-wrap'>

                        <div class='box-area'>
                            <div class='form-row'>
                                <div class='field-name'>
                                    <span>
                                        " . pm_Locale::lmsg('dashboard.view.occured_on') . "
                                    </span>
                                </div>
                                <div class='field-value'>
                                    <span style='vertical-align: middle;'>
                                        " . date('m/d/o h:i A', $issue['lastDate'] / 1000) . "
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class='box-area'>
                            <div class='form-row'>
                                <div class='field-name'>
                                    <span>
                                        " . pm_Locale::lmsg('dashboard.view.known_since') . "
                                    </span>
                                </div>
                                <div class='field-value'>
                                    <span style='vertical-align: middle;'>
                                        " . date('m/d/o h:i A', $issue['createDate'] / 1000) . "
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class='box-area'>
                            <div class='form-row'>
                                <div class='field-name'>
                                    <span>
                                        " . pm_Locale::lmsg('dashboard.view.path') . "
                                    </span>
                                </div>
                                <div class='field-value'>
                                    <span style='vertical-align: middle;'>
                                        {$issue['resource']}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class='box-area'>
                            <div class='form-row'>
                                <div class='field-name'>
                                    <span>
                                        " . pm_Locale::lmsg('dashboard.view.name') . "
                                    </span>
                                </div>
                                <div class='field-value'>
                                    <span style='vertical-align: middle;'>
                                        {$issue['threatname']}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class='box-area'>
                            <div class='form-row'>
                                <div class='field-name'>
                                    <span>
                                        " . pm_Locale::lmsg('dashboard.view.md5') . "
                                    </span>
                                </div>
                                <div class='field-value'>
                                    <span style='vertical-align: middle;'>
                                        {$issue['md5']}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class='box-area'>
                            <div class='form-row'>
                                <div class='field-name'>
                                    <span>
                                        " . pm_Locale::lmsg('dashboard.view.owner') . "
                                    </span>
                                </div>
                                <div class='field-value'>
                                    <span style='vertical-align: middle;'>
                                        {$issue['owner']}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class='box-area'>
                            <div class='form-row'>
                                <div class='field-name'>
                                    <span>
                                        " . pm_Locale::lmsg('dashboard.view.group') . "
                                    </span>
                                </div>
                                <div class='field-value'>
                                    <span style='vertical-align: middle;'>
                                        {$issue['group']}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class='box-area'>
                            <div class='form-row'>
                                <div class='field-name'>
                                    <span>
                                        " . pm_Locale::lmsg('dashboard.view.permission') . "
                                    </span>
                                </div>
                                <div class='field-value'>
                                    <span style='vertical-align: middle;'>
                                        {$issue['permission']}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class='source-code'>
                            <a>
                                " . pm_Locale::lmsg('dashboard.view.source_code') . " <i class='mdi mdi-arrow-down-drop-circle source-code-icon'></i>
                            </a>
                        
                            <div class='panel panel-collapsible panel-collapsed source-code-panel' style='margin: 0px; border: 0px'>
                                <div class='panel-wrap'>
                                    <div class='panel-content'>
                                        <div class='panel-content-wrap'>
                                            <pre style='white-space: pre-wrap;'><code class='php'>" . trim(htmlentities(file_get_contents($issue['resource']))) . "</code></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>";
    }
}
