<?php
/**
 * Calendar_Layout_Full_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Calendar\Api\ModuleChecker;
use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

abstract class Calendar_Layout_Full_Abstract
{
    protected $page         = null;
    protected $date         = '';
    protected $categoryList = [];
    protected $templateFile = '';
    protected $selected     = [];
    protected $selectedDate = null;
    protected $startOfWeek  = null;

    /** @var Calendar_Api_EventType_Interface Actual connector instance */
    protected $api;

    /** @var  Schoolbox\User $user Who this Calendar is about */
    protected $user;

    /** @var  Schoolbox\User $viewer Who is currently viewing the Calendar */
    protected $viewer;

    /** @var ModuleChecker */
    private $moduleChecker;

    /**
     * Constructor
     *
     * @param SitePage             $page
     * @param User                 $user
     * @param                      $date
     * @param User                 $viewer
     * @param Calendar_Api_Factory $factory
     * @param ModuleChecker        $moduleChecker
     */
    public function __construct(SitePage $page, User $user, $date, User $viewer, Calendar_Api_Factory $factory, ModuleChecker $moduleChecker)
    {
        $this->api           = $factory->produceForUser($user, $viewer);
        $this->moduleChecker = $moduleChecker;
        $this->user          = $user;
        $this->viewer        = $viewer;
        $this->page          = $page;
        $this->date          = $date;
        $this->setupTemplateFile();
        $this->setupBlocks();
    }

    /**
     * Does the current user have permission to view this calendar page?
     *
     * @param int            $userId
     * @param Schoolbox\User $currentUser
     * @param Group          $groupHandler
     * @throws Exception if user doesn't have permission to view calendar.
     *                   The exception's code matches a Schoolbox error code.
     * @return bool
     */
    public static function permissionCheck($userId, Schoolbox\User $currentUser, Group $groupHandler): bool
    {
        if ($currentUser->getId() != $userId) {
            try {
                UserMapper::getInstance()->findById($userId);
            } catch (Exception $e) {
                throw new Exception('User does not exist', 31, $e);
            }
            if (!$currentUser->getSuperuser() &&
                !$currentUser->getRole()->isStaff() &&
                !$groupHandler->isParentOfChild($userId)
            ) {
                throw new Exception('Current user does not have permission to view calendar for this user', 125);
            }
        }
        return true;
    }

    /**
     * @return void
     */
    abstract protected function setupTemplateFile(): void;

    /**
     * @return void
     */
    abstract protected function setupBlocks(): void;

    /**
     * @return void
     */
    abstract protected function render(): void;

    /**
     * Set up selected day/month/year
     */
    protected function setupSelectedDayMonthYear(): void
    {
        [$selectedDay, $selectedMonth, $selectedYear] = explode("-", $this->date);

        $selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);

        /* Default url access doesn't have a date portion
         * i.e. /calendar/week/. In that case, just use now
         */
        if ($selectedDate <= 0) {
            $selectedDate = time();
        }

        $this->selectedDate = $selectedDate;

        if ($selectedDate <= 0) {
            $selectedDate = time();
        }

        $selectedDay   = date("j", $selectedDate);
        $selectedMonth = date("n", $selectedDate);
        $selectedYear  = date("Y", $selectedDate);

        $dayOfWeek   = date("w", mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear));
        $startOfWeek = mktime(0, 0, 0, $selectedMonth, ($selectedDay - $dayOfWeek), $selectedYear);
        $endOfWeek   = strtotime("+6 days", $startOfWeek);

        if (date("m", $startOfWeek) != date("m", $endOfWeek)) {
            $month = date('jS F', $startOfWeek) . ' - ' . date("jS F", $endOfWeek);
        } else {
            $month = date("jS", $startOfWeek) . ' - ' . date('jS F', $endOfWeek);
        }

        $this->page->setVar(
            [
                'SELECTED_DAY'   => $selectedDay,
                'SELECTED_MONTH' => $selectedMonth,
                'SELECTED_YEAR'  => $selectedYear,
                'YEAR'           => date("Y", $startOfWeek),
                'MONTH'          => $month,
                'DAY'            => $selectedDay,
            ]
        );

        $this->startOfWeek = $startOfWeek;
    }

    /**
     * Setup movement buttons
     */
    protected function setupMovementButtons(): void
    {
        // Load sources, types
        $typeList   = EventTypeMapper::getInstance()->findAll();
        $sourceList = Calendar_Api_Config_Mapper::getInstance()->getSourceListForCalendar($this->moduleChecker);

        $categoryList = (new Group())->involvedInCategoryList($this->user->getId());
        $campusList   = (new Calendar())->getFilterCampusList($this->user->getId());

        // Load what is selected: default to selecting everything
        /** @var EventType[] $typeList */
        if (isset($_SESSION['calendarFilter'])) {
            $selected['group']     = $_SESSION['calendarFilter']['group'] ?? [];
            $selected['campus']    = $_SESSION['calendarFilter']['campus'] ?? [];
            $selected['eventType'] = $_SESSION['calendarFilter']['eventType'] ?? [];
            $selected['source']    = $_SESSION['calendarFilter']['source'] ?? [];
        } else {
            // select everything
            foreach ($categoryList as $cat) {
                $selected['group'][] = (int)$cat['id'];
            }

            // select all campuses
            $selected['campus'] = array_map('intval', $_SESSION['campus']);

            foreach ($typeList as $type) {
                $selected['eventType'][] = $type->getId();
            }

            // select all sources
            foreach ($sourceList as $source) {
                $selected['source'][] = $source['ids'];
            }
        }

        $this->selected = $selected;

        // Shoehorn the above into JSON-encoded vars
        $this->page->setVar(
            'GROUPS_JSON',
            json_encode(
                array_map(
                    function ($v) {
                        $v['id']   = (int)$v['id'];
                        $v['type'] = (int)$v['type'];
                        return $v;
                    },
                    $categoryList
                )
            )
        );
        $this->page->setVar(
            'CAMPUSES_JSON',
            json_encode(
                array_map(
                    function ($v) {
                        return [
                            'id'   => (int)$v['CAMPUS_ID'],
                            'name' => $v['CAMPUS_NAME'],
                        ];
                    },
                    $campusList
                )
            )
        );
        $this->page->setVar(
            'EVENT_TYPES_JSON',
            json_encode(
                $typeList->map(
                    function (EventType $v) {
                        return [
                            'id'    => $v->getId(),
                            'name'  => $v->getType(),
                            'color' => $v->getColor(),
                        ];
                    }
                )
            )
        );
        $this->page->setVar('SOURCES_JSON', json_encode($sourceList));


        // Output selected filters as JSON
        $this->page->setVar('SELECTED_FILTERS_JSON', json_encode($selected));
    }
}
