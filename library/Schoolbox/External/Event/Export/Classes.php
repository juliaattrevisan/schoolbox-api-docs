<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event\Export;

/**
 * Export the contents of sis_class into a csv format
 */
class Classes
{
    /**
     * CSV heading mapping
     */
    private const IMPORT_COLUMNS = [
        'Name' => 'name',
        'Class Code' => 'code',
    ];


    /**
     * @param \Schoolbox\External\ClassEntryMapper $mapper
     */
    public function __construct(private \Schoolbox\External\ClassEntryMapper $mapper)
    {
    }


    /**
     * @return string
     * @throws \PDOException
     */
    public function export(): string
    {
        /* Header row
         */
        $headings = array_keys(self::IMPORT_COLUMNS);

        /* Each csv row follows this template
         */
        $template = array_fill_keys($headings, '');

        /* Header row
         */
        $fp = fopen('php://temp', 'w');
        fputcsv($fp, $headings, escape: '');

        /* SUDS Timetable records
         */
        $statement = $this->mapper->getDatabase()->prepare("
            SELECT *
            FROM {$this->mapper->getTable()}
            ORDER BY name
        ");

        $statement->execute();

        while ($timetableEvent = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $row = $template;

            foreach (self::IMPORT_COLUMNS as $heading => $column) {
                $row[$heading] = $timetableEvent[$column];
            }

            fputcsv($fp, array_values($row), escape: '');
        }

        rewind($fp);

        $contents = stream_get_contents($fp);

        fclose($fp);

        return $contents;
    }
}
