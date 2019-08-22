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
    /** @var Calendar $calendarHandler */
    protected $calendarHandler = null;

    /** @var Group $groupHandler */
    protected $groupHandler = null;

    protected $subStuHandler = null;
    protected $page          = null;
    protected $date          = '';
    protected $categoryList  = [];
    protected $templateFile  = '';
    protected $selected      = [];
    protected $selectedDate  = null;
    protected $startOfWeek   = null;

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
     * @param                      $page
     * @param User                 $user
     * @param                      $date
     * @param User                 $viewer
     * @param Calendar_Api_Factory $factory
     * @param ModuleChecker        $moduleChecker
     */
    public function __construct($page, User $user, $date, User $viewer, Calendar_Api_Factory $factory, ModuleChecker $moduleChecker)
    {
        $api = $factory->produceForUser($user, $viewer);

        $this->setApi($api);

        /* Still used for perms and stuff
         */
        $this->setCalendarHandler(new Calendar());
        $this->moduleChecker = $moduleChecker;

        $this->user = $user;
        $this->viewer = $viewer;
        $this->setGroupHandler(new Group());
        $this->setPage($page);
        $this->setDate($date);
        $this->setCategoryList($this->getGroupHandler()->involvedInCategoryList($user->getId()));
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
        list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $this->getDate());

        $selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);

        /* Default url access doesn't have a date portion
         * i.e. /calendar/week/. In that case, just use now
         */
        if ($selectedDate <= 0) {
            $selectedDate = time();
        }

        $this->setSelectedDate($selectedDate);

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

        $this->getPage()->setVar(
            [
                'SELECTED_DAY'   => $selectedDay,
                'SELECTED_MONTH' => $selectedMonth,
                'SELECTED_YEAR'  => $selectedYear,
                'YEAR'           => date("Y", $startOfWeek),
                'MONTH'          => $month,
                'DAY'            => $selectedDay,
            ]
        );

        $this->setStartOfWeek($startOfWeek);
    }

    /**
     * Setup movement buttons
     */
    protected function setupMovementButtons(): void
    {
        // Load sources, types
        $typeList   = EventTypeMapper::getInstance()->findAll();
        $sourceList = Calendar_Api_Config_Mapper::getInstance()->getSourceListForCalendar($this->moduleChecker);


        // Load what is selected: default to selecting everything
        /** @var EventType[] $typeList */
        if (isset($_SESSION['calendarFilter'])) {
            $selected['group']     = $_SESSION['calendarFilter']['group'] ?? [];
            $selected['campus']    = $_SESSION['calendarFilter']['campus'] ?? [];
            $selected['eventType'] = $_SESSION['calendarFilter']['eventType'] ?? [];
            $selected['source']    = $_SESSION['calendarFilter']['source'] ?? [];
        } else {
            // select everything
            foreach ($this->getCategoryList() as $cat) {
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

        $this->setSelected($selected);

        $categoryList = $this->getCategoryList();
        $campusList   = $this->getCalendarHandler()->getFilterCampusList($this->user->getId());

        // Shoehorn the above into JSON-encoded vars
        $this->getPage()->setVar(
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
        $this->getPage()->setVar(
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
        $this->getPage()->setVar(
            'EVENT_TYPES_JSON',
            json_encode(
                $typeList->map(
                    function (EventType $v) {
                        return [
                            'id'         => $v->getId(),
                            'name'       => $v->getType(),
                            'color'      => $v->getColor(),
                            'isTaskType' => $v->isTaskType(),
                        ];
                    }
                )
            )
        );
        $this->getPage()->setVar('SOURCES_JSON', json_encode($sourceList));


        // output task type too
        foreach ($typeList as $type) {
            if ($type->isTaskType()) {
                $this->getPage()->setVar('TASK_TYPE_ID', $type->getId());
            }
        }


        // Output selected filters as JSON
        $this->getPage()->setVar('SELECTED_FILTERS_JSON', json_encode($selected));
    }

    /* Getters/Setters
     */


    /**
     * Set calendar handler
     *
     * @param Calendar $calendarHandler
     */
    protected function setCalendarHandler(Calendar $calendarHandler): void
    {
        $this->calendarHandler = $calendarHandler;
    }

    /**
     * Get calendar handler
     *
     * @return Calendar
     */
    protected function getCalendarHandler(): Calendar
    {
        return $this->calendarHandler;
    }

    /**
     * Set group handler
     *
     * @param Group $groupHandler
     */
    protected function setGroupHandler(Group $groupHandler): void
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * Get group handler
     *
     * @return Group
     */
    protected function getGroupHandler(): Group
    {
        return $this->groupHandler;
    }

    /**
     * Set template file
     *
     * @param string $templateFile
     */
    protected function setTemplateFile(string $templateFile)
    {
        $this->templateFile = $templateFile;
    }

    /**
     * Get template file
     *
     * @return string
     */
    protected function getTemplateFile(): string
    {
        return $this->templateFile;
    }

    /**
     * Set page
     *
     * @param $page
     */
    protected function setPage($page)
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
     * Set date
     *
     * @param $date
     */
    protected function setDate($date)
    {
        $this->date = $date;
    }

    /**
     * Get date
     *
     * @return string
     */
    protected function getDate()
    {
        return $this->date;
    }

    /**
     * Set category list
     *
     * @param array $categoryList
     */
    protected function setCategoryList($categoryList)
    {
        $this->categoryList = $categoryList;
    }

    /**
     * Get category list
     *
     * @return array
     */
    protected function getCategoryList()
    {
        return $this->categoryList;
    }

    /**
     * Set selected
     *
     * @param $selected
     */
    protected function setSelected($selected)
    {
        $this->selected = $selected;
    }

    /**
     * Get selected
     *
     * @return array
     */
    protected function getSelected()
    {
        return $this->selected;
    }

    /**
     * Set selected date
     *
     * @param $selectedDate
     */
    protected function setSelectedDate($selectedDate)
    {
        $this->selectedDate = $selectedDate;
    }

    /**
     * Get selected date
     *
     * @return null
     */
    protected function getSelectedDate()
    {
        return $this->selectedDate;
    }

    /**
     * Set start of week
     *
     * @param $startOfWeek
     */
    protected function setStartOfWeek($startOfWeek)
    {
        $this->startOfWeek = $startOfWeek;
    }

    /**
     * Get start of week
     *
     * @return null
     */
    protected function getStartOfWeek()
    {
        return $this->startOfWeek;
    }

    /**
     * Set api
     *
     * @param $api
     */
    protected function setApi($api)
    {
        $this->api = $api;
    }

    /**
     * Get api
     *
     * @return Calendar_Api_EventType_Interface
     */
    protected function getApi()
    {
        return $this->api;
    }
}
