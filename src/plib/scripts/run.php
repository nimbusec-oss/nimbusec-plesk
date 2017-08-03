<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

set_time_limit(0);
pm_Context::init('nimbusec-agent-integration');

$varDir = pm_Context::getVarDir();
$agent = json_decode(pm_Settings::get("agent"), true)["name"];

$cmd = "{$varDir}/{$agent} -config " . pm_Settings::get("agent_config") . " ";

if (pm_Settings::get("agent_yara") === "true") {
	$cmd .= "-yara ";
}

$cmd .= "> " . pm_Settings::get("agent_log") . " 2>&1";

system($cmd);