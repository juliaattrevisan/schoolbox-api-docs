<?php

/**
 * Importer
 *
 * Imports each line of a csv (quote text, quote author) into a db table
 * from which stuff is randomly selected
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace DailyQuote;

class Importer
{

    /**
     * Path to the csv containing quotes to import
     *
     * @var string
     */
    private $inputFile = '';

    /**
     * An instance of QuoteMapper
     * @var QuoteMapper
     */
    private $quoteMapper = null;

    /**
     * Constructor
     */
    public function __construct($inputFile)
    {
        $this->setInputFile($inputFile);
        $this->setQuoteMapper(new \DailyQuote\QuoteMapper());
    }

    /**
     * Import csv data
     */
    public function import()
    {
        $inputFile = fopen($this->getInputFile(), 'r');

        while ($row = fgetcsv($inputFile, 4096)) {
            $quote = new \DailyQuote\Quote();
            $quote->setDescription(trim($row[0]));
            $quote->setAuthor(trim($row[1]));
            $quote->setCreatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
            $quote->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
            $quote->setDeletedAt(null);

            $this->getQuoteMapper()->save($quote);
        }
    }

    /* Getters/Setters
     */

    /**
     * Set input file
     *
     * @param string $inputFile
     */
    public function setInputFile($inputFile)
    {
        $this->inputFile = $inputFile;
    }

    /**
     * Get input file
     *
     * @return string
     */
    public function getInputFile()
    {
        return $this->inputFile;
    }

    /**
     * Set quote mapper
     *
     * @param \DailyQuote\QuoteMapper $quoteMapper
     */
    public function setQuoteMapper($quoteMapper)
    {
        $this->quoteMapper = $quoteMapper;
    }

    /**
     * Get quote mapper
     *
     * @return \DailyQuote\QuoteMapper
     */
    public function getQuoteMapper()
    {
        return $this->quoteMapper;
    }
}
