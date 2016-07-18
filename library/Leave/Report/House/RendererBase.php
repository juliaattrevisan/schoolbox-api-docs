<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * Online house report as found in the Schoolbox
 */
abstract class Leave_Report_House_RendererBase implements Leave_Report_House_RenderableInterface
{
    /** @var  SitePage */
    protected $page;

    /** @var  string */
    protected $startDate;

    /** @var  string */
    protected $endDate;

    /** @var string Enum from {'day','weekend','range'} */
    protected $reportPeriod;

    /** @var  Alaress_Date_Format */
    protected $reportStart;

    /** @var  Alaress_Date_Format */
    protected $reportFinish;

    protected $requestList = array();
    protected $houseList = array();
    protected $range = -1;
    protected $combined = array();
    protected $currentHouse = null;

    /**
     * Offsets from the start time of a day as shown on the report.
     *
     * @var array|int[]
     */
    protected $offsets = [
        'morningStart' => 10,
        'dayStart'     => 19,
        'nightStart'   => 22,
        'nightEnd'     => 34,
    ];



    // Types of leave directions
    const LEAVING = 1;
    const AWAY = 2;
    const RETURNING = 3;

    /**
     * Constructor
     *
     * @param SitePage $page
     * @param array $data
     * @var HouseResidential      $data['house']
     * @var string                $data['dateStart']
     * @var string|null           $data['dateEnd']
     * @var string                $data['reportPeriod']
     * @var Alaress_Date_Format   $data['reportStart']
     * @var Alaress_Date_Format   $data['reportFinish']
     * @var \Schoolbox\Collection $data['requestList']
     * @var Leave_Request         $data['requestList'][]
     * @var \Schoolbox\Collection $data['houseList']
     * @var HouseResidential      $data['houseList'][]
     */
    public function __construct($page, $data)
    {
        $this->setPage($page);
        $this->setCurrentHouse($data['house']);
        $this->setReportStartDate($data['dateStart']);
        $this->setReportEndDate($data['dateEnd']);
        $this->setReportPeriod($data['reportPeriod']);
        $this->setReportStart($data['reportStart']);
        $this->setReportFinish($data['reportFinish']);
        $this->setRange( $this->getReportFinish()->diffInDays($this->getReportStart(), true) + 1);
        $this->setRequestList($data['requestList']);
        $this->setHouseList($data['houseList']);

        /* Execute data recombination
         */
        $this->recombination();

        /* Setup hook if data bundle extended by descendants
         */
        $this->setup($data);
    }


    /**
     * Setup Hook for descendant classes
     * @param array $data extra data for descendant classes
     * @return void
     */
    protected function setup(array $data){

    }

