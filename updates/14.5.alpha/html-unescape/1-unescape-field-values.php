<?php
/**
 * Script for unescaping HTML-escaped field values from list tables.
 *
 * It performs the following actions:
 * * Fetches the description of the table, and loads all char or varchar fields
 * * Iterates through all table rows, updating all char or varchars to unescaped
 *   values
 */

require_once __DIR__ . '/../../../include/Bootstrap.php';

$database = Database::getInstance();

// Torn directly from adminv2/list
$tableList = array(
    array(
        'name'  => 'groups',
        'index' => 'id',
    ),
    array(
        'name'  => 'house',
        'index' => 'id',
    ),
    array(
        'name'  => 'luResidentialHouse',
        'index' => 'id',
    ),
    array(
        'name'  => 'luCampus',
        'index' => 'id',
    ),
    array(
        'name'  => 'luEventType',
        'index' => 'eventTypeID',
    ),
    array(
        'name'  => 'newsTopic',
        'index' => 'newsTopicID',
    ),
    array(
        'name'  => 'submission_grade',
        'index' => 'grade_id',
    ),
    array(
        'name'  => 'component_definition',
        'index' => 'id',
    ),
    array(
        'name' => 'lu_timetable_colour',
        'index' => 'timetable_colour_id'
    ),
    array(
        'name'  => 'submission_mark_type',
        'index' => 'mark_type_id',
    ),
);

foreach ($tableList as $table) {

    // Build the update query from the description of the table
    $escapedTable = $database->quoteIdentifier($table['name']);
    $description  = $database->query("DESCRIBE $escapedTable");
    $selectQuery  = "SELECT * FROM %s";
    $updateQuery  = "UPDATE %s SET %s WHERE %s = :index";

    // Load the list of columns to update.
    // Only unescape non-index and char|varchar fields.
    $updateColumns = array();
    foreach ($description as $tableColumn) {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);
        if ($tableColumn['field'] != $table['index']
            && preg_match('/^(?:char|varchar)/',$tableColumn['type'])
        ) {
            $updateColumns[] = $tableColumn['field'];
        }
    }

    $setParts = array();
    foreach ($updateColumns as $column) {
        $setParts[] = sprintf(
            '%s = :%s',
            $database->quoteIdentifier($column),
            $column
        );
    }

    $updateQuery = $database->prepare(
        sprintf(
            $updateQuery,
            $escapedTable,
            implode(', ', $setParts),
            $database->quoteIdentifier($table['index'])
        )
    );

    // Update each row in the table
    foreach ($database->query(sprintf($selectQuery, $escapedTable)) as $row) {
        $params = array(
            'index' => $row[$table['index']],
        );

        // Unescape all the update columns
        foreach ($updateColumns as $column) {
            $params[$column] = html_entity_decode($row[$column], ENT_IGNORE|ENT_QUOTES, "UTF-8");
        }
        $updateQuery->execute($params);
    }
}
