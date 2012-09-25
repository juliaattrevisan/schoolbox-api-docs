<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2002-2003 Lektec Consulting         *
 *                                                           *
 ************************************************************/
//timetable object that displays the users timetable
require_once '../library/user.php';
require_once '../library/group.php';
require_once '../library/category.php';

//synergy not enabled do not display page
if (!Cfg::get('external_enable')) {
    error(19);
}

$groupHandler = new Group;
$userHandler = new User;
$timetableHandler = new External_Timetable;
$categoryHandler = new Category;

$this->setFile(TEMPLATE_content, 'class/timetable.html');
$this->setTitle('Timetable');
$this->setTab('timetable');

$this->setBlock(TEMPLATE_content, "timetable", "timetable_listing");
$this->setBlock("timetable", "period", "period_listing");
$this->setBlock("period", "day", "day_listing");
$this->setBlock("timetable", "day_header", "day_header_listing");
$outputList = false;

/* Fetch list of homepage colours for use */
$colourList = Timetable_ColourMapper::getInstance()->findAll();


if ($groupHandler->isParent()) {
    if (is_array($_SESSION['children'])) {
        foreach ($_SESSION['children'] as $child) {
            $outputList[] = $userHandler->getByID($child['id']);
        }
    }
} else {
    if ($this->arg[0]) {
        if ($groupHandler->isStaff()) {
            $outputList[] = $userHandler->getByID($this->arg[0]);
        } else {
            error(19);
        }
    } else {
        $outputList[] = $userHandler->getByID($_SESSION['id']);
    }
}


if (is_array($outputList)) {
    foreach ($outputList as $user) {

        /* Setup colour map */
        $subjectColourMap = array();
        $colourIndex = 0;

        $outputUser = array(
            'PERSON' => $userHandler->formatName($user),
            'YEAR' => $user['year'] ? ', Year ' . $user['year'] : '',
        );
        $this->setVar($outputUser);

        $staffFlag = $groupHandler->isStaff($user['uid']);

        $periodCurrent = $timetableHandler->getPeriodNumber($user['campus']);
        $dayCurrent = $timetableHandler->getDayNumber($user['campus']);

        $timetable = $timetableHandler->getFullTimetable($user['synergy_id'], $user['uid'], $user['campus']);

        if (is_array($timetable) && !empty($timetable)) {
            $days = count($timetable[1]['day']);

            $this->setVar('TIMETABLE_FIELD_WIDTH', round((95 / ($days + 1)), 1));
            $periodCount = 1;
            //for each period
            foreach ($timetable as $row) {

                $outputPeriodDefinition = array(
                    'PERIOD_NAME' => $row['periodName'],
                    'PERIOD_START' => $row['periodStart'],
                    'PERIOD_END' => $row['periodEnd'],
                );
                $this->setVar($outputPeriodDefinition);

                $dayCount = 1;
                //for each day
                foreach ($row['day'] as $period) {
                    if ($periodCount == 1) // only output header on first run through
                    {
                        //output day header
                        $this->setVar("DAY_NUMBER", $dayCount);
                        $this->parse("day_header_listing", "day_header", true);
                    }

                    $outputPeriod = array(
                        'PERIOD_DESC' => '',
                        'PERIOD_CODE' => '',
                        'PERIOD_ROOM' => '',
                        'PERIOD_ONCLICK' => '',
                        'PERIOD_STAFF' => '',
                        'PERIOD_COLOUR' => '',
                    );

                    if ($period['description']) {

                        /* Handle colour array */
                        if ($period['code'] && !array_key_exists($period['code'], $subjectColourMap)) {
                            $subjectColourMap[$period['code']] = $colourList[$colourIndex]->hex_colour;
                            $colourIndex++;
                        }

                        // format teacher output

                        // if not staff and external db is enabled
                        if (!$staffFlag && Cfg::get('external_enable')) {
                            switch (Cfg::get('external_type')) {
                                case 'synergetic':
                                    $period_staff = $userHandler->formatName(
                                        array(
                                            'title' => $period['staffTitle'],
                                            'firstname' => $period['staffFirstname'],
                                            'lastname' => $period['staffSurname']
                                        )
                                    );
                                    break;
                                case 'tass':
                                    $period_staff = $period['staffCode'];
                                    break;
                                case 'denbigh':
                                case 'schoolbox':
                                default:
                                    $period_staff = '';
                                    break;
                            }
                        } // staff, or external_db is disabled
                        else {
                            $period_staff = '';
                        }

                        $outputPeriod = array(
                            'PERIOD_DESC' => Scrub::Text($period['description']),
                            'PERIOD_CODE' => '(' . Scrub::Text($period['code']) . ')',
                            'PERIOD_ROOM' => Scrub::Text($period['room']),
                            'PERIOD_STAFF' => Scrub::Text($period_staff),
                            'PERIOD_ONCLICK' => '',
                            'PERIOD_COLOUR' => '#' . $subjectColourMap[$period['code']],
                        );


                        /* Lookup subject code and check permissions, if read access provide a link to the user */
                        if (!isset($catCache[$period['code']])) {
                            $cat = $categoryHandler->getByCode($period['code']);
                            $catCache[$period['code']] = $cat['id'];
                        }

                        if ($catCache[$period['code']] && $groupHandler->getPermission(
                            $catCache[$period['code']]
                        ) >= READ_ACCESS
                        ) {
                            $outputPeriod['PERIOD_ONCLICK'] = 'onClick="window.location=\'/homepage/' . urlencode(
                                $period['code']
                            ) . '\'"';
                        }

                    }

                    if ($dayCount == $dayCurrent) {
                        if ($row['periodNumber'] == $periodCurrent['PeriodNumber'] && $row['periodSequence'] == $periodCurrent['PeriodNumberSeq']) {
                            $outputPeriod['TIMETABLE_CLASS'] = 'dayCurrent current';
                        } else {
                            $outputPeriod['TIMETABLE_CLASS'] = 'dayCurrent';
                        }
                    } else {
                        $outputPeriod['TIMETABLE_CLASS'] = 'normal';
                    }

                    $this->setVar($outputPeriod);

                    $this->parse('day_listing', 'day', true);
                    $dayCount++;
                }

                $this->parse('period_listing', 'period', true);
                $this->setVar('day_listing', '');
                $periodCount++;
            }
            $this->parse('timetable_listing', 'timetable', true);

            //reset blocks in case we coming back...
            $this->setVar('period_listing', '');
            $this->setVar('day_header_listing', '');
        } else {
            $this->setVar('timetable_listing', '');
        }

    }

}





?>
