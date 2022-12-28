<?php
declare(strict_types=1);

class AuthBase {
	public function lock(): void {
		foreach ($GLOBALS['-corplist'] as $corp => $data) {
			// Set to true if the corpus requires access checking
			$GLOBALS['-corplist'][$corp]['locked'] = false;
		}
	}

	public function init(): void {
		$_REQUEST['p'] = array_values(array_merge(array_values($_REQUEST['p'] ?? []), array_keys($_SESSION['passwords'] ?? [])));
	}

	public function check($corp_id): bool {
		foreach ($_REQUEST['p'] as $password) {
			/* If access is to be granted, be sure to set both:
			$_SESSION['corpora'][$corp_id] = true;
			$_SESSION['passwords'][$password] = true;
			*/
		}
		// Assume access is denied by default
		return false;
	}
}
