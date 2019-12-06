<?php
/**
 * Author: Viacheslav Soroka
 * Version: 1.0.0
 * Source: https://github.com/destrofer/sql-migrate-tool
 * license: LGPL3
 */
try {
	$configurations = json_decode(file_get_contents("migrate.json"));

	foreach( $configurations as $config ) {
		echo "Looking for updates in {$config->path} ...\n";

		$pdo = new PDO($config->db->dsn, $config->db->user, $config->db->pass, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		]);
		if( !empty($config->db->init) )
			foreach( $config->db->init as $query )
				$pdo->exec($query);

		$pdo->exec("CREATE TABLE IF NOT EXISTS db_migrations (id VARCHAR(255) NOT NULL COLLATE utf8_general_ci PRIMARY KEY)");

		$files = [];
		$dir = opendir($config->path);
		if( !$dir )
			throw new Exception("Unable to open directory with SQL files");

		while( $f = readdir($dir) ) {
			if( !preg_match("#\\.sql$#isuU", $f) )
				continue;
			$files[] = $f;
		}

		closedir($dir);

		sort($files);

		$stt = $pdo->prepare("SELECT id FROM db_migrations");
		$stt->execute();

		$existing = [];
		while( $id = $stt->fetchColumn() ) {
			$existing[] = $id;
		}

		$apply = array_diff($files, $existing);

		if( empty($apply) ) {
			echo "Up to date\n";
		}
		else {
			$stt = $pdo->prepare("INSERT INTO db_migrations (id) VALUES (?)");
			foreach( $apply as $file ) {
				echo "Applying {$file}: ";
				if( !in_array("--fill-initial-list", $_SERVER["argv"]) )
					$pdo->exec(file_get_contents($file));
				$stt->execute([$file]);
				echo "OK\n";
			}
			echo "Done\n";
		}
	}
}
catch(Exception $ex) {
	echo $ex->getMessage() . "\n";
	exit(1);
}
