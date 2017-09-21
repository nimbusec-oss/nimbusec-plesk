<?php

$messages = [

	/**************** general ****************/
	"msg.installed" => "installed",
	"msg.not_installed" => "not installed",
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
	"error.agent_not_supported" => "Our Nimbusec Agent does not seem to support your OS. If you see this message on a Windows or Linux Server please feel free to contact us at office@nimbusec.at.",
	"error.invalid_domain" => "Invalid domain",
	"error.invalid_issue" => "Invalid issue",
	"error.file_not_exist" => "File does not exist",
	"error.invalid_path" => "Invalid path",
	"error.unexpected" => "An unexpected error occurred",
	"error.msg" => "An error occurred while performing the action: %s",
	"error.invalid_request" => "Invalid request",

	"error.quarantine.file" => "File %s does not exist. Failed to move it into quarantine",
	"error.quarantine.directory" => "Failed to create a quarantine directory: %s",
	"error.quarantine" => "Failed to move %s into quarantine at %s: %s", 
	"error.fp" => "Cannot mark the file as false positive: %s",
	"error.unquarantine" => "Failed to move %s back from quarantine to %s: %s", 

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

	/**************** setup ****************/
	"setup.view.title" => "Setup",
	"setup.view.description" => "Enter your Nimbusec API credentials in order to download the Nimbusec Server Agent. " .
								"Leave the API Server field unchanged for preserving the stability of the extension. " .
								"<br>For more information about getting API credentials, please contact us at <a href=\"mailto:plesk@nimbusec.com\">plesk@nimbusec.com</a>." .
								"<br>The Plesk extension documentation can be found at <a href=\"https://kb.nimbusec.com/Integrations/Plesk\" target=\"_blank\">https://kb.nimbusec.com/Integrations/Plesk</a>.",
	"setup.view.download_agent" => "Download the Nimbusec Server Agent",

	"setup.controller.placeholder.api_key" => "Your Nimbusec API Key",
	"setup.controller.placeholder.api_secret" => "Your Nimbusec API Secret",

	"setup.controller.installed" => "Successfully installed the Nimbusec Agent",

	/**************** settings ****************/
	"settings.view.title" => "Settings",

	/* unregistered */
	"settings.view.unreg.title" => "Unregistered Domains",
	"settings.view.unreg.description" => "Below you can find your domains inside of Plesk which has not been registered with Nimbusec. " .
										 "In order to register a domain, select the domain you want to register " .
										 "along with the bundle<br>and click on the button \"Register the selected domains\" to complete the registration.",
	"settings.view.unreg.register" => "Register the selected domains",
	"settings.view.unreg.no_domains" => "No domains found",
	"settings.view.unreg.domain" => "Unregistered domains",

	/* registered */
	"settings.view.reg.title" => "Registered Domains",
	"settings.view.reg.description" => "Below you can find your registered domains with their corresponding bundle. " .
									   "To unregister a domain, select it and click on the button.",
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

	"settings.view.schedule.yara" => "Activate static malware signatures", 
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
	"settings.controller.invalid_interval" => "Invalid interval given",
	"settings.controller.schedule.updated" => "Agent Schedule settings successfully updated",

	/**************** dashboard ****************/
	"dashboard.view.title" => "Dashboard",
	"dashboard.view.description" => "The Nimbusec Dashboard shows all detected issues and assists in quarantining infected files.",

	"dashboard.view.bulk_actions" => "Bulk actions",
	"dashboard.view.mass_quarantine" => "Manual mass quarantine",
	"dashboard.view.mass_quarantine.description" => "Manual mass quarantine moves all selected files into quarantine. Use with care as this can damage your websites. If you are not sure about what you are doing, please quarantine only single files.",
	"dashboard.view.quarantine" => "Move to quarantine",
	"dashboard.view.false_positive" => "False positive",
	"dashboard.view.select_issues" => "Select / Deselect all issues",
	"dashboard.view.automatic_quarantine" => "Automatically move domains to quarantine",
	"dashboard.view.automatic_quarantine.title" => "Automatically quarantine",
	"dashboard.view.automatic_quarantine.hint" => "By having an automatic cron job running in background, all new issues will be moved into quarantine. " . 
												"The cron job will run every 5 minute on your host system. With the checkbox you can specify what kind of issues you want to be quarantined.",
	
	"dashboard.view.disclaimer" => "Automatic quarantine checks for infected files and immediately moves them from their original storage location into quarantine. This prevents further abuse, but might also impair infected websites." .
								   "<br/>Therefore we <strong>DO NOT RECOMMEND TO USE THIS FEATURE IN A PRODUCTION ENVIRONMENT</strong>. Nimbusec GmbH will not be liable for any damages arising out of the use of this feature.",
	"dashboard.view.disclaimer.accept"=> " Click the checkbox to accept this disclaimer",

	"dashboard.view.issues" => "Issues",
	"dashboard.view.issues.description" => "Below you can find the found issues for all domain which are registered and scanned with Nimbusec. All these Issues are " .
										   "brought and uploaded by the Nimbusec Server Agent.",
	"dashboard.view.issues.loading" => "(Please wait, issues will be loaded ... <i class=\"fa fa-spinner fa-fw fa-spin\"></i>)",										   

	"dashboard.view.sort" => "Sort issues: ",
	"dashboard.view.sort.severity" => "by severity",
	"dashboard.view.sort.no_issues" => "by the number of issues",
	"dashboard.view.sort.no_quarantined" => "by the number of quarantined issues",
	
	"dashboard.view.apply" => "Set automatic issue quarantining",
	"dashboard.view.no_issues" => "No issues found for your domains.",
	"dashboard.view.source_code" => "View source code",

	/* for an issue */
	"dashboard.view.occured_on" => "Occurred on",
	"dashboard.view.known_since" => "Known since",
	"dashboard.view.path" => "Path",
	"dashboard.view.name" => "Name",
	"dashboard.view.md5" => "MD5",
	"dashboard.view.owner" => "Owner",
	"dashboard.view.group" => "Group",
	"dashboard.view.permission" => "Permission",

	"dashboard.controller.false_positive" => "Successfully marked %s as False Positive",
	"dashboard.controller.quarantine" => "Successfully moved %s into quarantine",
	"dashboard.controller.bulk_quarantine" => "Successfully moved your selection into quarantine",
	"dashboard.controller.automatic_quarantine.enabled" => "Successfully established automatic quarantine",
	"dashboard.controller.automatic_quarantine.disabled" => "Successfully deactivated automatic quarantine",

	"dashboard.controller.no_issues" => "No issues selected. Please select an issue in order to perform the action.",
	"dashboard.controller.invalid_schedule" => "Invalid schedule selected",

	/**************** quarantine ****************/
	"quarantine.view.title" => "Quarantine",
	"quarantine.view.description" => "Here you can view all quarantined files within a directory structure. Additionally, it allows the user to view or move the files back from quarantine.",
	"quarantine.view.no_files_found" => "<h5>No files were found in quarantine. <img style='width: 16px; height: 16px;' src='/theme/icons/16/plesk/on.png'/></h5>",

	"quarantine.controller.no_files_found" => "No files found in quarantine.",
	"quarantine.controller.invalid_store" => "Failed to retrieve quarantine Store.",
	"quarantine.controller.unquarantine" => "Move back from quarantine",
	"quarantine.controller.no_of_files" => "Number of quarantined files",
	"quarantine.controller.action" => "Action",

	"quarantine.controller.quarantined_on" => "Quarantined since",
	"quarantine.controller.old_path" => "Moved from",
	"quarantine.controller.filesize" => "File size",

	"quarantine.controller.no_files" => "No files selected. Please select a file in order to perform the action",

	"quarantine.controller.unquarantined" => "Successfully unquarantined %s",
	"quarantine.controller.bulk_unquarantined" => "Successfully unquarantined your selection",
];
