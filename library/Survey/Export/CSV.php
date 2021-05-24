<?php

/**
 * Survey_Export_CSV
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Survey_Export_CSV extends Evaluation_Export_CSV
{

    /**
     * @var array
     */
    protected $additionalColumns = array();


    /**
     * Hook method
     *
     * @return mixed|void
     */
    protected function setup()
    {
    }


    /**
     * Filter out anonymous results
     *
     * Normally should be done in a more meaningful way at collation
     * but because it's shared with assess, we don't.
     *
     * @param array $survey
     */
    public function adjustAnonymousResult($survey)
    {
        if ($survey['anonymous_result']) {
            $this->adjustHeadings();
            $this->adjustResponses();
        }
    }


    /**
     * Generic adjust row for anonymity method - chops off first 3 (4 if
     * external ID inluded) fields from row.
     *
     * Chops the following fields:
     * * External ID
     * * User ID
     * * Last Name
     * * First Name
     *
     * @param array $row
     * @return array
     */
    protected function adjustRowForAnonymity($row)
    {
        return array_slice($row, $this->getIncludeExternalId() ? 4 : 3);
    }


    /**
     * Adjust headings for Anonymity
     */
    private function adjustHeadings()
    {
        $this->setHeadings($this->adjustRowForAnonymity($this->getHeadings()));
    }


    /**
     * Adjust responses for Anonymity
     */
    private function adjustResponses()
    {
        $collection = array();
        foreach ($this->getResponses() as $currentResponse) {
            $collection[] = $this->adjustRowForAnonymity($currentResponse);
        }
        $this->setResponses($collection);
    }
}
