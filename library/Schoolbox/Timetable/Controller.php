<?php

/**
 * Controller for timetable related stuff. Currently only contains the AJAX
 * target for getting the date for a timetable day.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Timetable;

use Alaress\Web\Controller\Base;
use DateTime;
use External_Timetable;

class Controller extends Base
{
    /**
     * @var External_Timetable
     */
    protected $externalTimetableHandler;

    /**
     * Constructor
     *
     * @param External_Timetable $externalTimetable
     */
    public function __construct(External_Timetable $externalTimetable)
    {
        $this->setExternalTimetableHandler($externalTimetable);
    }


    /**
     * This screen is a FullCalendar view of all timetable-related subscriptions
     * for a specific user, $user
     *
     * @param \Schoolbox\User $user
     * @param string          $date
     * @return void
     * @throws \InvalidArgumentException
     */
    public function getTimetableCalendar(\Schoolbox\User $user, string $date): void
    {
        /* Selected date
         */
        $selectedDate = (new \Alaress_Date_Format($date, 'd-m-Y'))->getDatabaseDate();

        /* TODO: Figure out what happens when school weeks start on a weekend
         */
        $weekends = 'false';

        $this->getResponse()->content->set($this->buildHtmlOutput('@timetable/timetableCalendar.twig', [
            'user' => $user,
            'selectedDate' => $selectedDate,
            'weekends' => $weekends
        ])->setTitle(sprintf(
            'Timetable Calendar for %s',
            $user->getFullName()
        )));
    }


    /**
     * Redirect to today's date
     *
     * @param \Schoolbox\User $user
     * @return void
     * @throws \InvalidArgumentException
     */
    public function getTimetableCalendarRedirect(\Schoolbox\User $user): void
    {
        $this->getTimetableCalendar($user, \Alaress_Date_Format::now()->getUsingFormat('d-m-Y'));
    }


    /**
     * Outputs a response for getting the next or previous occurence of a
     * timetable day.
     *
     * Query parameters:
     * * campus:   the ID of the campus to get timetable day for
     * * day:      the day number to get
     * * date:     Date to get next or previous occurence in relation to.
     *             Defaults to current date.
     * * previous: If true, gets previous date - otherwise, gets next date.
     *
     */
    public function dateForTimetableDay()
    {
        $campus    = $this->request->query['campus'];
        $dayNumber = $this->request->query['day'];
        $date      = strtotime($this->request->query['date']);
        $previous  = $this->request->query['previous'];
        $code      = 200;
        $error     = '';
        if ($date === false) {
            $date = null;
        }
        try {
            $campusObj = \Schoolbox\CampusMapper::getInstance()->findById($campus);

            $nearestDate = $previous ?
                $this->getExternalTimetableHandler()->getPreviousDateForDayNumber($campusObj, $dayNumber, $date) :
                $this->getExternalTimetableHandler()->getNextDateForDayNumber($campusObj, $dayNumber, $date);

            $definition = $this->getExternalTimetableHandler()->getTimetableDefinition(
                null,
                $campusObj,
                $dayNumber
            );
        } catch (\InvalidArgumentException $e) {
            $code  = 400;
            $error = $e->getMessage();
        } catch (\RangeException $e) {
            $code  = 400;
            $error = $e->getMessage();
        } catch (\Schoolbox\Mapper\Exception $e) {
            $code  = 400;
            $error = $e->getMessage();
        }

        $data = array();
        if ($error) {
            $data['error'] = $error;
        } elseif (isset($nearestDate) && $nearestDate instanceof DateTime) {
            $data['date'] = $nearestDate->format('Y-m-d');
            // Collate the definition into a list of start and end times
            if (!empty($definition['desc'])) {
                foreach ($definition['desc'] as $i => $desc) {
                    if (isset($definition['start'][$i], $definition['end'][$i])) {
                        $data['periods'][] = [
                            'desc'  => $desc,
                            'start' => date('H:i', strtotime($definition['start'][$i])),
                            'end'   => date('H:i', strtotime($definition['end'][$i])),
                        ];
                    }
                }
            }
        }
        $this->response->status->setCode($code);
        $this->response->content->set(
            array(
                'format' => 'json',
                'data'   => $data,
            )
        );
    }

    /**
     * @return External_Timetable
     */
    protected function getExternalTimetableHandler()
    {
        return $this->externalTimetableHandler;
    }

    /**
     * @param  External_Timetable $externalTimetableHandler
     *
     * @return $this
     */
    protected function setExternalTimetableHandler($externalTimetableHandler)
    {
        $this->externalTimetableHandler = $externalTimetableHandler;
        return $this;
    }
}
