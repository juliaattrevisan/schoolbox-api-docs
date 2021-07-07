<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Leave\HostType;
use Schoolbox\GenericCollection;

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
    protected $reportTimespan;

    /** @var  Alaress_Date_Format */
    protected $reportStart;

    /** @var  Alaress_Date_Format */
    protected $reportFinish;

    protected $requestList;
    protected $houseList;
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
    const LEAVING               = 1;
    const AWAY                  = 2;
    const RETURNING             = 3;
    const LEAVING_AND_RETURNING = 4;  // Leave and return in same day section (eg 1hr leave during morning)

    /**
     * Constructor
     *
     * @param SitePage $page
     * @param array $data
     * @var HouseResidential      $data['house']
     * @var string                $data['dateStart']
     * @var string|null           $data['dateEnd']
     * @var string                $data['reportTimespan']
     * @var Alaress_Date_Format   $data['reportStart']
     * @var Alaress_Date_Format   $data['reportFinish']
     * @var \Schoolbox\Collection $data['requestList']
     * @var Leave_Request         $data['requestList'][]
     * @var \Schoolbox\Collection $data['houseList']
     * @var HouseResidential      $data['houseList'][]
     */
    public function __construct(SitePage $page, $data)
    {
        $this->setPage($page);
        $this->setCurrentHouse($data['house']);
        $this->setReportStartDate($data['dateStart']);
        $this->setReportEndDate($data['dateEnd']);
        $this->setReportTimespan($data['reportTimespan']);
        $this->setReportStart($data['reportStart']);
        $this->setReportFinish($data['reportFinish']);
        $this->setRange($this->getReportFinish()->diffInDays($this->getReportStart(), true) + 1);
        $this->setRequestList($data['requestList']);
        $this->setHouseList($data['houseList']);

        /* Execute data recombination
         */
        $this->recombination();
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
            $combined[$key]['user_year']            = $request->getUser()->getYear() ? $request->getUser()->getYear()->getName() : '';
            $combined[$key]['user_boarding_status'] = $request->getUser()->getAbbreviatedBoardingStatus();

            $combined[$key]['request_id'][$request->getId()]          = $request->getId();
            $combined[$key]['request_status'][$request->getId()]      = $request->getStatus(true);
            $combined[$key]['request_host_type'][$request->getId()]   = $request->getHostType() instanceof HostType
                                                                        ? $request->getHostType()->getName()
                                                                        : '';
            $combined[$key]['request_description'][$request->getId()] = $request->getDescription();
            $combined[$key]['request_travel_type'][$request->getId()] = $request->getTravelType() instanceof Leave_Request_TravelType
                                                                        ? $request->getTravelType()->getName()
                                                                        : '';

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


            /* For each day that the person is on leave within the prescribed period of time, show an
             * icon. If they are starting or ending their leave on this day, show the time as well
             *
             * Render 3 columns per day
             *
                * AM:    10:00:00 to 18:59:59
                * PM:    19:00:00 to 21:59:59
                * Night: 22:00:00 to 9:59:59
             */
            $priorNightEnd = Alaress_Date_Format::newLocalShort($this->getReportStart());
            $priorNightEnd->addHours($this->offsets['nightEnd'])->addDays(-1);

            $morningStart = Alaress_Date_Format::newLocalShort($this->getReportStart());
            $morningStart->addHours($this->offsets['morningStart']);

            $dayStart = Alaress_Date_Format::newLocalShort($this->getReportStart());
            $dayStart->addHours($this->offsets['dayStart']);

            $nightStart = Alaress_Date_Format::newLocalShort($this->getReportStart());
            $nightStart->addHours($this->offsets['nightStart']);

            $nightEnd = Alaress_Date_Format::newLocalShort($this->getReportStart());
            $nightEnd->addHours($this->offsets['nightEnd']);

            $nextMorningStart = Alaress_Date_Format::newLocalShort($this->getReportStart());
            $nextMorningStart->addHours($this->offsets['morningStart'])->addDays(1);

            // For each day in our timespan
            for ($j = 0; $j < $this->getRange(); $j++) {
                // Is this person is on leave during the morning section ?
                if ($request->getOccupancy($morningStart, $dayStart)) {
                    $onLeavePreviousNight = $request->getOccupancy($priorNightEnd, $morningStart);
                    $onLeaveThisDay       = $request->getOccupancy($dayStart, $nightStart);

                    // Leave continues through this morning
                    if ($onLeavePreviousNight) {
                        if ($onLeaveThisDay) {
                            $requestPeriodMorning          = 'L';
                            $requestPeriodMorningDirection = self::AWAY;
                        } else { // Leave ends this morning
                            $requestPeriodMorning          = $request->getFinish()->getTimeOnly();
                            $requestPeriodMorningDirection = self::RETURNING;
                        }
                    } else {
                        if ($onLeaveThisDay) {  // Leave starts this morning
                            $requestPeriodMorning          = $request->getStart()->getTimeOnly();
                            $requestPeriodMorningDirection = self::LEAVING;
                        } else {  // Leave starts and ends in this morning section
                            $requestPeriodMorning          = $request->getStart()->getTimeOnly() . ' ' . $request->getFinish()->getTimeOnly();
                            $requestPeriodMorningDirection = self::LEAVING_AND_RETURNING;
                        }
                    }
                } else {
                    $requestPeriodMorning = null;
                    $requestPeriodMorningDirection = null;
                }

                // Is this person is on leave during the day ?
                if ($request->getOccupancy($dayStart, $nightStart)) {
                    $onLeaveThisMorning = $request->getOccupancy($morningStart, $dayStart);
                    $onLeaveThisNight   = $request->getOccupancy($nightStart, $nightEnd);

                    // Leave continues through this day
                    if ($onLeaveThisMorning) {
                        if ($onLeaveThisNight) {
                            $requestPeriodDay          = 'L';
                            $requestPeriodDayDirection = self::AWAY;
                        } else {
                            $requestPeriodDay          = $request->getFinish()->getTimeOnly();
                            $requestPeriodDayDirection = self::RETURNING;
                        }
                    } else {
                        if ($onLeaveThisNight) { // Leave starts this day
                            $requestPeriodDay          = $request->getStart()->getTimeOnly();
                            $requestPeriodDayDirection = self::LEAVING;
                        } else {  // Leave starts and ends in this day section
                            $requestPeriodDay          = $request->getStart()->getTimeOnly() . ' ' . $request->getFinish()->getTimeOnly();
                            $requestPeriodDayDirection = self::LEAVING_AND_RETURNING;
                        }
                    }
                } else {
                    $requestPeriodDay = null;
                    $requestPeriodDayDirection = null;
                }

                /* If this person is on leave during the night
                 */
                if ($request->getOccupancy($nightStart, $nightEnd)) {
                    $onLeaveThisDay     = $request->getOccupancy($dayStart, $nightStart);
                    $onLeaveNextMorning = $request->getOccupancy($nightEnd, $nextMorningStart);

                    // Leave continues through this night
                    if ($onLeaveThisDay) {
                        if ($onLeaveNextMorning) {
                            $requestPeriodNight          = 'L';
                            $requestPeriodNightDirection = self::AWAY;
                        } else { // Leave ends this night
                            $requestPeriodNight          = $request->getFinish()->getTimeOnly();
                            $requestPeriodNightDirection = self::RETURNING;
                        }
                    } else {
                        if ($onLeaveNextMorning) { // Leave starts this night
                            $requestPeriodNight          = $request->getStart()->getTimeOnly();
                            $requestPeriodNightDirection = self::LEAVING;
                        } else { // Leave starts and ends in this night section
                            $requestPeriodNight          = $request->getStart()->getTimeOnly() . ' ' . $request->getFinish()->getTimeOnly();
                            $requestPeriodNightDirection = self::LEAVING_AND_RETURNING;
                        }
                    }
                } else {
                    $requestPeriodNight = null;
                    $requestPeriodNightDirection = null;
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
                $priorNightEnd->addDays(1);
                $morningStart->addDays(1);
                $dayStart->addDays(1);
                $nightStart->addDays(1);
                $nightEnd->addDays(1);
                $nextMorningStart->addDays(1);
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
     * @param int $range
     */
    protected function setRange($range)
    {
        $this->range = $range;
    }

    /**
     * Get range
     *
     * @return int
     */
    protected function getRange()
    {
        return $this->range;
    }

    /**
     * Set request list
     *
     * @param GenericCollection $requestList
     */
    protected function setRequestList($requestList)
    {
        $this->requestList = $requestList;
    }

    /**
     * Get request list
     *
     * @return GenericCollection
     */
    protected function getRequestList()
    {
        return $this->requestList;
    }

    /**
     * Set combined result
     *
     * @param array $combined
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
     * @param GenericCollection $houseList
     */
    protected function setHouseList($houseList)
    {
        $this->houseList = $houseList;
    }

    /**
     * Get house list
     *
     * @return GenericCollection
     */
    protected function getHouseList()
    {
        return $this->houseList;
    }

    /**
     * Set current house
     *
     * @param HouseResidential $currentHouse
     */
    protected function setCurrentHouse($currentHouse)
    {
        $this->currentHouse = $currentHouse;
    }

    /**
     * Get current house
     *
     * @return HouseResidential
     */
    protected function getCurrentHouse()
    {
        return $this->currentHouse;
    }

    /**
     * The startDate requested by user, may not equal report display start
     * @param string $startDate
     */
    protected function setReportStartDate($startDate)
    {
        $this->startDate = $startDate;
    }

    /**
     * Start Date requested by user, may not be equal to report's displayed start
     * @return string
     */
    protected function getReportStartDate()
    {
        return $this->startDate;
    }

    /**
     * The endDate requested by user, may not equal report display finish
     * @param string|null $endDate
     */
    protected function setReportEndDate($endDate)
    {
        $this->endDate = $endDate;
    }

    /**
     * End Date requested by user, may not be equal to report's displayed finish
     * @return string|null
     */
    protected function getReportEndDate()
    {
        return $this->endDate;
    }

    /**
     * @param string $reportTimespan Enum from {'day','weekend','range'}
     */
    protected function setReportTimespan($reportTimespan)
    {
        $this->reportTimespan = $reportTimespan;
    }

    /**
     * Enum from {'day','weekend','range'}
     * @return string
     */
    protected function getReportTimespan()
    {
        return $this->reportTimespan;
    }

    /**
     * @param Alaress_Date_Format $reportStart
     */
    protected function setReportStart(Alaress_Date_Format $reportStart)
    {
        $this->reportStart = $reportStart;
    }

    /**
     * @return Alaress_Date_Format
     */
    protected function getReportStart()
    {
        return $this->reportStart;
    }

    /**
     * @param Alaress_Date_Format $reportFinish
     */
    protected function setReportFinish(Alaress_Date_Format $reportFinish)
    {
        $this->reportFinish = $reportFinish;
    }

    /**
     * @return Alaress_Date_Format
     */
    protected function getReportFinish()
    {
        return $this->reportFinish;
    }
}
