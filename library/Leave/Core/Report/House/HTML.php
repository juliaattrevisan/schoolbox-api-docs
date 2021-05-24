<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */


/**
 * Provides common behaviour for HTML-based rendering (Print or Browser)
 */
abstract class Leave_Core_Report_House_HTML extends Leave_Report_House_RendererBase
{

    /**
     * @var string $templateFile
     */
    protected $templateFile = 'modules/leave/report/house/web.html';

    /* Decorations to use on the little L's etc.
     */
    protected $decoration = array(
        'pendingStart'  => "<span class='leave-pending'>",
        'pendingEnd'    => "</span><br />",
        'approvedStart' => "<span class='leave-approved'>",
        'approvedEnd'   => "</span><br />",
    );

    protected $rangePeriods = array('morning', 'day', 'night');


    /**
     * @inheritdoc
     */
    public function __construct(SitePage $page, array $data)
    {
        parent::__construct($page, $data);
        $this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
    }


    /**
     * Facade method
     * Render house report
     */
    public function render()
    {
        $this->renderHouseList();
        $this->renderMetaDetails();
        $this->renderTimespanOptions();
        $this->renderTableHeaders();
        $this->renderTableRowsAndFooter();
    }

    /**
     * Render extra information about the report
     */
    protected function renderMetaDetails()
    {
        $currentTime = new Alaress_Date_Format(time(), 'U');

        $countDataColumns = 1 === $this->getRange() ? 3 : 3 * $this->getRange() - 1;

        $this->getPage()->setVar(
            array(
                'HOUSE_NAME'                => Scrub::text($this->getCurrentHouse()->getHouse()),
                'HOUSE_ID'                  => $this->getCurrentHouse()->getId(),
                'GENERATED_AT'              => $currentTime->getShort(),
                'REPORT_DATE_START'         => $this->getReportStartDate(),
                'REPORT_DATE_END'           => $this->getReportEndDate(),
                'CLASS_REPORT_DATE_END'     => Leave_Controller::REPORT_TIMESPAN_RANGE === $this->getReportTimespan() ? '' : 'hide',
                'DATE_DISPLAY_START'        => $this->getReportStart()->getUsingFormat('d-m-Y'),
                'DATE_DISPLAY_END'          => $this->getReportFinish()->getUsingFormat('d-m-Y'),
                'COUNT_DATA_COLUMNS'        => $countDataColumns,
            )
        );
    }

    /**
     * Render the house list dropdown selector
     */
    protected function renderHouseList()
    {
        $this->getPage()->setBlock(TEMPLATE_content, 'house', 'house_block');

        if (count($this->getHouseList())) {
            foreach ($this->getHouseList() as $house) {
                $this->getPage()->setVar(
                    array(
                        'HOUSE_LIST_ID'   => $house->getId(),
                        'HOUSE_LIST_NAME' => Scrub::text($house->getHouse())
                    )
                );

                if ($house->getId() == $this->getCurrentHouse()->getId()) {
                    $this->getPage()->setVar('HOUSE_LIST_SELECTED', 'selected="selected"');
                } else {
                    $this->getPage()->setVar('HOUSE_LIST_SELECTED', '');
                }

                $this->getPage()->parse('house_block', 'house', true);
            }
        }
    }

    /**
     * Render the options in the Report Period select
     */
    protected function renderTimespanOptions()
    {
        $this->getPage()->setBlock(TEMPLATE_content, 'report_timespan', 'report_timespan_block');

        foreach (Leave_Controller::getTimespanOptions() as $timespanOption) {
            $optionSelected = $timespanOption === $this->getReportTimespan();

            switch ($timespanOption) {
                case Leave_Controller::REPORT_TIMESPAN_DAY:
                    $timespanLabel = 'Single Day';
                    break;
                case Leave_Controller::REPORT_TIMESPAN_RANGE:
                    $timespanLabel = 'Date Range';
                    break;
                case Leave_Controller::REPORT_TIMESPAN_WEEKEND:
                default:
                    $timespanLabel = 'Weekend';
                    break;
            }

            $this->getPage()->setVar([
                'REPORT_TIMESPAN_VALUE'    => $timespanOption,
                'REPORT_TIMESPAN_SELECTED' => $optionSelected ? 'selected="selected"' : '',
                'REPORT_TIMESPAN_LABEL'    => $timespanLabel,
            ]);

            $this->getPage()->parse('report_timespan_block', 'report_timespan', true);
        }
    }

