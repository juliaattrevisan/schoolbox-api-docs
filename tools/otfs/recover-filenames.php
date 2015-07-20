<?php
/**
 * This script will generate MySQL that, once run, will restore blank filenames from the specified backup database
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if ($argc != 2) {
    die ("Usage: php {$argv[0]} {backup_database_name}\n");
}

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$k = new Schoolbox\Application\Daemon\Kernel('File Recovery', [
    'verbose' => [1,1,1]
]);

// database name: you get alphanum and underscore, nothing else
$backupDatabase = preg_replace('`[^a-z0-9_]`i', '', trim($argv[1]));

if ($backupDatabase != $argv[1]) {
    throw new Exception("Invalid database name: '{$argv[1]}'");
}

// validate bkp db: will failhard if db is inaccessible / absent
$db = Database::getInstance();

$target = SQL_db;

$db->exec("Use $backupDatabase;");

// all good? all good.


// list of tables to test
$tables = [
    'storage'            => [ 'storage_id', 'filename', 'hash' ],
    'file'               => [ 'id', 'realname', 'description' ],
    'myFile'             => [ 'id', 'realname', 'description' ],
    'student_submission' => [ 'id', 'realname', 'hash' ],
    'submission_return'  => [ 'id', 'filename', null ],
    'dropbox'            => [ 'id', 'realname', 'description' ]
];

foreach ($tables as $table => $fields) {

    list ($idField, $nameField, $titleField) = $fields;

    if ($titleField) {
        $coalesce = "COALESCE(NULLIF(bkp_f.$nameField, ''), bkp_f.$titleField)";
    } else {
        $coalesce = "bkp_f.$nameField";
    }

    $stmt = $db->prepare("
        SELECT bkp_f.$idField, $coalesce `name`
        FROM $backupDatabase.$table bkp_f
        WHERE bkp_f.$idField IN (
          SELECT live_f.$idField
          FROM $target.$table live_f
          WHERE live_f.$nameField = ''
        )
        AND $coalesce != '';
    ");

    $k->logger()->debug(trim(preg_replace('`\s+`', ' ', $stmt->queryString)));

    $stmt->execute();

    $i = 0;

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        fprintf(STDERR, "UPDATE `$target`.`$table` SET `$nameField` = %s WHERE `id` = %s;\n",
            $db->quote($r['name']),
            $db->quote($r['id'], PDO::PARAM_INT)
        );

        ++$i;
    }

    $k->logger()->info("$i records to be modified", [ 'char' => '+' ]);

}