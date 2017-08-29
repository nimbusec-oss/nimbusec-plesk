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
			["title" => "Login to Nimbusec",   "action" => "view", "controller" => "index"],
			["title" => "Issues",              "action" => "view", "controller" => "issues"],
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
		if (!$request->isPost()) {
			return false;
		}

		$fetched_action = $request->getPost($form_event);
		if (!isset($fetched_action)) {
			return false;
		}

        if (!$dynamic_action) {       
            if ($fetched_action !== $expected_action) {
                return false;
            }
        }

        if ($dynamic_action) {
            if (strpos($fetched_action, $expected_action) === false) {
                return false;
            }
        }

		return true;
	}

    public static function getSignedLoginURL($userName, $userSecret)
    {

		// get time with milliseconds ~true timestamp (hack because PHP has no long)
        $time = time();

        // encode with BCrypt
        $signature = password_hash($userName . $time . $userSecret, PASSWORD_BCRYPT);

        // previous PHP bcrypt version had a security bug in their implementation. To distinguish
        // older signatures from (safe) new ones, they changed the prefix to $2y$. The nimbusec
        // dashboard does not work with the PHP prefix, so just set the 'standard' $2a$ ;)
        $signature = str_replace("$2y$", "$2a$", $signature);

        // build the final SSO String
        $ssoString = sprintf("%slogin/signed?user=%s&time=%d&sig=%s", pm_Settings::get("portal_url"), $userName, $time, $signature);
        return $ssoString;
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
                " . pm_Locale::lmsg("issues.view.bulk_actions") . "
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
                <th>" . pm_Locale::lmsg("quarantine.controller.no_files") . "</th>
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
                <th>" . pm_Locale::lmsg("issues.view.permission") . "</th>
                <th>" . pm_Locale::lmsg("issues.view.owner") . "</th>
                <th>" . pm_Locale::lmsg("issues.view.group") . "</th>
                <th>" . pm_Locale::lmsg("quarantine.controller.action") . "</th>
            </tr>
        </thead>
        <tbody>
            {$table_body}
        </tbody>";
    }

    private static function createQTreeViewFile($path, $files)
    {
        $code = htmlentities(file_get_contents($files[0]["path"]));
        
        // return html template
        return "
        <pre>
            <code class='html'>
                {$code}
            </code>
        </pre>";
    }
}
