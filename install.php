<?php

define('APP_BASE', __DIR__ . '/');

require_once APP_BASE . 'lib/kadro/common.inc.php';

use HexMakina\LocalFS\Text\TextFile;
use HexMakina\LocalFS\FileSystem;
use HexMakina\Crudites\{Crudites,Database};
use HexMakina\kadro\Auth\{Operator, Permission, ACL};
use HexMakina\kadro\Controllers\{TradukoController};

require_once 'configs/database.php';

$database = new Database(
    Crudites::connect($db_props['CONTENT']),
    Crudites::connect($db_props['SCHEMA'], $db_props['SCHEMA']['name'])
);

Crudites::setDatabase($database);


// init database
$database_files = [
  'structure' => [
    'database_auth',
    'database_i18n'
  ],
  'data' => [
    'datadump_i18n_codes',
    'datadump_i18n_labels'
  ]
];

$conx = $database->contentConnection();

echo '<pre>';
foreach ($database_files as $type => $filenames) {
    echo(PHP_EOL . "LOADING DATABASE $type");

    foreach ($filenames as $filename) {
        echo(PHP_EOL . "EXECUTING $filename.sql");
        $sql_dump = new TextFile(APP_BASE . 'lib/kadro/BaseData/' . $filename . '.sql');
        $res = $conx->exec("$sql_dump");
        if ($res === false) {
            throw new \Exception("EXECUTING $filename.sql FAILED");
        }
    }
}

echo(PHP_EOL . "CREATING ROOT USER");
$root_props = ['username' => 'koral', 'name' => 'Koral'];
$conx->exec("INSERT INTO `kadro_operator` (`id`, `username`, `name`, `active`) VALUES (1, 'koral','Koral', 1);");

$operator = Operator::one(1);
$operator_id = $operator->get_id();

echo(PHP_EOL . "CREATING ROOT PASSWORD");
$operator->password_change($operator->username());
$operator->save($operator_id);

echo(PHP_EOL . 'CREATING ROOT PERMISSION');
$permission = new Permission();
$permission->set('name', 'root');
$permission->save($operator_id);
// vd($permission);

echo(PHP_EOL . 'CREATING ROOT ACCESS');
$acl = ACL::allow_in($operator, $permission);
// vd($acl);


echo(PHP_EOL . 'CREATING TRANSLATION FILES');
$languages = TradukoController::init($box->get('settings.locale_data_path'));
echo(PHP_EOL . "LANGUAGES: " . implode(', ', $languages));


echo(PHP_EOL . 'CREATING .htaccess');
if (!FileSystem::copy('.htaccess', '../.htaccess')) {
    echo(PHP_EOL . 'FAILURE CREATING .htaccess');
}

echo(PHP_EOL . 'CREATING index.php');
if (!FileSystem::copy('app_index.php', '../index.php')) {
    echo(PHP_EOL . 'FAILURE CREATING index.php');
}

echo(PHP_EOL . 'CREATING configs FOLDER');
if (exec('cp configs ../configs') === false) {
    echo(PHP_EOL . 'FAILURE CREATING configs');
}

echo(PHP_EOL . 'INSTALLATION FINISHED');

echo '</pre>';

die('here');
