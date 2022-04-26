<?php

/**
 * Really simple CSV reader
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Csv;

class Reader implements \Iterator
{

    /**
     * The column list for this reader. Each row returned will be mapped to its
     * corresponding position in this list.
     *
     * @var array|bool
     */
    protected $column_list = [];


    /**
     * The column list expected for this reader.
     * If not set, it'll simply be ignored. If set, case insensitive search of the csv column names
     * will be performed against this array.
     * @var array
     */
    protected $expected_column_list = [];

    /**
     * Is the column list loaded from the first row of the CSV file?
     *
     * @var bool
     */
    protected $load_from_first = false;

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
     * Are empty cells filled with the first non-empty value above them?
     *
     * @var bool
     */
    protected $fill_down = false;

    protected $fp           = null;
    protected $current_key  = 0;
    protected $current_row  = [];
    protected $previous_row = [];

    /**
     * Sets up the reader to read from the current file name.
     *
     * @param string|resource $file
     *
     * @throws \RuntimeException|\Schoolbox\Resource\Exception if file isn't found or readable
     */
    public function __construct($file)
    {
        if (\is_resource($file)) {
            /* Check if it's a fopen'd resource
             */
            $this->fp = $file;
        } elseif (is_readable($file)) {
            /* Otherwise maybe it's a path to a filename
             */
            $this->fp = fopen($file, 'r');
        } else {
            throw new \RuntimeException('File not found, or is not readable');
        }

        // Initialise the pointer position
        $this->fp = (new ValidCSV($this->fp))->skipBom();
    }

    /**
     * Cleans up after itself when destroyed
     */
    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        $parsed_row  = [];
        $column_list = $this->getColumnList();
        if (empty($column_list)) {
            $parsed_row = $this->current_row;
        } else {
            foreach ($column_list as $i => $column) {
                if (isset($column)) {
                    $parsed_row[$column] = isset($this->current_row[$i]) ? $this->current_row[$i] : null;
                }
            }
        }

        // Fill down
        if ($this->getFillDown()) {
            foreach ($parsed_row as $i => $value) {
                if (strlen($value) === 0 && isset($this->previous_row[$i])) {
                    $parsed_row[$i] = $this->previous_row[$i];
                }
            }
        }

        $this->previous_row = $parsed_row;
        return $parsed_row;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->current_key++;
        $this->current_row = $this->loadRow();
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->current_key;
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return $this->current_row !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->current_key = 0;
        $this->current_row = [];
        // skipBom rewinds to a proper position
        $this->fp = (new ValidCSV($this->fp))->skipBom();

        // Am I loading the column list from the first column?
        // If so, load that and set it as the column list.
        if ($this->getLoadFromFirst()) {
            $this->setColumnList($this->loadRow());
        }
        $this->next();
    }

    /**
     * Clean up
     * @return void
     */
    protected function cleanup()
    {
        if (is_resource($this->fp)) {
            fclose($this->fp);
            unset($this->fp);
        }
        $this->current_key = 0;
        $this->current_row = [];
    }

    /**
     * Loads the next row from the CSV file.
     *
     * @return array|false
     */
    protected function loadRow()
    {
        return fgetcsv($this->fp, 0, $this->getDelimiter(), $this->getEnclosure(), $this->getEscape());
    }


    /**
     * @param array $column_list
     * @return $this
     */
    public function setColumnList($column_list)
    {
        if (empty($this->getExpectedColumnList())) {
            $this->column_list = $column_list;
            return $this;
        }

        // Check importing column name against expected column names.
        // This is not to exclude existence of the importing field,
        // but to search the same name with different case
        foreach ($column_list as $importedColumn) {
            foreach ($this->getExpectedColumnList() as $expectedColumn) {
                if ($importedColumn === $expectedColumn) {
                    break;
                }
                if (strcasecmp($importedColumn, $expectedColumn) === 0) {
                    $importedColumn = $expectedColumn;
                    break;
                }
            }
            $this->column_list[] = $importedColumn;
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getColumnList()
    {
        return $this->column_list;
    }

    /**
     * @param array $expected_column_list
     * @return $this
     */
    public function setExpectedColumnList($expected_column_list)
    {
        $this->expected_column_list = $expected_column_list;
        return $this;
    }

    /**
     * @return array
     */
    public function getExpectedColumnList()
    {
        return $this->expected_column_list;
    }

    /**
     * @param bool $load_from_first
     * @return $this
     */
    public function setLoadFromFirst($load_from_first)
    {
        $this->load_from_first = $load_from_first;
        return $this;
    }

    /**
     * @return bool
     */
    public function getLoadFromFirst()
    {
        return $this->load_from_first;
    }

    /**
     * @param string $delimiter
     * @return $this
     */
    public function setDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * @return string
     */
    public function getDelimiter()
    {
        return $this->delimiter;
    }

    /**
     * @param string $enclosure
     * @return $this
     */
    public function setEnclosure($enclosure)
    {
        $this->enclosure = $enclosure;
        return $this;
    }

    /**
     * @return string
     */
    public function getEnclosure()
    {
        return $this->enclosure;
    }

    /**
     * @param string $escape
     * @return $this
     */
    public function setEscape($escape)
    {
        $this->escape = $escape;
        return $this;
    }

    /**
     * @return string
     */
    public function getEscape()
    {
        return $this->escape;
    }

    /**
     * @return bool
     */
    public function getFillDown()
    {
        return $this->fill_down;
    }

    /**
     * @param bool $fill_down
     * @return $this
     */
    public function setFillDown($fill_down)
    {
        $this->fill_down = $fill_down;
        return $this;
    }
}
