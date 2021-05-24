<?php

/**
 * Really simple CSV writer
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Csv;

class Writer
{
    /**
     * The columns to write
     *
     * @var array
     */
    protected $columns = array();

    /**
     * Field delimiter for the CSV file
     *
     * @var string
     */
    protected $delimiter = ',';

    /**
     * Field enclosure for the CSV file
     *
     * @var string
     */
    protected $enclosure = '"';

    /**
     * Field escaper for the CSV file
     *
     * @var string
     */
    protected $escape = '\\';

    /**
     * A writable resource. Possibilities: a file pointer, stdin
     *
     * @var resource
     */
    protected $fp = null;


    /**
     * A dataset to write to the file pointer.
     *
     * @var array[]
     */
    protected $dataset = [];

    /**
     * Sets up the writer to write to a resource.
     *
     * @param resource $fp        A writable resource
     * @param array[]  $dataset   A dataset to write
     * @param string   $delimiter Field delimiter
     * @param string   $enclosure Field enclosing character
     * @param string   $escape    Field escape
     */
    public function __construct($fp, $dataset, $delimiter = ',', $enclosure = '"', $escape = '\\')
    {
        $this->fp        = $fp;
        $this->dataset   = $dataset;
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escape    = $escape;
    }

    /**
     * Writes this writer's dataset to the writable resource.
     */
    public function write()
    {
        $rowCount = 0;
        foreach ($this->dataset as $row) {
            // First row determines the columns
            if ($rowCount === 0) {
                $this->columns = array_keys($row);
                fputcsv($this->fp, $this->columns, $this->delimiter, $this->enclosure, $this->escape);
            }

            // Build row to output. If columns are missing, raise a notice
            $outputRow = [];
            foreach ($this->columns as $column) {
                if (array_key_exists($column, $row)) {
                    $outputRow[] = $row[$column];
                } else {
                    trigger_error(E_USER_NOTICE, sprintf('Row %d has missing column "%s"', $rowCount, $column));
                    $outputRow[] = null;
                }
                unset($row[$column]);
            }

            // Raise a notice for any leftover rows
            foreach (array_keys($row) as $excessColumn) {
                trigger_error(E_USER_NOTICE, sprintf('Row %d has excess column "%s"', $rowCount, $excessColumn));
            }

            fputcsv($this->fp, $outputRow, $this->delimiter, $this->enclosure, $this->escape);
            $rowCount++;
        }
    }
}
