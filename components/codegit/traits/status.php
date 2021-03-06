<?php


trait Status {

	public function repoStatus() {
		$result = $this->execute("git status --branch --porcelain");

		if (!$result["status"]) {
			Common::sendJSON("error", i18n("codegit_error_statusFail")); die;
		} else {
			$result = $result["data"];
		}

		$result = $this->parseChanges($result);
		$status = "Unknown";

		if (!empty($result["added"]) ||
			!empty($result["deleted"]) ||
			!empty($result["modified"]) ||
			!empty($result["renamed"])) {
			$status = 'Uncommitted';
		} else if (!empty($result["untracked"])) {
			$status = 'Untracked';
		} else {
			$status = 'Committed';
		}

		Common::sendJSON("success", $status);
	}

	public function fileStatus($path) {
		if (!file_exists($path)) {
			Common::sendJSON("error", i18n("path_missing"));
		}

		$dirname = dirname($path);
		$filename = basename($path);
		chdir($dirname);

		$result = $this->execute("git diff --numstat " . $filename);

		if (!$result["status"]) {
			Common::sendJSON("error", i18n("codegit_error_statusFail")); die;
		} else {
			$result = $result["data"];
		}

		if (count($result) > 0) {
			$stats = explode("\t", $result[0]);
			$additions = $stats[0] ? $stats[1] : 0;
			$deletions = $stats[1] ? $stats[1] : 0;

		} else {
			$result = $this->execute("git status --branch --porcelain");

			if ($result["status"] && !empty($result["data"])) {
				$status = $this->parseChanges($result["data"]);
				if (in_array($filename, $status['untracked'])) {
					$file = file_get_contents($filename);
					$file = explode("\n", $file);
					$additions = count($file);
					$deletions = 0;
				}
			}

			$additions = 0;
			$deletions = 0;
		}
		$result = array("branch" => $this->getCurrentBranch(), "insertions" => $additions, "deletions" => $deletions);
		Common::sendJSON("success", $result);
	}

	public function branchStatus($repo) {
		$result = $this->execute("git status --branch --porcelain");
		if (!$result["status"]) {
			return false;
		}

		preg_match('/(?<=\[).+?(?=\])/', $result["data"][0], $status);

		if (!is_array($status) || empty($status)) {
			return i18n("git_status_current");
		}

		$int = (int)preg_replace("/(ahead|behind)/", "", $status[0]);
		$count = $int === 1 ? "plural" : "single";

		if (strpos($status, "ahead") !== false) {
			$status = i18n("git_status_ahead_$count", $int);
		} elseif (strpos($status, "behind") !== false) {
			$status = i18n("git_status_behind_$count", $int);
		}

		return $status;

	}

	public function loadChanges($repo) {
		$result = $this->execute("git status --branch --porcelain");
		if ($result["status"]) {
			$result = $this->parseChanges($result["data"]);
		} else {
			$result = i18n("codegit_error_statusFail");
		}
		return $result;
	}
}