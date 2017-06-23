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

// define command
$utility = "agent.sh";
$args = array();

array_push($args, "{$varDir}/{$agent}");
array_push($args, "-config", "{$varDir}/agent.conf");

if (pm_Settings::get("agentYara") == "1") {
	array_push($args, "-yara");
}

array_push($args, ">", "{$varDir}/agent.log", "2>&1");

pm_ApiCli::callSbin($utility, $args);
