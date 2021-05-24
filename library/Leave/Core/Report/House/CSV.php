<?php

/**
 * CSV Export method for the House Report
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Leave_Core_Report_House_CSV extends Leave_Report_House_RendererBase
{

    /**
     * @var resource $stream
     */
    private $stream;


    /**
     * @inheritdoc
     */
    public function __construct(SitePage $page, array $data)
    {
        parent::__construct($page, $data);
        $this->getPage()->setAjax(true);
    }


    /**
     * Render it
     */
    public function render()
    {
        header('Content-type: text/csv');
        header('Cache-Control: no-store, no-cache');
        header('Content-Disposition: attachment; filename="House Report.csv"');
        $this->stream = fopen('php://output', 'wb');
        $this->renderHeadings();
        $this->renderSubheadings();
        $this->renderRequests();
        fclose($this->stream);
    }


    /**
     * For each day that the person is on leave within the prescribed period of time, show an 'L' char.
     * If they are starting or ending their leave on this day, show the time instead.
     *
     * Render 3 columns each per day. Skip morning of first day if viewing multiple days.
     *
     * Morning: 00:00:00 to 09:59:59
     * Day:     10:00:00 to 18:59:59
     * Night:   19:00:00 to 21:59:59
     */
    protected function renderHeadings()
    {
        $fieldList = ['Name', 'Year', 'Boarding Status'];

        $headerDate = new Alaress_Date_Format($this->getReportStart()->getUnixTimeStamp(), Alaress_Date_Format::TIMESTAMP);

        for ($j = 0; $j < $this->getRange(); $j++, $headerDate->addDays(1)) {
            $fieldList[] = $headerDate->getLongDateOnly();
            $fieldList[] = '';

            // Don't render AM column if first of multiple days
            if ($j || 1 === $this->getRange()) {
                $fieldList[] = '';
            }
        }

        $fieldList[] = 'Authorised By';

        // Stream it
        fputcsv($this->stream, $fieldList);
    }


    /**
     * Render subheadings
     */
    protected function renderSubheadings()
    {
        $fieldList = ['', '', ''];

        // Skip AM for Friday if not single-day view
        for ($j = 0; $j < $this->getRange(); $j++) {
            if ($j || 1 === $this->getRange()) {
                $fieldList[] = 'AM';
            }

            $fieldList[] = 'PM';
            $fieldList[] = 'Bed';
        }

        // Stream it!
        fputcsv($this->stream, $fieldList);
    }


    /**
     * Render it!
     */
    protected function renderRequests()
    {
        if (count($this->getRequestList())) {
            $allPeople = $this->getCombined();

            if (is_array($allPeople) && count($allPeople)) {
                foreach ($allPeople as $userId => $currentPerson) {
                    /* A user may have multiple, potentially overlapping requests, for the report period.
                     * If so, we print each request on its own row, we do not aggregate as some fields are
                     * should not be aggregated, eg 'host', etc
                     */
                    $countRequests = count($currentPerson['request_id']);
                    /** @noinspection ForeachInvariantsInspection */
                    for ($i = 0; $i < $countRequests; $i++) {
                        $fieldList = [];

                        // Personal details (but only for first iteration)
                        $fieldList[] = $i ? '' : $currentPerson['user_name'];
                        $fieldList[] = $i ? '' : $currentPerson['user_year'];
                        $fieldList[] = $i ? '' : $currentPerson['user_boarding_status'];

                        // Periods
                        foreach ($currentPerson['periods'] as $j => $period) {
                            $currentPeriod = $period['request_period'];

                            // Don't print AM column for first day, unless only showing a single day

                            if ($j || 1 === $this->getRange()) {
                                $fieldList[] = $currentPeriod['morning'][$i]['display'];
                            }

                            $fieldList[] = $currentPeriod['day'][$i]['display'];
                            $fieldList[] = $currentPeriod['night'][$i]['display'];
                        }

                        // Authorisation
                        if (isset($currentPerson['request_authorisation_user_id'])) {
                            $fieldList[] = implode(' / ', $currentPerson['request_authorisation_user_id']);
                        } else {
                            $fieldList[] = '';
                        }

                        // Stream it
                        fputcsv($this->stream, $fieldList);
                    }
                }
            }
        }
    }
}
