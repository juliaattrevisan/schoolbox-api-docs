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
$this->setBlock('day', 'period_class', 'period_class_block');
$this->setBlock('day', 'period_class_link', 'period_class_link_block');
$this->setBlock("timetable", "day_header", "day_header_listing");
$outputList = array();

/* Fetch list of homepage colours for use */
$colourList = Timetable_ColourMapper::getInstance()->findAll();

$child_id = $this->arg[0];
if ($groupHandler->isParent()) {
    if (is_array($_SESSION['children'])) {
        foreach ($_SESSION['children'] as $child) {
            //
            // Was a specific child ID requested? show them only, otherwise
            // show everyone.
            if (!$child_id || ($child_id == $child['id'])) {
                $outputList[] = $userHandler->getByID($child['id']);
            }
        }
    }
    //
    // Requesting a child that isn't yours? Access Denied
    if ($child_id && empty($outputList)) {
        error(19);
    }

}
else {
    if ($child_id) {
        if ($groupHandler->isStaff() || $child_id == $_SESSION['id']) {
			//Output a timetable for each campus user is involved in
	        $user = $userHandler->getByID($child_id);
	        $campusList = $userHandler->getUserCampus($child_id);

	        foreach($campusList as $campus ) {
		        $user['campus'] = $campus;
		        $outputList[] = $user;
	        }
        }
        else {
            error(19);
        }
    }
    else {
	    //Output a timetable for each campus a staff is involved in
	    if($groupHandler->isStaff()) {
	        $user = $userHandler->getByID($_SESSION['id']);
	        $campusList = $userHandler->getUserCampus($_SESSION['id']);

	        foreach($campusList as $campus ) {
		        $user['campus'] = $campus;
		        $outputList[] = $user;
            }
	    }
		else {
			$outputList[] = $userHandler->getByID($_SESSION['id']);
		}
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
                $is_current_period =
                    $row['periodNumber'] == $periodCurrent['PeriodNumber']
                    && $row['periodSequence'] == $periodCurrent['PeriodNumberSeq']
                ;
                $outputPeriodDefinition = array(
                    'PERIOD_NAME'  => $row['periodName'],
                    'PERIOD_START' => $row['periodStart'],
                    'PERIOD_END'   => $row['periodEnd'],
                    'PERIOD_CLASS' => ($is_current_period ? 'dayCurrent' : 'normal'),
                );
                $this->setVar($outputPeriodDefinition);

                $dayCount = 1;
                //for each day
                foreach ($row['day'] as $period) {
                    // only output header on first run through
                    if ($periodCount == 1) {
                        //output day header
                        if ($dayCount == $dayCurrent) {
                            $this->setVar('TIMETABLE_CLASS', 'dayCurrent');
                        }
                        else {
                            $this->setVar('TIMETABLE_CLASS', 'normal');
                        }
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
                        'TIMETABLE_CLASS' => '',
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
                            'PERIOD_COLOUR' => '#' . $subjectColourMap[$period['code']],
                        );

                        /* Lookup subject code and check permissions, if read access provide a link to the user */
                        if (!isset($catCache[$period['code']])) {
                            $cat = $categoryHandler->getByCode($period['code']);
                            $catCache[$period['code']] = $cat['id'];
                        }
                    } else {
                        $outputPeriod = array(
                            'PERIOD_DESC' => '',
                            'PERIOD_CODE' => '',
                            'PERIOD_ROOM' => '',
                            'PERIOD_STAFF' => '',
                            'PERIOD_URL' => '',
                            'PERIOD_COLOUR' => '',
                        );
                    }

                    if ($is_current_period) {
                        if ($dayCount == $dayCurrent) {
                            // current period, also current day: intersection
                            $outputPeriod['TIMETABLE_CLASS'] = 'dayCurrent current';
                        } else {
                            // current period, not current day: display row
                            $outputPeriod['TIMETABLE_CLASS'] = 'dayCurrent';
                        }
                    } else {
                        if ($dayCount == $dayCurrent) {
                            // not current period, but is current day: display column
                            $outputPeriod['TIMETABLE_CLASS'] = 'dayCurrent';
                        } else {
                            // neither current period nor current day
                            $outputPeriod['TIMETABLE_CLASS'] = 'normal';
                        }
                    }

                    $this->setVar($outputPeriod);

                    if ($catCache[$period['code']] && $groupHandler->getPermission(
                        $catCache[$period['code']]
                    ) >= READ_ACCESS
                    ) {
                        $this->setVar('PERIOD_URL', '/homepage/' . $period['code']);
                        $this->parse('period_class_link_block', 'period_class_link');
                        $this->setVar('period_class_block', '');
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
            $this->parse('timetable_listing', 'timetable', true);

            //reset blocks in case we coming back...
            $this->setVar('period_listing', '');
            $this->setVar('day_header_listing', '');
        } else {
            $this->setVar('timetable_listing', '');
        }

    }

}