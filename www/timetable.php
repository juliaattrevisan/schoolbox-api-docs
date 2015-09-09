<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2002-2003 Lektec Consulting         *
 *                                                           *
 ************************************************************/
//timetable object that displays the users timetable

if (!isset($this)) {
    require_once dirname(__FILE__) . '/../include/Bootstrap.php';
    SitePage::localRedir('/timetable/');
}

//synergy not enabled do not display page
if (!Cfg::get('external_enable')) {
    error(19);
}

$groupHandler     = new Group;
$userHandler      = new User;
$timetableHandler = new External_Timetable;
$categoryMapper   = new \Schoolbox\Resource\CategoryMapper();
$userMapper       = new \Schoolbox\UserMapper();

$this->setFile(TEMPLATE_content, 'class/timetable.html');
$this->setTitle('Timetable');
$this->setTab('timetable');

$this->setBlock(TEMPLATE_content, "no_timetable", "no_timetable_block");
$this->setBlock(TEMPLATE_content, "timetable", "timetable_listing");
$this->setBlock(TEMPLATE_content, 'timetable_selector', 'timetable_selector_block');
$this->setBlock("timetable", "period", "period_listing");
$this->setBlock("period", "day", "day_listing");
$this->setBlock('day', 'period_class', 'period_class_block');
$this->setBlock('day', 'period_class_link', 'period_class_link_block');
$this->setBlock("timetable", "day_header", "day_header_listing");

/* Fetch list of homepage colours for use */
$colourList = Timetable_ColourMapper::getInstance()->findAll();

/** @var Schoolbox\User[] $userList */
$userList = [];

$child_id = $this->arg[0];
if ($groupHandler->isParent()) {
    if (is_array($_SESSION['children'])) {
        foreach ($_SESSION['children'] as $child) {
            //
            // Was a specific child ID requested? show them only, otherwise
            // show everyone.
            $checked_campus_list = array();
            if (!$child_id || ($child_id == $child['id'])) {
                $userList[] = $userMapper->findById($child['id']);
            }
        }
    }
    //
    // Requesting a child that isn't yours? Access Denied
    if (empty($userList)) {
        error(19);
    }
} else {
    if ($child_id) {
        if ($groupHandler->isStaff() || $child_id == $_SESSION['id']) {
            //Output a timetable for each campus user is involved in
            $userList[] = $userMapper->findById($child_id);
        } else {
            error(19);
        }
    } else {
        // Output my own timetable (all campuses)
        /** @var Schoolbox\User $userObject **/
        $userList[] = $_SESSION['user'];
    }
}