    /**
     * Render headings for each day in the specified range
     */
    protected function renderTableHeaders()
    {
        $this->getPage()->setBlock(TEMPLATE_content, 'report_header', 'report_header_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'report_header_subtitle', 'report_header_subtitle_block');
        $this->getPage()->setBlock('report_header_subtitle', 'report_header_subtitle_show_am', 'report_header_subtitle_show_am_block');
        $this->getPage()->setBlock('report_header_subtitle', 'report_header_subtitle_show_day', 'report_header_subtitle_show_day_block');
        $this->getPage()->setBlock('report_header_subtitle', 'report_header_subtitle_show_pm', 'report_header_subtitle_show_pm_block');

        if (count($this->getRequestList())) {
            /* Parse out the headings
             */
            for ($i = 0; $i < $this->getRange(); $i++) {
                $headerDate = Alaress_Date_Format::newLocalShort($this->getReportStart());
                $headerDate->addDays($i);

                $this->getPage()->setVar(
                    array(
                        'REPORT_HEADER_WEEKDAY' => $headerDate->getUsingFormat('l'),
                        'REPORT_HEADER_DATE'    => $headerDate->getLocalShort(),
                    )
                );

                /* Hide the first two columns of the first group (but not for single-day view)
                 */
                $skipPeriod = 0 == $i && 1 !== $this->getRange();
                if ($skipPeriod) {
                    $this->getPage()->setVar('REPORT_HEADER_COLSPAN', 2);
                    $this->getPage()->setVar('report_header_subtitle_show_am_block', '');
                    $this->getPage()->parse('report_header_subtitle_show_day_block', 'report_header_subtitle_show_day');
                } else {
                    $this->getPage()->setVar('REPORT_HEADER_COLSPAN', 3);
                    $this->getPage()->parse('report_header_subtitle_show_am_block', 'report_header_subtitle_show_am');
                    $this->getPage()->parse('report_header_subtitle_show_day_block', 'report_header_subtitle_show_day');
                }

                $this->getPage()->parse('report_header_subtitle_show_pm_block', 'report_header_subtitle_show_pm');

                $this->getPage()->parse('report_header_block', 'report_header', true);
                $this->getPage()->parse('report_header_subtitle_block', 'report_header_subtitle', true);

                /* Clear children after parsing out the parent
                 */
                $this->getPage()->setVar('report_header_subtitle_show_am_block', '');
                $this->getPage()->setVar('report_header_subtitle_show_day_block', '');
                $this->getPage()->setVar('report_header_subtitle_show_pm_block', '');
            }
        } else {
            $this->getPage()->setVar('report_header_block', '');
            $this->getPage()->setVar('report_header_subtitle_block', '');
        }
    }

    /**
     * Render leave requests
     * TODO: The current implementation tries to combine multiple requests for a single
     * TODO:   user into one table row. Propose that the default version of this function render each request per row
     * TODO:   and schools that wish to change (eg kings) can override this method
     * TODO:   Or have a config whether to render each per own row.
     */
    protected function renderTableRowsAndFooter()
    {
        $this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'row', 'row_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'request_period_total', 'request_period_total_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'request_period_initial', 'request_period_initial_block');
        $this->getPage()->setBlock('row', 'request_period', 'request_period_block');
        $this->getPage()->setBlock('row', 'request_authorisation', 'request_authorisation_block');

        $this->getPage()->setBlock('request_period', 'request_period_show_am', 'request_period_show_am_block');
        $this->getPage()->setBlock('request_period', 'request_period_show_day', 'request_period_show_day_block');
        $this->getPage()->setBlock('request_period', 'request_period_show_pm', 'request_period_show_pm_block');


        if (count($this->getRequestList())) {
            $this->getPage()->setVar('no_requests_block', '');

            $allPeople   = $this->getCombined();
            $totalPeople = count($allPeople);

            // Create base totals for all ranges and periods
            for ($j = 0; $j < $this->getRange(); $j++) {
                $totals[] = array('morning' => $totalPeople, 'day' => $totalPeople, 'night' => $totalPeople);
            }

            if (is_array($allPeople) && $totalPeople) {
                foreach ($allPeople as $currentPerson) {
                    $hostTypes = array();

                    foreach ($currentPerson['request_host_type'] as $currentHostType) {
                        if (!empty($currentHostType)) {
                            $hostTypes[] = $currentHostType;
                        }
                    }

                    $this->getPage()->setVar(
                        array(
                            'LEAVE_USER_ID'              => $currentPerson['user_id'],
                            'LEAVE_USER_NAME'            => $currentPerson['user_name'],
                            'LEAVE_USER_YEAR'            => $currentPerson['user_year'],
                            'LEAVE_USER_BOARDING_STATUS' => $currentPerson['user_boarding_status'],
                            'REQUEST_STATUS'       => implode('<hr />', $currentPerson['request_status']),
                            'REQUEST_HOST'         => implode('<hr />', $hostTypes),
                            'REQUEST_ACTIVITY'     => implode('<hr />', $currentPerson['request_description']),
                            'CLASS'                => implode(' ', $currentPerson['row_css_class']),
                        )
                    );

                    /* Render out each authorisation user -_-
                     */
                    if (is_array($currentPerson['request_authorisation_user_id']) && count($currentPerson['request_authorisation_user_id'])) {
                        foreach ($currentPerson['request_authorisation_user_id'] as $id => $name) {
                            $this->getPage()->setVar(
                                array(
                                    'REQUEST_AUTHORISATION_USER_ID'   => $id,
                                    'REQUEST_AUTHORISATION_USER_NAME' => $name,
                                )
                            );

                            $this->getPage()->parse('request_authorisation_block', 'request_authorisation', true);
                        }
                    } else {
                        $this->getPage()->setVar('request_authorisation_block', '');
                    }

                    /* Used to hide the first two blocks
                     */
                    $i = 0;

                    foreach ($currentPerson['periods'] as $currentPeriod) {
                        foreach ($this->rangePeriods as $section) {
                            $requestOutput   = '';
                            $removeFromTotal = false;

                            foreach ($currentPeriod['request_period'][$section] as $request) {
                                if ($request['status'] == Leave_Request::STATUS_PENDING && $request['display']) {
                                    $requestOutput .= $this->getDecorationPendingStart() . $request['display'] . $this->getDecorationPendingEnd();
                                } else {
                                    if ($request['status'] == Leave_Request::STATUS_APPROVED && $request['display']) {
                                        $requestOutput .= $this->getDecorationApprovedStart() . $request['display'] . $this->getDecorationApprovedEnd();

                                        if ($request['direction'] == self::AWAY || $request['direction'] == self::RETURNING) {
                                            $removeFromTotal = true;
                                        }
                                    }
                                }
                            }
                            $this->getPage()->setVar('REQUEST_PERIOD_' . strtoupper($section), $requestOutput);
                            $removeFromTotal ? $totals[$i][$section]-- : false;
                        }

                        $skipPeriod = 0 == $i && 1 !== $this->getRange();
                        if ($skipPeriod) {
                            $this->getPage()->setVar('request_period_show_am_block', '');
                            $this->getPage()->parse('request_period_show_day_block', 'request_period_show_day');
                        } else {
                            $this->getPage()->parse('request_period_show_am_block', 'request_period_show_am');
                            $this->getPage()->parse('request_period_show_day_block', 'request_period_show_day');
                        }

                        $this->getPage()->parse('request_period_show_pm_block', 'request_period_show_pm');
                        $this->getPage()->parse('request_period_block', 'request_period', true);

                        /* Clear children after parsing out the parent
                         */
                        $this->getPage()->setVar('request_period_show_am_block', '');
                        $this->getPage()->setVar('request_period_show_day_block', '');
                        $this->getPage()->setVar('request_period_show_pm_block', '');

                        $i++;
                    }

                    $this->getPage()->parse('row_block', 'row', true);

                    /* Clear children after parsing out the parent
                     */
                    $this->getPage()->setVar('request_block', '');
                    $this->getPage()->setVar('request_period_block', '');
                    $this->getPage()->setVar('request_authorisation_block', '');
                }
            }
        } /* This condition never occurs because of left join on users.
         */
        else {
            $this->getPage()->parse('no_requests_block', 'no_requests');
            $this->getPage()->setVar('row_block', '');
        }

        /* Output totals */
        $periodCount = 0;
        for ($j = 0; $j < $this->getRange(); $j++) {
            foreach ($this->rangePeriods as $period) {
                $skipFirstMorning = $j == 0 && $period == 'morning' && 1 !== $this->getRange();
                if (!$skipFirstMorning) {
                    $periodCount++;
                    $this->getPage()->setVar('PERIOD_TOTAL', $totals[$j][$period]);
                    $this->getPage()->setVar('CLASS_PERIOD_TOTAL', '');
                    $this->getPage()->parse('request_period_total_block', 'request_period_total', true);
                    $this->getPage()->parse('request_period_initial_block', 'request_period_initial', true);
                }
            }
        }
        $this->getPage()->setVar('PERIOD_COUNT', $periodCount);
    }









    /* Getters/Setters
     */


    /**
     * Get template file
     *
     * @return string
     */
    protected function getTemplateFile()
    {
        return $this->templateFile;
    }



    /**
     * Get start of pending decoration
     *
     * @return mixed
     */
    protected function getDecorationPendingStart()
    {
        return $this->decoration['pendingStart'];
    }

    /**
     * Get end of pending decoration
     *
     * @return mixed
     */
    protected function getDecorationPendingEnd()
    {
        return $this->decoration['pendingEnd'];
    }

    /**
     * Get start of approved decoration
     *
     * @return mixed
     */
    protected function getDecorationApprovedStart()
    {
        return $this->decoration['approvedStart'];
    }

    /**
     * Get end of approved decoration
     *
     * @return mixed
     */
    protected function getDecorationApprovedEnd()
    {
        return $this->decoration['approvedEnd'];
    }
}
