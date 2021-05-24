<?php

/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace DailyQuote;

class Factory
{

    /**
     * An instance of ConfigMapper
     *
     * @var ConfigMapper
     */
    private $configMapper = null;

    /**
     * An instance of QuoteMapper
     *
     * @var QuoteMapper
     */
    private $quoteMapper = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setQuoteMapper(new \DailyQuote\QuoteMapper());
        $this->setConfigMapper(new \DailyQuote\ConfigMapper());
    }

    /**
     * Spawn a quote
     *
     * @return mixed
     */
    public function produce()
    {
        $lastSelectedDate = $this->getConfigMapper()->findByColumn('name', 'last_selected_date')->first();
        $lastSelectedIndex = $this->getConfigMapper()->findByColumn('name', 'last_selected_index')->first();

        $now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

        /* Time to select a new quote and update the db
         */
        if ($lastSelectedDate->getValue() != $now->getDatabaseDate()) {
            $dailyQuote = $this->getQuoteMapper()->findRandomQuote();

            /* Remember when the quote was last selected
             */
            $lastSelectedDate->setValue($now->getDatabaseDate());
            $this->getConfigMapper()->save($lastSelectedDate);

            /* Remember the quote that was last selected
             */

            $lastSelectedIndex->setValue($dailyQuote->getId());
            $this->getConfigMapper()->save($lastSelectedIndex);
        }
        /* Reuse a previously selected quote
         */
        else {
            $dailyQuote = $this->getQuoteMapper()->findById($lastSelectedIndex->getValue());
        }

        return $dailyQuote;
    }

    /* Getters/Setters
     */

    /**
     * Set config mapper
     *
     * @param \DailyQuote\ConfigMapper $configMapper
     */
    public function setConfigMapper($configMapper)
    {
        $this->configMapper = $configMapper;
    }

    /**
     * Get config mapper
     *
     * @return \DailyQuote\ConfigMapper
     */
    public function getConfigMapper()
    {
        return $this->configMapper;
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
