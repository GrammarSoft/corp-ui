<?php

declare(strict_types=1);
require_once __DIR__.'/../_vendor/autoload.php';

class SQLiteSessions implements \SessionHandlerInterface, \SessionIdInterface, \SessionUpdateTimestampHandlerInterface {
	private $db = null;

	public function open(string $path, string $name): bool {
		$sdb = $GLOBALS['CORP_ROOT'].'/sessions.sqlite';
		if (!file_exists($sdb) || !filesize($sdb)) {
			$db = $this->db = new \TDC\PDO\SQLite($sdb);

			$db->exec("PRAGMA journal_mode = delete");
			$db->exec("PRAGMA page_size = 65536");
			$db->exec("VACUUM");

			$db->exec("PRAGMA auto_vacuum = INCREMENTAL");
			$db->exec("PRAGMA case_sensitive_like = ON");
			$db->exec("PRAGMA foreign_keys = ON");
			$db->exec("PRAGMA ignore_check_constraints = OFF");
			$db->exec("PRAGMA journal_mode = WAL");
			$db->exec("PRAGMA locking_mode = NORMAL");
			$db->exec("PRAGMA synchronous = OFF");
			$db->exec("PRAGMA threads = 4");
			$db->exec("PRAGMA trusted_schema = OFF");

			$db->exec("CREATE TABLE sessions (
				s_id TEXT NOT NULL,
				s_data BLOB NOT NULL DEFAULT '',
				s_start INTEGER NOT NULL DEFAULT 0,
				s_seen INTEGER NOT NULL DEFAULT 0,
				PRIMARY KEY (s_id)
			)");
		}
		else {
			$this->db = new \TDC\PDO\SQLite($sdb);
		}

		return true;
	}

	public function close(): bool {
		$this->db = null;
		return true;
	}

	public function read(string $id) {
		$this->db->prepexec("UPDATE sessions SET s_seen = ? WHERE s_id = ?", [time(), $id]);
		$rows = $this->db->prepexec("SELECT s_data FROM sessions WHERE s_id = ?", [$id])->fetchAll();
		return $rows ? $rows[0]['s_data'] : '';
	}

	public function write(string $id, string $data) {
		$this->db->beginTransaction();
		$this->db->prepexec("UPDATE sessions SET s_data = ?, s_seen = ? WHERE s_id = ?", [$data, time(), $id]);
		$this->db->commit();
		return true;
	}

	public function destroy(string $id): bool {
		$this->db->beginTransaction();
		$this->db->prepexec("DELETE FROM sessions WHERE s_id = ?", [$id]);
		$this->db->commit();
		return true;
	}

	public function gc($lifetime): bool {
		$this->db->beginTransaction();
		$this->db->prepexec("DELETE FROM sessions WHERE s_seen < ?", [time() - $lifetime]);
		$this->db->commit();
		return true;
	}

	public function create_sid(): string {
		$id = substr(b64_slug(random_bytes(22)), 0, 32);
		$this->db->beginTransaction();
		$this->db->prepexec("INSERT INTO sessions (s_id, s_start) VALUES (?, ?)", [$id, time()]);
		$this->db->commit();
		return $id;
	}

	public function validateId(string $id): bool {
		$rows = $this->db->prepexec("SELECT s_id FROM sessions WHERE s_id = ?", [$id])->fetchAll();
		return !empty($rows);
	}

	public function updateTimestamp(string $id, string $data): bool {
		$this->db->prepexec("UPDATE sessions SET s_seen = ? WHERE s_id = ?", [time(), $id]);
		return true;
	}

	public static function register() {
		$status = session_status();
		if ($status === PHP_SESSION_ACTIVE) {
			throw new \Exception('PHP_SESSION_ACTIVE');
		}
		else if ($status === PHP_SESSION_DISABLED) {
			throw new \Exception('PHP_SESSION_DISABLED');
		}

		$handler = new static();
		session_set_save_handler($handler, true);

		return $handler;
	}
}

function session() {
	$name = 'sess_corp';
	$opts = [
		'secure' => 1,
		'samesite' => 'Lax',
		];
	$opts_p = $opts;
	$opts_p['lifetime'] = 365*24*60*60;
	$opts_c = $opts;
	$opts_c['expires'] = time() + $opts_p['lifetime'];

	ini_set('session.use_strict_mode', '1');
	session_name($name);
	session_set_cookie_params($opts_p);

	SQLiteSessions::register();

	session_start();
	$id = session_id();

	// Refresh expiration, because of session_set_cookie_params() bug
	if (array_key_exists($name, $_COOKIE) && $_COOKIE[$name] === $id) {
		setcookie($name, $_COOKIE[$name], $opts_c);
	}
}
