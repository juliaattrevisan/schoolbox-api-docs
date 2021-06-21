<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

/**
 * Class Preconditions
 *
 * Check that we fulfil a number of Preconditions before we jump in and start importing.
 *
 * 1. There has to be at least 1 profile xml and at least 10 student timetable xmls
 * 2. 30 minutes must have elapsed from when a file was last received. We need to check
 *    that because we can't tell when Kamar will stop sending data, so we start importing
 *    when we cease receiving data
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint
 */
class Preconditions
{

    /**
     * Require at least one profile xml
     */
    private const MIN_PROFILE_XML = 1;


    /**
     * Require at least ten profile xml
     */
    private const MIN_TIMETABLE_XML = 10;


    /**
     * Array key
     */
    private const ANALYSIS_XML = 'xml';


    /**
     * Array key
     */
    private const ANALYSIS_LASTARRIVAL = 'lastArrival';


    /**
     * If nothing arrived within these many seconds, good to go for importing
     */
    private const ARRIVAL_THRESHOLD = 0;


    /**
     * The place where kamar files end up
     *
     * @var string
     */
    private string $host;


    /**
     * Preconditions constructor.
     *
     * @param string $host
     */
    public function __construct(string $host)
    {
        $this->host = $host;
    }


    /**
     * Perform an analysis on the Receiver's contents
     *
     * @return array
     */
    private function analyse(): array
    {
        /* Profile and student timetable xmls come with a datetime suffix
         *
         * Timetable Composite requires access across multiple xml files cross-referencing nodes
         * in profile xml so you can identify who is in a class ($container)
         */
        $analysis = [
            self::ANALYSIS_XML => [
                Receiver::XML_FULL => [],
                Receiver::XML_STUDENTTIMETABLES => []
            ],

            /* Most recent arrival time of an xml file
             */
            self::ANALYSIS_LASTARRIVAL => null
        ];

        /* Make a stack of profile xmls (full_ ... .xml)
         * Make a stack of student timetable xmls (studenttimetables_ ... .xml)
         */
        foreach (glob($this->host . '/*.xml') as $filepath) {
            $xmlFile = basename($filepath);

            if (strpos($xmlFile, Receiver::XML_FULL) !== false) {
                $analysis[self::ANALYSIS_XML][Receiver::XML_FULL][] = $xmlFile;
            } elseif (strpos($xmlFile, Receiver::XML_STUDENTTIMETABLES) !== false) {
                $analysis[self::ANALYSIS_XML][Receiver::XML_STUDENTTIMETABLES][] = $xmlFile;
            }

            /* partial xmls could still be arriving, so we need to include their arrival
             * times as part of our analysis. A profile or studenttimetable xml might arrive
             * after these partials
             */
            $lastArrival = filemtime($filepath);

            if ((null === $analysis[self::ANALYSIS_LASTARRIVAL]) || ($analysis[self::ANALYSIS_LASTARRIVAL] < $lastArrival)) {
                $analysis[self::ANALYSIS_LASTARRIVAL] = $lastArrival;
            }

            /* noop
             *
             * Ignore all partial xmls which kamar sends at us
             * These are micro-updates that are sent whenever people use the kamar system
             * We don't need to use them (yet)
             */
        }

        return $analysis;
    }


    /**
     * Return a set of xml files if all the preconditions pass
     */
    public function getXmlFiles(): array
    {

        /* Examine the Receiver's data at this moment
         */
        $analysis = $this->analyse();

        /* Get the xml files that exist and check minimum quantities are present
         */
        $xml = $analysis[self::ANALYSIS_XML];
        $lastArrival = $analysis[self::ANALYSIS_LASTARRIVAL];

        $minProfileXml = \count($xml[Receiver::XML_FULL]) >= self::MIN_PROFILE_XML;
        $minTimetableXml = \count($xml[Receiver::XML_STUDENTTIMETABLES]) >= self::MIN_TIMETABLE_XML;

        /* Now check if we haven't received files recently
         * If we didn't receive any files in the last ARRIVAL_THRESHOLD seconds, go import
         */
        if ($minProfileXml && $minTimetableXml && $lastArrival && $lastArrival < (time() - self::ARRIVAL_THRESHOLD)) {
            return $analysis[self::ANALYSIS_XML];
        }

        return [];
    }
}
