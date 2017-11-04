<?php

require __DIR__ . '/vendor/autoload.php';
/**
 * Your merchant key from Gainkit.
 */
const GAINKIT_API_KEY = '';

$config = [
	'api_key' => GAINKIT_API_KEY,
	'dbconnection' => 'mysql:host=127.0.0.1;dbname=gainkit_import_test',
	'dblogin' => 'login',
	'dbpassword' => 'password',
];

$gki = new \Import\GainkitDotaImport($config);
$gki->start();

$gki = new \Import\GainkitCsgoImport($config);
$gki->start();