<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

set_time_limit(0);
pm_Context::init("nimbusec-agent-integration");

$quarantine_level = pm_Settings::get("quarantine_level");
pm_Log::info(var_export($quarantine_level, true));

if ($quarantine_level == "0") {
    exit(0);
}

// read and convert to integers
$quarantine_levels = array_map(function($level) { return intval($level); }, explode("_", $quarantine_level));

$nimbusec = new Modules_NimbusecAgentIntegration_NimbusecHelper();

// get registered plesk domains
$domains = $nimbusec->getRegisteredPleskDomains();
$domain_names = array_keys($domains);

// get issues
$issues = $nimbusec->getWebshellIssuesByDomain($domain_names);
$filtered = $nimbusec->filterByQuarantined($issues);

// all results by domain
$quarantine_counter = 0;
foreach ($filtered as $name => $domain) {

    // find results which should be quarantined
    $results = array_filter($domain["results"], function($result) use ($quarantine_levels) {
        return in_array($result["severity"], $quarantine_levels);
    });

    foreach ($results as $result) {

        // quarantine - hijyaaa
        $success = $nimbusec->moveToQuarantine($name, $result["resource"]);
        if (!$success) {
            pm_Log::err("Automatic quarantining: something went wrong a result of {$name}.");
            exit(1);
        }

        $quarantine_counter++;
    }
}

if ($quarantine_counter > 0) {
    pm_Log::info("Automatic quarantining: {$quarantine_counter} new issues quarantined");
}