    /**
     * "...with multiple leave entries amalgamated into one"
     * Combine multiple leave requests for the same person into one row.
     * We do this by preprocessing each request and collating the information.
     */
    protected function recombination()
    {
        $combined = array();

        foreach ($this->getRequestList() as $request) {
            /** @var Leave_Request $request */
            $key = $request->getUser()->getId();

            /* Collect general data
             */
            $combined[$key]['user_id']              = $key;
            $combined[$key]['user_name']            = Scrub::smartQuotes($request->getUser()->formatName());
            $combined[$key]['user_year']            = $request->getUser()->getYear();
            $combined[$key]['user_boarding_status'] = $request->getUser()->getAbbreviatedBoardingStatus();

            $combined[$key]['request_id'][$request->getId()]          = $request->getId();
            $combined[$key]['request_status'][$request->getId()]      = $request->getStatus(true);
            $combined[$key]['request_host_type'][$request->getId()]   = $request->getHostType()->getName();
            $combined[$key]['request_description'][$request->getId()] = $request->getDescription();
            $combined[$key]['request_travel_type'][$request->getId()] = $request->getTravelType()->getName();

            /* Pending leave requests get a css class for the row
             * (used to decorate the spans)
             */
            if ($request->isPending()) {
                $combined[$key]['row_css_class'][] = 'leave-pending';
            } /* Non-pending requests (authorised requests)
             */
            else {
                if ($request->getId()) {
                    $combined[$key]['request_authorisation_user_id'][$request->getAuthorisationUser()->getId()]
                                                       = Scrub::smartQuotes(trim($request->getAuthorisationUser()->formatName()));
                    $combined[$key]['row_css_class'][] = 'leave-approved';
                } else {
                    $combined[$key]['row_css_class'][] = '';
                }
            }

            /* Text color if request is still pending
             */
            if ($request->isPending()) {
                $decorationStart = $this->getDecorationPendingStart();
                $decorationEnd   = $this->getDecorationPendingEnd();
            } else {
                $decorationStart = $this->getDecorationApprovedStart();
                $decorationEnd   = $this->getDecorationApprovedEnd();
            }

            /* For each day that the person is on leave within the prescribed period of time, show an
             * icon. If they are starting or ending their leave on this day, show the time as well
             *
             * Render 3 columns each day for 3 days
             *
                * AM: 10:00:00 to 18:59:59
                * PM: 19:00:00 to 21:59:59
                * Night: 22:00:00 to 9:59:59
             */
            $morningStart = Alaress_Date_Format::newLocalShort($this->getReportStart());
            $morningStart->addHours($this->offsets['morningStart']);

            $dayStart = Alaress_Date_Format::newLocalShort($this->getReportStart());
            $dayStart->addHours($this->offsets['dayStart']);

            $nightStart = Alaress_Date_Format::newLocalShort($this->getReportStart());
            $nightStart->addHours($this->offsets['nightStart']);

            $nightEnd = Alaress_Date_Format::newLocalShort($this->getReportStart());
            $nightEnd->addHours($this->offsets['nightEnd']);

            $showStartTime = true;

            for ($j = 0; $j < $this->getRange(); $j++) {
                /* If this person is on leave during the morning
                 */
                if ($request->getOccupancy($morningStart, $dayStart)) {
                    /* Check whether next segment is occupied; if not, also show the time
                     */
                    if ($showStartTime) {
                        /* If $showStartTime == true and he's not on leave in the next period
                         * then he left and returned in the same period
                         */
                        if (!$request->getOccupancy($dayStart, $nightStart)) {
                            $requestPeriodMorning          = $request->getStart()->getTimeOnly() . ' ' . $request->getFinish()->getTimeOnly();
                            $requestPeriodMorningDirection = self::LEAVING;
                        } else {
                            /* Specifically check for this day, to account for multi-week leave
                             */
                            if ($request->getStart()->getDatabaseDate() == $morningStart->getDatabaseDate()) {
                                $requestPeriodMorning          = $request->getStart()->getTimeOnly();
                                $requestPeriodMorningDirection = self::LEAVING;
                            } else {
                                $requestPeriodMorning          = 'L';
                                $requestPeriodMorningDirection = self::AWAY;
                            }
                        }

                        $showStartTime = false;
                    } else {
                        if (!$showStartTime && !$request->getOccupancy($dayStart, $nightStart)) {
                            $requestPeriodMorning          = $request->getFinish()->getTimeOnly();
                            $requestPeriodMorningDirection = self::RETURNING;
                        } else {
                            $requestPeriodMorning          = 'L';
                            $requestPeriodMorningDirection = self::AWAY;
                        }
                    }
                }

                /* If this person is on leave during the day
                 */
                if ($request->getOccupancy($dayStart, $nightStart)) {
                    if ($showStartTime) {
                        if (!$request->getOccupancy($nightStart, $nightEnd)) {
                            $requestPeriodDay          = $request->getStart()->getTimeOnly() . ' ' . $request->getFinish()->getTimeOnly();
                            $requestPeriodDayDirection = self::LEAVING;
                        } else {
                            if ($request->getStart()->getDatabaseDate() == $dayStart->getDatabaseDate()) {
                                $requestPeriodDay          = $request->getStart()->getTimeOnly();
                                $requestPeriodDayDirection = self::LEAVING;
                            } else {
                                $requestPeriodDay          = 'L';
                                $requestPeriodDayDirection = self::AWAY;
                            }
                        }

                        $showStartTime = false;
                    } else {
                        if (!$showStartTime && !$request->getOccupancy($nightStart, $nightEnd)) {
                            $requestPeriodDay          = $request->getFinish()->getTimeOnly();
                            $requestPeriodDayDirection = self::RETURNING;
                        } else {
                            $requestPeriodDay          = 'L';
                            $requestPeriodDayDirection = self::AWAY;
                        }
                    }
                }

                /* If this person is on leave during the night
                 */
                if ($request->getOccupancy($nightStart, $nightEnd)) {
                    /* Check whether the next morning is occupied
                     */
                    $nextMorning = clone $dayStart;
                    $nextMorning->addDays(1);

                    if ($showStartTime) {
                        if (!$request->getOccupancy($nightEnd, $nextMorning)) {
                            $requestPeriodNight          = $request->getStart()->getTimeOnly() . ' ' . $request->getFinish()->getTimeOnly();
                            $requestPeriodNightDirection = self::LEAVING;
                        } else {
                            if ($request->getStart()->getDatabaseDate() == $nightStart->getDatabaseDate()) {
                                $requestPeriodNight          = $request->getStart()->getTimeOnly();
                                $requestPeriodNightDirection = self::LEAVING;
                            } else {
                                $requestPeriodNight          = 'L';
                                $requestPeriodNightDirection = self::AWAY;
                            }
                        }

                        $showStartTime = false;
                    } else {
                        if (!$showStartTime && !$request->getOccupancy($nightEnd, $nextMorning)) {
                            $requestPeriodNight          = $request->getFinish()->getTimeOnly();
                            $requestPeriodNightDirection = self::RETURNING;

                        } else {
                            $requestPeriodNight          = 'L';
                            $requestPeriodNightDirection = self::AWAY;
                        }
                    }
                }

                /* Display icon and time where appropriate
                 */
                $combined[$key]['periods'][$j]['request_period']['morning'][] = array(
                    'display'   => $requestPeriodMorning,
                    'direction' => $requestPeriodMorningDirection,
                    'status'    => $request->getStatus()
                );
                $combined[$key]['periods'][$j]['request_period']['day'][]     = array(
                    'display'   => $requestPeriodDay,
                    'direction' => $requestPeriodDayDirection,
                    'status'    => $request->getStatus()
                );
                $combined[$key]['periods'][$j]['request_period']['night'][]   = array(
                    'display'   => $requestPeriodNight,
                    'direction' => $requestPeriodNightDirection,
                    'status'    => $request->getStatus()
                );


                /* Iteration reset
                 */
                $morningStart->addDays(1);
                $dayStart->addDays(1);
                $nightStart->addDays(1);
                $nightEnd->addDays(1);

                unset($requestPeriodMorning, $requestPeriodDay, $requestPeriodNight, $requestPeriodMorningDirection, $requestPeriodDayDirection, $requestPeriodNightDirection);
            }
        }

        $this->setCombined($combined);
    }