foreach ($userList as $user) {
    $checkedCampusList = [];
    foreach ($user->getCampusCollection() as $campus) {
        $campusCode = $campus->getExtTimetableCode();
        if (isset($checkedCampusList[$campusCode])) {
            continue;
        }
        $checkedCampusList[$campusCode] = true;

        /* Setup colour map */
        $subjectColourMap = array();
        $colourIndex      = 0;

        $outputUser = array(
            'PERSON' => $user->formatName(),
            'YEAR'   => $user->getYear() ? ', Year ' . $user->getYear() : '',
            'USER_ID' => $user->getId(),
        );

        $this->setVar($outputUser);
        $this->parse('timetable_selector_block', 'timetable_selector', true);

        $staffFlag = $user->getRole()->isStaff();

        $periodCurrent = $timetableHandler->getPeriodNumber($campus);
        $dayCurrent    = $timetableHandler->getDayNumber($campus);

        try {
            $timetable = $timetableHandler->getFullTimetable($user->getExternalId(), $user->getId(), $campus);
        } catch (\Schoolbox\External\Exception $e) {
            error(22);
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
                            }
                        }
                    }
                }
            }
        }

        if (is_array($timetable) && !empty($timetable) && $classCount) {
            $numTimetables++;

            $days = count($timetable[1]['day']);

            $this->setVar('TIMETABLE_FIELD_WIDTH', round((95 / ($days + 1)), 1));
            $periodCount = 1;
            //for each period
            foreach ($timetable as $row) {
                if ($row['periodSequence'] == 1) {
                    $is_current_period      =
                        $row['periodNumber'] == $periodCurrent['PeriodNumber']
                        && $row['periodSequence'] == $periodCurrent['PeriodNumberSeq'];
                    $outputPeriodDefinition = array(
                        'PERIOD_NAME'  => $row['periodName'],
                        'PERIOD_START' => $row['periodStart'],
                        'PERIOD_END'   => $row['periodEnd'],
                        'PERIOD_CLASS' => ($is_current_period ? 'timetable-period-active' : ''),
                    );
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

                        $outputPeriod = array(
                            'PERIOD_DESC'    => '',
                            'PERIOD_CODE'    => '',
                            'PERIOD_ROOM'    => '',
                            'PERIOD_ONCLICK' => '',
                            'PERIOD_STAFF'   => '',
                            'PERIOD_COLOUR'  => '',
                            'PERIOD_URL'     => '',
                            'DAY_CLASS'      => '',
                        );

                        if ($is_current_period) {
                            if ($dayCount == $dayCurrent) {
                                // current period, also current day: intersection
                                $this->setVar('DAY_CLASS', 'timetable-subject-active');
                            } else {
                                // current period, not current day: display row
                                $this->setVar('DAY_CLASS', '');
                            }
                        } else {
                            if ($dayCount == $dayCurrent) {
                                // not current period, but is current day: display column
                                $this->setVar('DAY_CLASS', '');
                            } else {
                                // neither current period nor current day
                                $this->setVar('DAY_CLASS', '');
                            }
                        }

                        $this->setVar('period_class_link_block', '');
                        $this->setVar('period_class_block', '');

                        if (count($period) > 0) {
                            foreach ($period as $class) {
                                /* Handle colour array */
                                if ($class['code'] && !array_key_exists($class['code'], $subjectColourMap)) {
                                    // not enough colors: loop around to index 0.
                                    if (!$colourList->offsetExists($colourIndex)) {
                                        $colourIndex = 0;
                                    }
                                    $subjectColourMap[$class['code']] = $colourList->offsetGet($colourIndex)->getHexColour();
                                    $colourIndex++;
                                }

                                // format teacher output
                                $period_staff = '';

                                // if not staff and external db is enabled
                                if (!$staffFlag && Cfg::get('external_enable')) {
                                    switch (Cfg::get('external_type')) {
                                        case 'maze':
                                        case 'synergetic':
                                        case 'schoolbox':
                                            $period_staff = $userHandler->formatName(
                                                array(
                                                    'title'    => $class['staffTitle'],
                                                    'lastname' => $class['staffSurname']
                                                )
                                            );
                                            break;
                                        case 'tass':
                                            $period_staff = $class['staffCode'];
                                            break;
                                        case 'denbigh':
                                        default:
                                            break;
                                    }
                                } // staff, or external_db is disabled

                                $outputPeriod = array(
                                    'PERIOD_DESC'   => Scrub::Text($class['description']),
                                    'PERIOD_CODE'   => '(' . Scrub::Text($class['code']) . ')',
                                    'PERIOD_ROOM'   => Scrub::Text($class['room']),
                                    'PERIOD_STAFF'  => Scrub::Text($period_staff),
                                    'PERIOD_COLOUR' => '#' . Scrub::text($subjectColourMap[$class['code']]),
                                );

                                /* Lookup subject code and check permissions, if read access provide a link to the user */
                                if (!isset($catCache[$class['code']])) {
                                    try {
                                        $cat                      = $categoryMapper->findByCode($class['code']);
                                        $catCache[$class['code']] = $cat->getId();

                                    } catch (\Schoolbox\Mapper\Exception $e) {
                                        //Subject not found
                                    }
                                }

                                $this->setVar($outputPeriod);

                                if ($catCache[$class['code']] && $groupHandler->getPermission($catCache[$class['code']]) >= READ_ACCESS) {
                                    // Yes you really do need to double url-encode this
                                    $this->setVar(
                                        'PERIOD_URL',
                                        Scrub::text(
                                            '/homepage/code/' . urlencode(urlencode($class['code']))
                                        )
                                    );
                                    $this->parse('period_class_link_block', 'period_class_link', true);
                                } else {
                                    $this->parse('period_class_block', 'period_class', true);
                                }

                            }

                        } else {
                            $this->setVar($outputPeriod);
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
        } elseif ($days == 0) {
            //In case no timetable has been output for what ever reason
            $this->setVar('timetable_listing', '');
        }
    }
}

if ($numTimetables) {
    $this->setVar(array(
        'no_timetable_block' => ''
    ));
}
else {
    $this->parse('no_timetable_block', 'no_timetable');
}
