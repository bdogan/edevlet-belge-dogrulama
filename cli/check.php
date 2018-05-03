<?php

require '../vendor/autoload.php';

// Get object
$eDevlet = new \EDevlet\Dogrula();
// $eDevlet->verbose = true;

// Output version
echo sprintf('E-Devlet Belge Doğrulama %s' . "\r\n", $eDevlet->version);
echo 'Usage: php check.php [kimlikno] [filename]' . "\r\n";

// Get arguments
$args = array_values(array_diff_key($argv, Array(basename(__FILE__) )));

// Check arguments
if (count($args) < 2) die('Wrong arguments passed!'. "\r\n");

// Get file
$file = $args[1];
if (!is_file($file) || !file_exists($file)) die('file not found!' . "\r\n");
echo sprintf('Belge: %s' . "\r\n", $file);

$kimlikNo = $args[0];
if (!is_string($kimlikNo) || empty($kimlikNo)) die('Kimlik no not found!' . "\r\n");
echo sprintf('Kimlik No: %s' . "\r\n", $kimlikNo);

// Validate
echo sprintf('Doğrulanıyor... ');
$result = $eDevlet->dogrula($kimlikNo, $file);
echo sprintf('%s' . "\r\n", $result === true ? 'Geçerli' : ( $result === false ? 'Geçersiz' : 'Bağlantı problemi lütfen tekrar deneyin'));
