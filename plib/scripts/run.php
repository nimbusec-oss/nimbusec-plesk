<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

set_time_limit(0);
pm_Context::init('nimbusec-agent-integration');

$binary = array(
	'linux' => 'agent',
	'unix' => 'agent',
	'windows' => 'agent.exe',
	'winnt' => 'agent.exe',
	'win32' => 'agent.exe',
);

$selected = $binary[strtolower(PHP_OS)];

$path_exec = pm_Context::getVarDir() . '/' . $selected;
$path_conf = pm_Context::getVarDir() . '/agent.conf';

$cmd = sprintf("%s -config %s", $path_exec, $path_conf);
if (pm_Settings::get("agentYara") == "1") {
	$cmd .= " -yara";
}

$cmd .= " > " . pm_Context::getVarDir() . "agent-out.log 2> " . pm_Context::getVarDir() . "agent-err.log";

system($cmd);