    /* Getters/Setters
     */



    /**
     * Set page
     *
     * @param SitePage $page
     */
    protected function setPage(SitePage $page)
    {
        $this->page = $page;
    }

    /**
     * Get page
     *
     * @return SitePage
     */
    protected function getPage()
    {
        return $this->page;
    }

    /**
     * Set range
     *
     * @param $range
     */
    protected function setRange($range)
    {
        $this->range = $range;
    }

    /**
     * Get range
     *
     * @return mixed
     */
    protected function getRange()
    {
        return $this->range;
    }

    /**
     * Set request list
     *
     * @param $requestList
     */
    protected function setRequestList($requestList)
    {
        $this->requestList = $requestList;
    }

    /**
     * Get request list
     *
     * @return mixed
     */
    protected function getRequestList()
    {
        return $this->requestList;
    }

    /**
     * Set combined result
     *
     * @param $combined
     */
    protected function setCombined($combined)
    {
        $this->combined = $combined;
    }

    /**
     * Get combined result
     *
     * @return array
     */
    protected function getCombined()
    {
        return $this->combined;
    }

    /**
     * Set house list
     *
     * @param $houseList
     */
    protected function setHouseList($houseList)
    {
        $this->houseList = $houseList;
    }

    /**
     * Get house list
     *
     * @return array
     */
    protected function getHouseList()
    {
        return $this->houseList;
    }

    /**
     * Set current house
     *
     * @param $currentHouse
     */
    protected function setCurrentHouse($currentHouse)
    {
        $this->currentHouse = $currentHouse;
    }

    /**
     * Get current house
     *
     * @return null
     */
    protected function getCurrentHouse()
    {
        return $this->currentHouse;
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

    /**
     * The startDate requested by user, may not equal report display start
     * @param string $startDate
     */
    protected function setReportStartDate($startDate){
        $this->startDate = $startDate;
    }

    /**
     * Start Date requested by user, may not be equal to report's displayed start
     * @return string
     */
    protected function getReportStartDate(){
        return $this->startDate;
    }

    /**
     * The endDate requested by user, may not equal report display finish
     * @param string|null $endDate
     */
    protected function setReportEndDate($endDate){
        $this->endDate = $endDate;
    }

    /**
     * End Date requested by user, may not be equal to report's displayed finish
     * @return string|null
     */
    protected function getReportEndDate(){
        return $this->endDate;
    }

    /**
     * @param string $reportPeriod Enum from {'day','weekend','range'}
     */
    protected function setReportPeriod($reportPeriod){
        $this->reportPeriod = $reportPeriod;
    }

    /**
     * Enum from {'day','weekend','range'}
     * @return string
     */
    protected function getReportTimespan(){
        return $this->reportPeriod;
    }

    /**
     * @param Alaress_Date_Format $reportStart
     */
    protected function setReportStart(Alaress_Date_Format $reportStart){
        $this->reportStart = $reportStart;
    }

    /**
     * @return Alaress_Date_Format
     */
    protected function getReportStart(){
        return $this->reportStart;
    }

    /**
     * @param Alaress_Date_Format $reportFinish
     */
    protected function setReportFinish(Alaress_Date_Format $reportFinish){
        $this->reportFinish = $reportFinish;
    }

    /**
     * @return Alaress_Date_Format
     */
    protected function getReportFinish(){
        return $this->reportFinish;
    }


}
