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
     * @param External_Timetable       $externalTimetable
     * @param \Schoolbox\External\User $userRelationHydrator
     */
    public function __construct(
        External_Timetable $externalTimetable,
        private \Schoolbox\External\User $userRelationHydrator,
    ) {
        $this->setExternalTimetableHandler($externalTimetable);
    }


    /**
     * This screen is a FullCalendar view of all timetable-related subscriptions
     * for a specific user, $user
     *
     * @param \Schoolbox\User $user - The user whose timetable is to be retrieved.
     * @param string          $date
     * @return void
     * @throws \InvalidArgumentException
     */
    public function getTimetableCalendar(\Schoolbox\User $user, string $date): void
    {
        $loggedInUser = $this->getUser();

        // The children of $loggedInUser will be passed to the Twig template as $userOptions.
        $userOptions = [];
        if ($loggedInUser->getRole()->isParent()) {
            $this->userRelationHydrator->loadChildren([$loggedInUser]);
            foreach ($loggedInUser->getChildren() as $child) {
                $campuses = $child->getCampusCollection();
                $userOptions[] = [
                    'userId' => $child->getId(),
                    'firstCampusName' => (count($campuses) === 0) ? null : $campuses->first()->getName(),
                    'formattedName' => $child->formatName(),
                    'yearLevel' => $child->getYear()?->getLongName('Year')
                ];
            }
        }

        if (
            // If the logged-in user is a parent, and not a superuser, and has requested their own timetable...
            count($userOptions) > 0 &&
            !$loggedInUser->isSuperuser() &&
            $loggedInUser->getId() === $user->getId()
        ) {
            // Redirect to the timetable of the logged-in user's first child.
            // (Parents do not need to see their own timetable - clarified in Jira comments for SA-16065).
            $this->response = $this->redirectFound(
                $this->routeGenerator->generate(
                    'timetable.getTimetableCalendarRedirect',
                    [
                        'userId' => $userOptions[0]['userId']
                    ]
                )
            );
            return;
        }

        // The selected user is the $user.
        $campuses = $user->getCampusCollection();
        $selectedUserOption = [
            'userId' => $user->getId(),
            'firstCampusName' => (count($campuses) === 0) ? null : $campuses->first()->getName(),
            'formattedName' => $user->formatName(),
            'yearLevel' => $user->getYear()?->getLongName('Year')
        ];

        $this->response = $this->responseFromTemplate(
            '@timetable/timetableCalendar.twig',
            // This data structure will be passed through the twig template to the Vue template (timetable-calendar.vue).
            [
                'options' => [
                    'selectedDate' => (new \Alaress_Date_Format($date, 'd-m-Y'))->getDatabaseDate(),
                    'selectedUserOption' => $selectedUserOption,
                    'userOptions' => $userOptions,
                    'weekends' => 'false' // TODO: Figure out what happens when school weeks start on a weekend.
                ]
            ],
            'Timetable Calendar for ' . $user->getFullName()
        );
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
        $campus    = $this->requestQuery['campus'];
        $dayNumber = $this->requestQuery['day'];
        $date      = strtotime($this->requestQuery['date']);
        $previous  = $this->requestQuery['previous'];
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
        $this->response = $this->responseAsJson($data)->withStatus($code);
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
