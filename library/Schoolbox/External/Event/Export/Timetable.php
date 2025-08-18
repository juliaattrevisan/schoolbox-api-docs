<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event\Export;

/**
 * Export the contents of sis_event into a csv format
 */
class Timetable
{
    /**
     * CSV heading mapping
     *
     * (classification is hardcoded to CLASSIFICATION_TIMETABLE and would always
     * be the same for every row)
     */
    private const IMPORT_COLUMNS = [
        'Title' => 'title',
        'Detail' => 'detail',
        'Location' => 'location',
        'Class Code' => 'code',
        'Day Number' => 'day',
        'Period Number' => 'period',
        'Start' => 'start_at',
        'Finish' => 'finish_at',
        'All Day' => 'allday',
        'Type' => 'type',
        'GUID' => 'guid',
        'Source Name' => 'source'
    ];


    /**
     * @param \Schoolbox\External\Event\Mapper $mapper
     */
    public function __construct(private \Schoolbox\External\Event\Mapper $mapper)
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
            WHERE classification = :classification
            ORDER BY start_at
        ");

        $statement->execute([
            'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE
        ]);

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
