<?php

/************************************************************
 *                                                           *
 *         Copyright (c) 2002-2003 Lektec Consulting         *
 *                                                           *
 ************************************************************/

//timetable object that displays the users timetable

use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;

if (!isset($this)) {
    require_once __DIR__ . '/../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    SitePage::localRedir('/timetable/');
}

/* Redirect to SUDS Timetable calendar screen where you can see the calendar
 * representation of timetable events; any other selection causes you to see
 * the classical cyclic timetable grid
 */
if (\Schoolbox\External\Event\Model::TIMETABLE_VIEW_CALENDAR === Config::get('timetable_view')) {
    if (is_numeric($this->arg[0])) {
        SitePage::localRedir("/timetable/calendar/{$this->arg[0]}");
    }
    /* Go to the currently-logged in user id
     * Disregard whatever the params might have been (don't need them)
     */
    SitePage::localRedir("/timetable/calendar/{$_SESSION['user']->getId()}");
}

// If External_DB not enabled do not display page
if (!Config::get('external_enable')) {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

$groupHandler     = new Group();
$userHandler      = new User();
$timetableHandler = new External_Timetable();
$categoryMapper   = new \Schoolbox\Resource\Folder\Mapper();
$userMapper       = new \Schoolbox\UserMapper();


$this->setFile(TEMPLATE_content, 'class/timetable.html');
$this->setTitle('Timetable');
$this->setTab('timetable');

$this->setBlock(TEMPLATE_content, "no_timetable", "no_timetable_block");
$this->setBlock(TEMPLATE_content, "timetable", "timetable_listing");
$this->setBlock(TEMPLATE_content, 'timetable_person', 'timetable_person_block');
$this->setBlock("timetable", "period", "period_listing");
$this->setBlock("period", "day", "day_listing");
$this->setBlock('day', 'period_class', 'period_class_block');
$this->setBlock('day', 'period_class_link', 'period_class_link_block');
$this->setBlock("timetable", "day_header", "day_header_listing");

/* Fetch list of homepage colours for use */
$colourList = Timetable_ColourMapper::getInstance()->findAll();

/** @var Schoolbox\User[] $userList */
$userList      = [];
$numTimetables = 0;
$child_id      = $this->arg[0] ?? 0;

if ($_SESSION['user']->getRole()->isParent()) {
    if (is_array($_SESSION['children'])) {
        foreach ($_SESSION['children'] as $child) {
            //
            // Was a specific child ID requested? show them only, otherwise
            // show everyone.
            if (!$child_id || ($child_id == $child['id'])) {
                try {
                    $userList[] = $userMapper->findById($child['id']);
                } catch (Schoolbox\Mapper\Exception $e) {
                    //noop, we are getting a list
                }
            }
        }
    }
    //
    // Requesting a child that isn't yours or does not exist, page not found
    if (empty($userList)) {
        $this->pageNotFound();
    }
} else {
    if ($child_id) {
        if ($_SESSION['user']->getRole()->isStaff() || $child_id == $_SESSION['id']) {
            //Output a timetable for each campus user is involved in
            try {
                $userList[] = $userMapper->findById($child_id);
            } catch (Schoolbox\Mapper\Exception $e) {
                $this->pageNotFound();
            }
        } else {
            error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        }
    } else {
        // Output my own timetable (all campuses)
        /** @var Schoolbox\User $userObject */
        $userList[] = $_SESSION['user'];
    }
}

foreach ($userList as $user) {
    $checkedCampusList = [];

    if (!$user->getHideTimetable()) {
        foreach ($user->getCampusCollection()->sortBy('sequence', 'ASC') as $campus) {
            $campusCode = $campus->getExtTimetableCode();
            if (isset($checkedCampusList[$campusCode])) {
                continue;
            }
            $checkedCampusList[$campusCode] = true;

            /* Setup colour map */
            $subjectColourMap = [];
            $colourIndex      = 0;

            $staffIdNameMap = [];

            $staffFlag = $user->getRole()->isStaff();

            $periodCurrent = $timetableHandler->getPeriodNumber($campus, 0, $user);
            $dayCurrent = $timetableHandler->getDayNumber($campus);

            try {
                $timetable = $timetableHandler->getFullTimetable($user->getExternalId(), $user->getId(), $campus);
            } catch (\Schoolbox\External\Exception $e) {
                error(ErrorMapper::ERROR_DATABASE_UNAVAILABLE);
            }

            $classCount = 0;

            /* Find out if the current campus timetable has any classes associated
             * with the timetable definition. If not, skip its rendering altogether
             */
            if (is_array($timetable) && count($timetable)) {
                foreach ($timetable as $currentDay) {
                    if (is_array($currentDay['day']) && count($currentDay['day'])) {
                        foreach ($currentDay['day'] as $currentPeriod) {
                            foreach ($currentPeriod as $currentClass) {
                                if (isset($currentClass['description']) && strlen($currentClass['description'])) {
                                    $classCount++;
                                } elseif (isset($currentClass['code']) && strlen($currentClass['code'])) {
                                    $classCount++;
                                }
                            }
                        }
                    }
                }
            }

            if (is_array($timetable) && !empty($timetable) && $classCount) {
                $numTimetables++;

                /* Only display subnav elements for timetables that contain data
                 */
                $this->setVar([
                    'TIMETABLE_PERSON'  => $user->formatName(),
                    'TIMETABLE_YEAR'    => $user->getYear() ? ', ' . $user->getYear()->getLongName() : '',
                    'TIMETABLE_USER_ID' => $user->getId(),
                    'CAMPUS_ID'         => $campus->getId(),
                    'CAMPUS_CODE'       => $campus->getExtTimetableCode(),
                    'CAMPUS_NAME'       => $campus->getName(),
                ]);

                $this->parse('timetable_person_block', 'timetable_person', true);

                /* Continue as normal
                 */
                $days = count($timetable[array_keys($timetable)[0]]['day']);
                if ($days == 0) {
                    //In case no timetable has been output for what ever reason
                    $this->setVar('timetable_listing', '');
                    continue;
                }
                $periodCount = 1;
                //for each period
                foreach ($timetable as $row) {
                    if ($row['periodSequence'] == 1) {
                        $is_current_period =
                            (isset($periodCurrent['PeriodNumber']) && isset($periodCurrent['PeriodNumberSeq']))
                            && $row['periodNumber'] == $periodCurrent['PeriodNumber']
                            && $row['periodSequence'] == $periodCurrent['PeriodNumberSeq'];
                        $outputPeriodDefinition = [
                            'PERIOD_NAME'  => $row['periodName'],
                            'PERIOD_START' => $row['periodStart'],
                            'PERIOD_END'   => $row['periodEnd'],
                            'PERIOD_CLASS' => ($is_current_period ? 'timetable-period-active' : ''),
                        ];
                        $this->setVar($outputPeriodDefinition);

                        $dayCount = 1;
                        //for each day
                        foreach ($row['day'] as $period) {
                            // only output header on first run through
                            if ($periodCount == 1) {
                                //output day header
                                if ($dayCount == $dayCurrent) {
                                    $this->setVar('DAY_CLASS', 'timetable-day-active');
                                } else {
                                    $this->setVar('DAY_CLASS', '');
                                }
                                $this->setVar('DAY_NAME', $timetableHandler->getDayName($dayCount));
                                $this->parse('day_header_listing', 'day_header', true);
                            }

                            $this->setVar([
                                'PERIOD_DESC'    => '',
                                'PERIOD_CODE'    => '',
                                'PERIOD_ROOM'    => '',
                                'PERIOD_ONCLICK' => '',
                                'PERIOD_STAFF'   => '',
                                'PERIOD_COLOUR'  => '',
                                'PERIOD_URL'     => '',
                                'DAY_CLASS'      => '',
                            ]);

                            // current period, also current day: intersection
                            if ($is_current_period && $dayCount == $dayCurrent) {
                                $this->setVar('DAY_CLASS', 'timetable-subject-active');
                            }

                            $this->setVar('period_class_link_block', '');
                            $this->setVar('period_class_block', '');


                            /**
                             * Indexed by "CampusCode|ClassCode"
                             *
                             * @var Schoolbox\Resource\Folder\Model[]|null[] $catCache
                             */
                            $catCache = [];
                            if (count($period) > 0) {
                                foreach ($period as $class) {
                                    $cacheKey = ($class['campus'] ?? '') . $class['code'];

                                    /* Handle colour array */
                                    if ($class['code'] && !array_key_exists($cacheKey, $subjectColourMap)) {
                                        // not enough colors: loop around to index 0.
                                        if (!$colourList->offsetExists($colourIndex)) {
                                            $colourIndex = 0;
                                        }
                                        $subjectColourMap[$cacheKey] = $colourList[$colourIndex]->getHexColour();
                                        $colourIndex++;
                                    }

                                    // format teacher output
                                    if (!isset($staffIdNameMap[$class['staffID']])) {
                                        $name = '';
                                        try {
                                            $classStaff = $userMapper->findByExternalId($class['staffID'], Role::STAFF);
                                            $name       = $classStaff->getFullName();
                                        } catch (\Schoolbox\Mapper\Exception $e) {
                                        }
                                        // When failed to find the user, use staffSurname it exists
                                        if (empty($name) && !empty($class['staffSurname'] ?? '')) {
                                            $name = $class['staffSurname'];
                                            // Optionally prepend a title, if that exists
                                            if ($class['staffTitle']) {
                                                $name = "{$class['staffTitle']} {$name}";
                                            }
                                        }
                                        $staffIdNameMap[$class['staffID']] = $name;
                                    }

                                    /* Lookup subject code and check permissions, if read access provide a link to the user */
                                    if (!array_key_exists($cacheKey, $catCache)) {
                                        $catCache[$cacheKey] = $categoryMapper->findForCampusAndClass($class['campus'] ?? '', $class['code']);
                                    }

                                    if ($class['description']) {
                                        $description = $class['description'];
                                    } elseif (isset($catCache[$cacheKey])) {
                                        $description = $catCache[$cacheKey]->getName();
                                    } else {
                                        $description = $class['code'];
                                    }

                                    $this->setVar([
                                        'PERIOD_DESC'   => Scrub::Text($description),
                                        'PERIOD_CODE'   => '(' . Scrub::Text($class['code']) . ')',
                                        'PERIOD_ROOM'   => Scrub::Text($class['room']),
                                        'PERIOD_STAFF'  => Scrub::Text($staffIdNameMap[$class['staffID']]),
                                        'PERIOD_COLOUR' => Scrub::text($subjectColourMap[$cacheKey]),
                                    ]);

                                    if (isset($catCache[$cacheKey]) && $groupHandler->getPermission($catCache[$cacheKey]->getId()) >= UserAccess::ACCESS_READ) {
                                        // Yes you really do need to double url-encode this
                                        $this->setVar(
                                            'PERIOD_URL',
                                            Scrub::text(
                                                '/homepage/code/' . urlencode(urlencode($catCache[$cacheKey]->getMatchedCode()))
                                            )
                                        );
                                        $this->parse('period_class_link_block', 'period_class_link', true);
                                    } else {
                                        $this->parse('period_class_block', 'period_class', true);
                                    }
                                }
                            } else {
                                $this->setVar('period_class_link_block', '');
                                $this->parse('period_class_block', 'period_class');
                            }

                            $this->parse('day_listing', 'day', true);
                            $dayCount++;
                        }

                        $this->parse('period_listing', 'period', true);
                        $this->setVar('day_listing', '');
                        $periodCount++;
                    }
                }
                $this->parse('timetable_listing', 'timetable', true);

                //reset blocks in case we coming back...
                $this->setVar('period_listing', '');
                $this->setVar('day_header_listing', '');
            }
        }
    }
}

if ($numTimetables > 1) {
    $this->setVar([
        'NUM_PEOPLE_CLASS' => '',
    ]);
} else {
    $this->setVar([
        'NUM_PEOPLE_CLASS' => 'hide',
    ]);
}

if ($numTimetables) {
    $this->setVar([
        'no_timetable_block' => '',
    ]);
} else {
    $this->setVar('timetable_listing', '');
    $this->parse('no_timetable_block', 'no_timetable');
}
