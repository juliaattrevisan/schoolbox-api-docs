<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Core_Nav_Sidebar {

    private $user;
    private $agenda;
    private $subjects;
    private $groups;
    private $work;
    private $more;

    public function __construct(GizaUser $user) {
        $this->user = $user;
    }

    public function createAgenda($items = 'all') {

        if(is_array($items) && count($items)) {
            $agenda = array(
                'dashboard' => array('facade/dashboard/display', 'home'),
                'timetable' => array('timetable/display',        'clock'),
                'calendar'  => array('calendar/month',           'calendar'),
            );

            $list = array();

            // GENERATE AGENDA
            foreach($items as $item) {
                $list[] = array(
                    'value' => ucfirst($item),
                    'icon'  => $agenda[$item][1],
                    'href'  => $agenda[$item][0],
                );
            }

            $this->agenda = $list;
        }

        elseif($items == 'all') {
            return $this->createAgenda(array('dashboard', 'timetable', 'calendar'));
        }

        return array();

    }

    public function createSubjectList() {
        $subjects = $this->user->getSubjects();

        $list = array(array('type' => '__SEPARATOR__', 'value' => 'Your Subjects'));

        if($subjects->count()) {
            // GENERATE SUBJECT LIST
            foreach($subjects as $cat) {
                $list[] = $this->createSubject($cat);
            }
        }

        $this->subjects = $list;
    }

    public function createSubject(GizaCategory $cat, $items = 'all') {

        if(is_array($items) && !empty($items)) {
            $code = $cat->__isset('code') ? " ({$cat->get('code')})" : '';
            $cid = $cat->get('id');
            $itemMap = array(
                'files'     => 'Files',
                'news'      => 'News',
                'due_work'  => 'Due Work',
                'grades'    => 'Recent Grades',
                'links'     => 'Links',
                'forums'    => 'Forums',
                'folders'   => 'Folders',
            );

            $subject = array(
                'value' => $cat->get('name') . $code,
                'href'  => "facade/homepage/display/$cid",
                'items' => array()
            );

            foreach($items as $item) {
                if(isset($itemMap[$item])) {
                    $subject['items'][] = array(
                        'value' => $itemMap[$item],
                        'href'  => "facade/homepage/$item/$cid"
                    );
                }
            }

            return $subject;
        }

        elseif($items == 'all') {
            return $this->createSubject($cat, array('files', 'news', 'due_work', 'grades', 'links', 'forums', 'folders'));
        }

        return array();
    }

    public function createGroupList() {
        $groups = $this->user->getGroups();

        $list = array(array('type' => '__SEPARATOR__', 'value' => 'Your Groups'));

        if($groups->count()) {
            // GENERATE GROUP LIST
            foreach($groups as $cat) {
                $list[] = $this->createGroup($cat);
            }
        }

        $this->subjects = array_merge($this->subjects, $list);
    }

    public function createGroup(GizaCategory $cat, $items = 'all') {
        if(is_array($items) && !empty($items)) {
            $code = $cat->__isset('code') ? " ({$cat->get('code')})" : '';
            $cid = $cat->get('id');
            $itemMap = array(
                'files'     => 'Files',
                'news'      => 'News',
                'due_work'  => 'Due Work',
                'grades'    => 'Recent Grades',
                'links'     => 'Links',
                'forums'    => 'Forums',
                'folders'   => 'Folders',
            );

            $subject = array(
                'value' => $cat->get('name'),
                'href'  => "facade/homepage/display/$cid",
                'items' => array()
            );

            foreach($items as $item) {
                if(isset($itemMap[$item])) {
                    $subject['items'][] = array(
                        'value' => $itemMap[$item],
                        'href'  => "facade/homepage/$item/$cid"
                    );
                }
            }

            return $subject;
        }

        elseif($items == 'all') {
            return $this->createSubject($cat, array('files', 'news', 'due_work', 'grades', 'links', 'forums', 'folders'));
        }

        return array();
    }

    public function createUpcomingWork() {

        $subjects = $this->user->getSubjects();

        // GENERATE UPCOMING WORK
        $subHandler = new Submission();

        $categoryIDList = array();
        if ($subjects->count()) {
            foreach($subjects as $category) {
                $categoryIDList[] = $category->get('id');
            }
        }

        $work = array();

        //Catch if not involved in any categories
        if (count($categoryIDList)) {

            // set how many to show
            $allWork = array();

            $uid = $this->user->get('uid');

            $works = $subHandler->getRecentWork($uid, $categoryIDList, UPCOMING_WORK_TYPE);
            for ($i = 0; (is_array($works) && $i < count($works)); $i++) {
                $works[$i]['owner'] = $uid;
                $allWork[] = $works[$i];
            }

            usort($allWork, 'cmp');

            if(count($allWork) && is_array($allWork)) {
                $prev = '';
                $now = new DateTime();
                foreach($allWork as $sub) {
                    $date = $sub['date']->format(Config::get('date_short'));
                    $status = ($sub['date'] > $now) ? 'Upcoming Work Due' : ($sub['late_date'] > $now ? 'Overdue Work' : 'Missed Deadlines');
                    if($status != $prev) {
                        $work[] = array(
                            'value' => $status,
                            'type'  => '__SEPARATOR__'
                        );
                        $prev = $status;
                    }
                    $work[] = array(
                        'value'  => "{$sub['name']}",
                        'date'   => $date,
                        'status' => $status,
                        'href'   => "due_work/submission_box/display/{$sub['bid']}",
                    );
                }
                $this->work = $work;
            }
        }
    }

    public function createSettings() {
        $this->more = array(
            array(
                'value' => 'Settings',
                'href'  => 'core/settings/preferences'
            ),
            array(
                'value' => 'Logout',
                'href'  => '/m/core/auth/logout'

            ),
        );
    }

    public function getAll() {

        $opts = array(
            'agenda'    => 'Agenda',
            'subjects'  => 'Categories',
            'work'      => 'Due Work',
            'more'      => 'More',
        );

        $output = array();

        foreach($opts as $k => $v) {
            if(is_array($this->$k)) {
                $output[$k] = array(
                    'value' => $v,
                    'items' => $this->$k
                );
            }
        }

        return $output;
    }
}

function cmp($a, $b) {
    if ($a['relevant_date'] == $b['relevant_date'])
        return 0;
    else if ($a['relevant_date'] > $b['relevant_date'])
        return 1;
    return -1;
}
