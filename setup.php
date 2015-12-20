<?php

@require_once('config.inc.php');
require_once('git.inc.php');

header('Content-Type: text/plain');

echo 'Checking SSH keypair... ';
if (server_has_ssh_key()) {
	echo 'done' . "\n";
} else {
	echo 'no' . "\n";
	echo 'Generating SSH keypair... ';
	if (server_generate_ssh_key()) {
		echo 'done' . "\n";
	} else {
		echo 'failed' . "\n";
	}
}

echo "\n" . 'Public SSH key is... ';
$pub_key = server_get_ssh_public_key();
if ($pub_key === false) {
	echo 'failed' . "\n";
} else {
	echo '"' . $pub_key . '"' . "\n";
}

echo "\n" . 'Checking if github.com is in known_hosts... ';
if (server_has_known_host('github.com')) {
	echo 'done' . "\n";
} else {
	echo 'no' . "\n";
	echo 'Adding github.com to known_hosts... ';
	if (server_add_known_host('github.com')) {
		echo 'done' . "\n";
	} else {
		echo 'failed' . "\n";
	}
}

echo "\n" . 'Checking configuration variables... ';
$success = true;
foreach ($config as $key => $val) {
	if ($val === 'CHANGEME') {
		if ($success) {
			echo 'Needs changing: ' . $key;
			$success = false;
		} else {
			echo ', ' . $key;
		}
	}
}
if ($success) {
	echo 'done';
}
echo "\n";

echo "\n" . 'Checking Pandoc... ';
$pandoc_ver = get_pandoc_version();
if ($pandoc_ver === false) {
	echo 'Not installed' . "\n";
} else {
	echo $pandoc_ver . "\n";
}

echo "\n" . 'Checking content dir... ';
if (check_content_dir()) {
	echo 'done' . "\n";
} else {
	echo 'failed. Make sure the webserver process can write to ' . config('content_dir') . '.' . "\n";
}

echo "\n" . 'Checking cache health... ';
if (check_cache_lru()) {
	echo 'done' . "\n";
} else {
	echo 'failed. Some files in ' . config('content_dir') . '/cache might not be removable by the webserver process.' . "\n";
}

echo "\n" . 'Checking tmp dir health... ';
if (check_tmp_dir_age()) {
	echo 'done' . "\n";
} else {
	echo 'failed. Some files in ' . config('content_dir') . '/tmp might not be removable by the webserver process.' . "\n";
}

echo "\n" . 'Checking available disk space... ';
echo disk_free_space(tmp_dir('')) . ' bytes' . "\n";

echo "\n" . 'All done.';
