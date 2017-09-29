<?php

trait Modules_NimbusecAgentIntegration_LoggingTrait
{
	public function err($msg) {
		$header = $this->getLogHeader();
		pm_Log::err("{$header} {$msg}");
	}

	public function errE($exception, $premsg = "") {
		$header = $this->getLogHeader();

		$msg = ($premsg !== "") ? "{$premsg}: {$exception->getMessage()}" : "{$exception->getMessage()}";
		pm_Log::err("{$header} {$msg}");
	}

	public function errF($format, ...$arguments) {
		$header = $this->getLogHeader();
		
		$msg = sprintf($format, ...$arguments);
		pm_Log::err("{$header} {$msg}");
	}

	private function getLogHeader() {
		$function = debug_backtrace()[2]["function"];
		$line = debug_backtrace()[2]["line"];
		$class = debug_backtrace()[2]["class"];

		return "[{$class}::{$function}:{$line}]";
	}
}