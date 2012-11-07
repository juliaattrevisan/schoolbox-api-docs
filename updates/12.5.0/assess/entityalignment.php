<?php
/**
 * This script iterates across fields that may or may not have htmlencode()'d text in them and realignes them all to
 * be unencoded.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once dirname(__FILE__) . '/../../../include/Bootstrap.php';
$db = Database::getInstance();

// collect all the tables in the target db because as far as I'm concerned, hardcoding tables, keys and fields
// is not particularly extensible
$stmt = $db->prepare("
    SELECT table_name, column_name FROM (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE data_type IN ('text', 'varchar')
        AND table_name LIKE :filter
        AND table_schema = :dbName
    ) dt
");

$stmt->execute(array(
    'dbName' => SQL_db,
    'filter' => 'assess%'
));

// retrieve the table and field listing, separating out the primary key field so it doesn't get modified
$tables = array();
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // this assumes that the tables have a single primary key (which, for assess*, they all do)
    // if you want to expand this script to affect other tables, this logic may need revisitation
    if(!isset($tables[$row['table_name']]['id'])) {
        $idstmt = $db->query("
            SHOW COLUMNS FROM {$row['table_name']}
            WHERE `Key` = 'PRI'
        ");
        $tables[$row['table_name']]['id'] = $idstmt->fetchColumn(0);
    }
    $tables[$row['table_name']]['fields'][] = $row['column_name'];
}

// iterate across the tables and update data only if required
foreach($tables as $table => $data) {
    $tblID = $data['id'];

    $fieldList = implode('`, `', $data['fields']);

    // retrieve the existing data
    $stmt = $db->query("SELECT `$tblID`, `$fieldList` FROM `$table`");
    while(($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $id = $row[$tblID];
        unset($row[$tblID]);
        $updates = array();

        // decode the individual fields, and update if the decoded version doesn't match the DB
        foreach($row as $key => &$field) {
            if(trim($field)) {
                $decoded = html_entity_decode($field);
                if($decoded != $field) {
                    $updates[] = "`$key` = '$decoded'";
                }
            }
        }
        $updateString = implode(', ', $updates);

        // if there are fields to update, run the query (we use MyISAM, so attempting transaction support is pointless)
        if($updateString) {
            $query = "UPDATE `$table` SET $updateString WHERE `$tblID` = '$id';";
            $db->query($query);

            // ...and output to terminal for good measure
            echo ' > ', $query, "\n";
        }
    }
}