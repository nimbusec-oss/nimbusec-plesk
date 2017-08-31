<?php

$messages = [

	/**************** general ****************/
	"msg.installed" => "installed",
	"msg.not_installed" => "not_installed",
	"msg.directory" => "Directory",
	"msg.required" => "Required fields",

	"msg.issues.none" => "None",
	"msg.issues.yellow" => "Yellow issues",
	"msg.issues.red" => "Red issues",

	"button.wait" => "Please wait",

	/**************** error ****************/
	"error.download_agent" => "Could not download the Nimbusec Agent",
	"error.enable_sso" => "Could not establish a connection to the Nimbusec Portal",
	"error.token_retrieval" => "Could not retrieve an Nimbusec Agent Token",
	"error.api_credentials" => "The specified key and secret are invalid. Please make sure you have the right credentials entered and try again.",
	"error.api_url" => "The specified server URL is invalid",
	"error.agent_not_supported" => "Our Nimbusec Agent does not seem to support your OS. If you see this message on a Windows or Linux Server please feel free to contact us at office@cumulo.at.",
	"error.invalid_domain" => "Invalid domain",
	"error.invalid_issue" => "Invalid issue",
	"error.file_not_exist" => "File does not exist",
	"error.invalid_path" => "Invalid path",
	"error.unexpected" => "An unexpected error occured",
	"error.msg" => "An error occured while performing the action: %s",
	"error.invalid_request" => "Invalid request",

	"error.quarantine.file" => "File %s does not exist. Failed to move it into Quarantine",
	"error.quarantine.directory" => "Failed to create a Quarantine directory: %s",
	"error.quarantine" => "Failed to move %s into Quarantine at %s: %s", 
	"error.unquarantine" => "Failed to move %s back from Quarantine to %s: %s", 

	/**************** agent ****************/
	"agent.view.title" => "Agent Overview",
	"agent.view.description" => "Keep the Nimbusec Agent up-to-date by downloading the newest version every now and then. This will guarantee a reliable and flawless malware detection.",
	"agent.view.installed.title" => "Current agent status",
	"agent.view.installed.value" => "installed",
	"agent.view.version.title" => "Current agent version",
	"agent.view.os.title" => "Current agent operating system",
	"agent.view.arch.title" => "Current agent architecture",
	
	"agent.controller.outdated" => "Your current Nimbusec Agent is outdated. Please download the newest update as soon as possible",
	"agent.controller.not_outdated" => "You have the newest version of the Nimbusec Agent installed",
	"agent.controller.update" => "Update to version %s",

	"agent.controller.updated" => "Successfully updated Nimbusec Agent to the newest version",

	/**************** index ****************/
	"index.view.title" => "Login to Nimbusec",
	"index.view.description" => "Access the Nimbusec Portal directly and conveniently through Single Sign On. For more information about the usage of this plugin see <a href=\"https://kb.nimbusec.com/Integrations/Plesk\" target=\"_blank\">https://kb.nimbusec.com/Integrations/Plesk</a>.",
	"index.view.login" => "Click here to access the Nimbusec Portal",

	/**************** setup ****************/
	"setup.view.title" => "Setup",
	"setup.view.description" => "Enter your Nimbusec API credentials in order to download the Nimbusec Server Agent. " .
								"Leave the API Server field unchanged for preserving the stability of the extension. " .
								"<br>For more information about getting API credentials, please contact us at <a href=\"mailto:plesk@nimbusec.com\">plesk@nimbusec.com</a>." .
								"<br>The plesk extension documentation can be found at <a href=\"https://kb.nimbusec.com/Integrations/Plesk\" target=\"_blank\">https://kb.nimbusec.com/Integrations/Plesk</a>.",
	"setup.view.download_agent" => "Download the Nimbusec Server Agent",

	"setup.controller.placeholder.api_key" => "Your Nimbusec API Key",
	"setup.controller.placeholder.api_secret" => "Your Nimbusec API Secret",

	"setup.controller.installed" => "Successfully installed the Nimbusec Agent",

	/**************** settings ****************/
	/* unregistered */
	"settings.view.unreg.title" => "Unregistered Domains",
	"settings.view.unreg.description" => "Below you can find your domains inside of plesk which has not been registered with Nimbusec. " .
										 "In order to register a domain, select the domain you want to register " .
										 "along with the bundle<br>and click on the button \"Register the selected domains\" to complete the registration.",
	"settings.view.unreg.register" => "Register the selected domains",
	"settings.view.unreg.no_domains" => "No domains found",
	"settings.view.unreg.domain" => "Unregistered domains",

	/* registered */
	"settings.view.reg.title" => "Registered Domains",
	"settings.view.reg.description" => "Below you can find your registered domains with their corresponding bundle. " .
									   "To unregister a bundle, select it on the checkbox and click on the button to complete the unregistration.",
	"settings.view.reg.unregister" => "Unregister the selected domains",
	"settings.view.reg.no_domains" => "No domains found registered with this bundle.",
	"settings.view.reg.domain" => "Registered domains",

	/* agent conf */
	"settings.view.conf.title" => "Agent Configuration",
	"settings.view.conf.description" => "Below you can see the current configuration file for the Nimbusec Agent",
	"settings.view.conf.configuration" => "Configuration (agent.conf)",

	/* schedule settings */
	"settings.view.schedule.title" => "Agent Schedule Settings",
	"settings.view.schedule.description" => "Within these settings, you can configure the Nimbusec Agent for a specific schedule as well as enabling or disabling the agent at all." .
											"<br>Please note that the Nimbusec Agent will not start until a schedule is set.",
	"settings.view.schedule.status" => "Scheduled (please check or uncheck to enable or disable the agent execution)",
	"settings.view.schedule.update" => "Update schedule settings",

	"settings.view.schedule.yara" => "Activate Yara", 
	"settings.view.schedule.yara_not_supported" => "(not supported with 32bit agent)",

	"settings.view.schedule.interval" => "Agent Scan Interval",
	"settings.view.schedule.interval.once" => "1x per day at 1:30 PM",
	"settings.view.schedule.interval.twice" => "2x per day at 1:30 PM and 1:30 AM",
	"settings.view.schedule.interval.three_times" => "3x per day at 1:30 AM, 9:30 AM and 5:30 PM",
	"settings.view.schedule.interval.four_times" => "4x per day at 1:30 AM, 7:30 AM, 1:30 PM and 7:30 PM",

	/* controller */
	"settings.controller.no_domains" => "No domains selected.",
	"settings.controller.invalid_bundle" => "Invalid bundle chosen.",
	"settings.controller.registered" => "Successfully registered domains with %s",
	"settings.controller.unregistered" => "Successfully unregistered domains from %s",
	"settings.controller.invalid_interval" => "Internal interval given",
	"settings.controller.schedule.updated" => "Agent Schedule settings successfully updated",

	/**************** issues ****************/
	"issues.view.title" => "Issues",
	"issues.view.bulk_actions" => "Bulk actions",
	"issues.view.quarantine" => "Move to Quarantine",
	"issues.view.false_positive" => "False Positive",
	"issues.view.select_issues" => "Select / Deselect all issues",
	"issues.view.automatic_quarantine" => "Automatically move domains to quarantine",
	"issues.view.automatic_quarantine.hint" => "By having an automaitic cron job running in background, all new issues will be moved into quarantine. " . 
												"The cron job will run every 5 minute on your host system. With the checkbox you can specify what kind of issues you want to be quarantined.",
	"issues.view.apply" => "Set automatic issue quarantining",
	"issues.view.no_issues" => "No issues found for your domains.",
	"issues.view.source_code" => "View source code",

	/* for an issue */
	"issues.view.occured_on" => "Occured On",
	"issues.view.known_since" => "Known Since",
	"issues.view.path" => "Path",
	"issues.view.name" => "Name",
	"issues.view.md5" => "MD5",
	"issues.view.owner" => "Owner",
	"issues.view.group" => "Group",
	"issues.view.permission" => "Permission",

	"issues.controller.false_positive" => "Successfully marked %s as False Positive",
	"issues.controller.quarantine" => "Successfully moved %s into Quarantine",
	"issues.controller.bulk_quarantine" => "Successfully moved your selection into Quarantine",
	"issues.controller.automatic_quarantine.enabled" => "Successfully established automatic quarantine",
	"issues.controller.automatic_quarantine.disabled" => "Successfully deactivated automatic quarantine",

	"issues.controller.no_issues" => "No issues selected. Please select an issue in order to perform the action.",
	"issues.controller.invalid_schedule" => "Invalid schedule selected",

	/**************** quarantine ****************/
	"quarantine.controller.no_files_found" => "No files found in Quarantine.",
	"quarantine.controller.invalid_store" => "Failed to retrieve Quarantine Store.",
	"quarantine.controller.unquarantine" => "Move back from Quarantine",
	"quarantine.controller.no_files" => "Number of quarantined files",
	"quarantine.controller.action" => "Action",

	"quarantine.controller.quarantined_on" => "In Quarantine since",
	"quarantine.controller.old_path" => "Moved from",
	"quarantine.controller.filesize" => "Filesize",

	"quarantine.controller.no_files" => "No files selected. Please select a file in order to perform the action",

	"quarantine.controller.unquarantined" => "Successfully unquarantined %s",
	"quarantine.controller.bulk_unquarantined" => "Successfully unquarantined your selection",
];
