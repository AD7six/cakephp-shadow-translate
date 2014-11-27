<?php

use Cake\Core\Plugin;
use Cake\Log\Log;

/**
 * Test suite bootstrap for ShadowTranslate
 *
 * This function is used to find the location of CakePHP whether CakePHP
 * has been installed as a dependency of the plugin, or the plugin is itself
 * installed as a dependency of an application.
 */
$findRoot = function ($root) {
	do {
		$lastRoot = $root;
		$root = dirname($root);
		if (is_dir($root . '/vendor/cakephp/cakephp')) {
			return $root;
		}
	} while ($root !== $lastRoot);

	throw new Exception("Cannot find the root of the application, unable to run tests");
};
$root = $findRoot(__FILE__);
unset($findRoot);

chdir($root);
if (file_exists($root . '/config/bootstrap.php')) {
	require $root . '/config/bootstrap.php';
	return;
}

putenv('db_dsn=sqlite:///:memory:?log=1');
require $root . '/vendor/cakephp/cakephp/tests/bootstrap.php';
$loader->addNamespace('Cake\Test', './vendor/cakephp/cakephp/tests');

Plugin::load('ShadowTranslate', [
	'path' => dirname(dirname(__FILE__)) . DS,
	'autoload' => true
]);

Log::config('queries', [
	'className' => 'Console',
	'stream' => 'php://stderr',
	'scopes' => ['queriesLog']
]);
