<?php

/*
$f = fopen('test.txt', 'w');
fwrite($f, '{"db": {"dbUser": "1592989144", "dbPassword": "JSQryz8x9j2", "dbName": "textnewdb", "dbHost": "localhost", "dbPort": "3306"}, "action": "dry_run", "search": "test"}' . PHP_EOL);
fwrite($f, '{"db": {"dbUser": "milica1", "dbPassword": "JSQryz8x9j2", "dbName": "textnewdb", "dbHost": "localhost", "dbPort": "3306"}, "action": "dry_run", "search": "test"}' . PHP_EOL);
fwrite($f, '{"db": {"dbUser": "milica2", "dbPassword": "JSQryz8x9j2", "dbName": "textnewdb", "dbHost": "localhost", "dbPort": "3306"}, "action": "dry_run", "search": "test"}' . PHP_EOL);
fwrite($f, '{"db": {"dbUser": "milica3", "dbPassword": "JSQryz8x9j2", "dbName": "textnewdb", "dbHost": "localhost", "dbPort": "3306"}, "action": "dry_run", "search": "test"}' . PHP_EOL);
exit();*/
/*
$handle = fopen("test.txt", "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
       var_dump(json_decode($line));
    }

    fclose($handle);
} else {
    // error opening the file.
}
exit(); */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'search_replace.php';

$arguments = $argv[1];

if (empty($arguments)) {

    throw new InsufficientArgumentError("");
}


$options = json_decode($arguments, true);

if (empty($options)) {
    throw new InsufficientArgumentError("");
}

if (empty($options['db']) || empty($options['db']['dbUser']) || empty($options['db']['dbPassword']) || empty($options['db']['dbName']) || empty($options['db']['dbHost'])) {
    throw new InsufficientArgumentError("Database credentials required");
}

if (empty($options['action'])) {
    $tables = getTables($options['db']);
    echo json_encode($tables);
    exit();
}

if (empty($options['search'])) {
    throw new InsufficientArgumentError("Search string is required");
}

if ($options['action'] === 'dry_run') {
    $stat = array('tablePointer' => 0, 'tableOffset' => 0);
    $tables = searchTables($options['db'], $options['search'], array(), 200, $stat);
    echo json_encode($tables);
    exit();
}

$json = $argv[2];
$data = json_decode($json, true);

$result = searchReplaceTables($options['db'], $options['search'], $options['replace'], $data['result']);
echo json_encode($result);


