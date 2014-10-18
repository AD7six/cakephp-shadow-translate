<?php
function find_root($root) {
	do {
		$lastRoot = $root;
		$root = dirname($root);
		if (is_dir($root . '/vendor/cakephp/cakephp')) {
			return $root;
		}
	} while($root !== $lastRoot);

	throw new Exception("Cannot find the root of the application, unable to run tests");
}

$root = find_root(__FILE__);

chdir($root);
require $root . '/config/bootstrap.php';
