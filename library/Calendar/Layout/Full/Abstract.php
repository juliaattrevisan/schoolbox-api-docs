<?php
/**
 * Calendar_Layout_Full_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\User;
use Schoolbox\UserMapper;

abstract class Calendar_Layout_Full_Abstract
{
    /** @var Calendar $calendarHandler */
    protected $calendarHandler = null;

    /** @var Group $groupHandler */
    protected $groupHandler = null;

    protected $subStuHandler = null;
    protected $page = null;
    protected $date = '';
    protected $categoryList = [];
    protected $templateFile = '';
    protected $selected = [];
    protected $selectedDate = null;
    protected $startOfWeek = null;

    /** @var Calendar_Api_EventType_Interface Actual connector instance */
    protected $api;

    /** @var  Schoolbox\User $user     Who this Calendar is about */
    protected $user;

    /** @var  Schoolbox\User $viewer   Who is currently viewing the Calendar */
    protected $viewer;

    /**
     * Constructor
     *
     * @param $page
     * @param User $user
     * @param $date
     * @param User $viewer
     * @param Calendar_Api_Factory $factory
     */
    public function __construct($page, User $user, $date, User $viewer, Calendar_Api_Factory $factory)
    {
        $api = $factory->produceForUser($user);

        $this->setApi($api);

        /* Still used for perms and stuff
         */
        $this->setCalendarHandler(new Calendar());

        $this->setUser($user);
        $this->setViewer($viewer);
        $this->setGroupHandler(new Group());
        $this->setSubStuHandler(new SubmissionStudent());
        $this->setPage($page);
        $this->setDate($date);
        $this->setCategoryList($this->getGroupHandler()->involvedInCategoryList($user->getId()));
        $this->setupTemplateFile();
        $this->setupBlocks();
    }

    /**
     * Does the current user have permission to view this calendar page?
     *
     * @param  int $userId
     * @param  Schoolbox\User $currentUser
     * @param  Group $groupHandler
     * @throws Exception if user doesn't have permission to view calendar.
     *                   The exception's code matches a Schoolbox error code.
     * @return bool
     */
    public static function permissionCheck($userId, Schoolbox\User $currentUser, Group $groupHandler)
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
     * @return mixed
     */
    abstract protected function setupTemplateFile();

    /**
     * @return mixed
     */
    abstract protected function setupBlocks();

    /**
     * @return void
     */
    abstract protected function render();

    /**
     * Set up selected day/month/year
     */
    protected function setupSelectedDayMonthYear()
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

        $selectedDay = date("j", $selectedDate);
        $selectedMonth = date("n", $selectedDate);
        $selectedYear = date("Y", $selectedDate);

        $dayOfWeek = date("w", mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear));
        $startOfWeek = mktime(0, 0, 0, $selectedMonth, ($selectedDay - $dayOfWeek), $selectedYear);
        $endOfWeek = strtotime("+6 days", $startOfWeek);

        if (date("m", $startOfWeek) != date("m", $endOfWeek)) {
            $month = date('jS F', $startOfWeek) . ' - ' . date("jS F", $endOfWeek);
        } else {
            $month = date("jS", $startOfWeek) . ' - ' . date('jS F', $endOfWeek);
        }

        $this->getPage()->setVar(
            array(
                'SELECTED_DAY'   => $selectedDay,
                'SELECTED_MONTH' => $selectedMonth,
                'SELECTED_YEAR'  => $selectedYear,
                'YEAR'           => date("Y", $startOfWeek),
                'MONTH'          => $month,
                'DAY'            => $selectedDay,
            )
        );

        $this->setStartOfWeek($startOfWeek);
    }

    /**
     * Setup movement buttons
     */
    protected function setupMovementButtons()
    {
        // Load sources
        $sourceList = Calendar_Api_Config_Mapper::getInstance()->getSourceListForCalendar();

        // select all types
        $typeList = \Schoolbox\Calendar\EventTypeMapper::getInstance()->findAll();
        if (isset($_SESSION['calendarFilter'])) {
            $selected['group'] = $_SESSION['calendarFilter']['groups'];
            $selected['campus'] = $_SESSION['calendarFilter']['campus'];
            $selected['event_type'] = $_SESSION['calendarFilter']['events'];
            $selected['source'] = $_SESSION['calendarFilter']['sources'];
        } else {
            // select all groups
            if (count($this->getCategoryList())) {
                foreach ($this->getCategoryList() as $cat) {
                    $selected['group'][] = $cat['id'];
                }
            }

            // select all campuses
            $selected['campus'] = $_SESSION['campus'];

            foreach ($typeList as $type) {
                $selected['event_type'][] = $type->getId();
            }

            // select all sources
            foreach ($sourceList as $source) {
                $selected['source'][] = $source['id'];
            }
        }

        $this->setSelected($selected);

        $category_list = $this->getCategoryList();
        $campus_list = $this->getCalendarHandler()->getFilterCampusList($this->getUser()->getId());
        $this->getPage()->dropDown(
            'export_group',
            $category_list,
            [],
            TEMPLATE_content,
            array('name' => 'EXPORT_GROUP_NAME', 'id' => 'EXPORT_GROUP_ID')
        );
        $this->getPage()->dropDown(
            'export_campus',
            $campus_list,
            [],
            TEMPLATE_content,
            array('CAMPUS_NAME' => 'EXPORT_CAMPUS_NAME', 'CAMPUS_ID' => 'EXPORT_CAMPUS_ID')
        );
        $this->getPage()->dropDown(
            'export_type',
            $typeList,
            [],
            TEMPLATE_content,
            array('type' => 'EXPORT_TYPE_NAME', 'id' => 'EXPORT_TYPE_ID')
        );
        // Might need special PHPLibs here
        $this->getPage()->checkList(
            'filter_source',
            $sourceList,
            $selected['source'],
            TEMPLATE_content,
            array('name' => 'SOURCE_NAME', 'id' => 'SOURCE_ID')
        );
        $this->getPage()->checkList(
            'filter_group',
            $category_list,
            $selected['group'],
            TEMPLATE_content,
            array('name' => 'GROUP_NAME', 'id' => 'GROUP_ID')
        );
        $this->getPage()->checkList(
            'filter_campus',
            $campus_list,
            $selected['campus'],
            TEMPLATE_content,
            array('name' => 'CAMPUS_NAME', 'id' => 'CAMPUS_ID')
        );
        $this->getPage()->checkList(
            'filter_event_type',
            $typeList,
            $selected['event_type'],
            TEMPLATE_content,
            array(
                'type'  => 'EVENT_TYPE_NAME',
                'id'    => 'EVENT_TYPE_ID',
                'color' => 'EVENT_TYPE_COLOUR'
            )
        );
        $this->getPage()->setVar('BACK', date("j-n-Y", strtotime("-1 week", $this->getSelectedDate())));
        $this->getPage()->setVar('FORWARD', date("j-n-Y", strtotime("+1 week", $this->getSelectedDate())));
    }

    /* Getters/Setters
     */


    /**
     * Set calendar handler
     *
     * @param Calendar $calendarHandler
     */
    protected function setCalendarHandler($calendarHandler)
    {
        $this->calendarHandler = $calendarHandler;
    }

    /**
     * Get calendar handler
     *
     * @return Calendar
     */
    protected function getCalendarHandler()
    {
        return $this->calendarHandler;
    }

    /**
     * Set group handler
     *
     * @param Group $groupHandler
     */
    protected function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * Get group handler
     *
     * @return Group
     */
    protected function getGroupHandler()
    {
        return $this->groupHandler;
    }

    /**
     * Set sub stu handler
     *
     * @param $subStuHandler
     */
    protected function setSubStuHandler($subStuHandler)
    {
        $this->subStuHandler = $subStuHandler;
    }

    /**
     * Get sub stu handler
     *
     * @return null
     */
    protected function getSubStuHandler()
    {
        return $this->subStuHandler;
    }

    /**
     * Set template file
     *
     * @param $templateFile
     */
    protected function setTemplateFile($templateFile)
    {
        $this->templateFile = $templateFile;
    }

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

    /**
     * Set user
     *
     * @param User $user
     */
    protected function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Get user
     *
     * @return User
     */
    protected function getUser()
    {
        return $this->user;
    }

    /**
     * Set viewer
     *
     * @param User $viewer
     */
    public function setViewer($viewer){
        $this->viewer = $viewer;
    }

    /**
     * Get viewer
     *
     * @return User
     */
    public function getViewer(){
        return $this->viewer;
    }
}
