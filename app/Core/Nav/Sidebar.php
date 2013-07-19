<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;

class Core_Nav_Sidebar {

    private $user;
    private $agenda;
    private $email;
    private $subjects = array();
    private $groups;
    private $work;
    private $more;

    public function __construct(User $user) {
        $this->user = $user;
    }

    public function createAgenda($items = 'all') {

        if(is_array($items) && count($items)) {
            $agenda['dashboard'] = array('facade/dashboard/display', 'home');
            $agenda['timetable'] = array('timetable/display', 'clock');
            $agenda['calendar'] = array('calendar/month', 'calendar');
            $agenda['news'] = array('news/school', 'news');

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
            if($_SESSION['timetable'] == 1) {
                return $this->createAgenda(array('dashboard', 'timetable', 'calendar', 'news'));
            }
            else {
                return $this->createAgenda(array('dashboard', 'calendar', 'news'));
            }
        }

        return array();

    }

    /**
     * Quick and nasty implementation of webmail link in mobile, to be replaced [SB-1724]
     *
     * @deprecated
     * @return array
     */
    public function createWebmail() {
        // ensure there's some manner of protocol on the target address, otherwise it will hit local
        $addr = (strpos(Cfg::get('mail_webmail'), 'http') === false ? 'http://' : '') . Cfg::get('mail_webmail');

        $this->email = array(array(
            'href' => $addr,
            'value' => 'Launch Webmail'
        ));
        return array();
    }


    public function createSubjectList() {
        try {
            $subjects = $this->user->getSubjectCollection();
	        $count = $subjects->count();
            if ($count > 0) {
                $this->subjects = array_merge(
                    $this->subjects,
                    array(array('type' => '__SEPARATOR__', 'value' => 'Your Subjects')),
                    $this->getSubjectsForPopulation($subjects)
                );
            }
        }
        catch(ErrorException $e) {
            // TODO log this somewhere, it will likely mean synergy is down
        }
        catch(Exception_Schoolbox $e) {
            // TODO log this somewhere, it will likely mean synergy is down
        }
    }

	/**
	 * @param Category $cat
	 * @param string $items
	 * @return array
	 */
	public function createSubject($cat, $items = 'all') {

        if(is_array($items) && !empty($items)) {
            $code = $cat->getCodes()->count() ? " ({$cat->getCodes()->first()->getCode()})" : '';
            $cid = $cat->getId();
            $itemMap = array(
                'news'      => 'News',
                'due_work'  => 'Due Work',
                'grades'    => 'Recent Grades',
                'links'     => 'Links',
                'folders'   => 'Folders',
            );

            $subject = array(
                'value' => $cat->getName() . $code,
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
        try {
	        // get the group collection
	        /**
	         * @var CategoryMapper $catMapper
	         */
	        $catMapper = CategoryMapper::getInstance();
	        $groups = $catMapper->findForUser($this->user, false);

            if ($groups->count()) {
                $this->subjects = array_merge(
                    $this->subjects,
                    array(array('type' => '__SEPARATOR__', 'value' => 'Your Groups')),
                    $this->getSubjectsForPopulation($groups)
                );
            }
        }
        catch(Exception_Schoolbox $e) {
            // TODO log this somewhere, it will likely mean synergy is down
        }
    }

    /**
     * Gets list of subjects for population into this object's list of subjects.
     *
     * @param $subject_list
     *
     * @return array
     */
    protected function getSubjectsForPopulation($subject_list) {
        $list = array();
        foreach ($subject_list as $cat) {
            /**
             * Don't include categories that don't have a homepage (ie. template == 0)
             * And account for loose typing ($cat could be an array or Category object)
             * @var Category $cat
             */
	        if(!($cat instanceof Category)) {
		        $cat = CategoryMapper::getInstance()->findById($cat['id']);
	        }
            if($cat->getTemplateId()) {
	            $list[] = $this->createSubject($cat);
            }
        }
        return $list;
    }

	/**
	 * @param Category $cat
	 * @param string $items
	 * @return array
	 */
	public function createGroup($cat, $items = 'all') {
        if(is_array($items) && !empty($items)) {
            $code = $cat->getCodes()->count() ? " ({$cat->getCodes()->first()->getCode()})" : '';
            $cid = $cat->getId();
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
                'value' => $cat->getName(),
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

        // GENERATE UPCOMING WORK
        $subHandler = new Submission();
        $upcoming = $subHandler->getAllUpcomingWorkAndAssessments($this->user->getId());

        $noWork = array(array(
            'value' => '<i>No upcoming work</i>',
            'href' => '#'
        ));

        if(!count($upcoming)) {
            $this->work = $noWork;
            return;
        }

        $work = array();
        $prev = '';
        $now = time();
        $j = 0;

        foreach($upcoming as &$item) {
            $invalid = false;

            if($item['kind'] == 0) {
                // duework
                if($item['submittable']) {
                    if($item['multi_submit']) {
                        // if unsubmitted, hide if LATE date passed
                        if(!$item['submit_count'] && $item['late_date'] < $now) {
                            $invalid = 'a';
                        }
                        // if submitted, hide if DUE date passed
                        elseif($item['submit_count'] && $item['date'] < $now) {
                            $invalid = 'b';
                        }
                    }
                    else {
                        // single submit: hide if completed or DUE date in past
                        if($item['date'] < $now || $item['submit_count']) {
                            $invalid = 'c';
                        }
                    }
                }
                else {
                    // no submissions: hide if DUE date in past
                    if($item['date'] < $now) {
                        $invalid = 'd';
                    }
                }
            }
            else {
                // assess
                if($item['max_attempts'] == 0) {
                    // 0 max attempts is unlimited: hide if FINISH in past
                    if($item['date'] < $now) {
                        $invalid = 'e';
                    }
                }
                elseif($item['attempt_count'] >= $item['max_attempts']) {
                    // if limited attempts, hide if limit has been reached
                    $invalid = 'f';
                }
                elseif($item['date'] < $now) {
                    $invalid = 'g';
                }
            }

            // skip if invalid for some reason
            if($invalid) {
                continue;
            }

            $adr = new Alaress_Date_Relative($item['date'], 'y-m-d');

            // how many days left, 0 <= x <= 10
            $item['urgency'] = max(0, min(10, (int)(($adr->getUnixTimestamp() - $now) / 86400)));
            $item['date_relative'] = $adr->getRelativeTime();

            $status = ($adr->getUnixTimestamp() > $now) ? 'Upcoming Work Due' : 'Overdue Work';
            if($status != $prev) {
                $work[] = array(
                    'value' => $status,
                    'type'  => '__SEPARATOR__'
                );
                $prev = $status;
            }

            $workitem = array(
                'value'  => $item['title'],
                'date'   => $item['date_relative'],
                'status' => $status,
            );

            if($item['kind'] == 0) {
                $workitem['href'] = 'due_work/submission_box/display/' . $item['id'];
            }
            else {
                $workitem['href'] = '#';
                $workitem['value'] .= ' <p>(Assessment)</p>';
            }

            $work[] = $workitem;

        }

        if(!count($work)) {
            $this->work = $noWork;
        }
        else {
            $this->work = $work;
        }


        return;
    }

    public function createSettings() {
        $this->more = array(
            array(
                'value' => 'Settings',
                'href'  => 'core/settings/preferences'
            ),
            array(
                'id'    => 'switch',
                'value' => 'Switch to Desktop',
                'href'  => '/switch.php?v=desktop',
                'rel'   => 'alternate',
            ),
            array(
                'id'    => 'logout',
                'value' => 'Logout',
                'href'  => 'core/auth/logout',
                'rel'   => 'alternate',
            ),
        );
    }

    public function getAll() {

        $opts = array(
            'agenda'   => 'Agenda',
            'email'    => 'Webmail',
            'subjects' => 'Categories',
            'work'     => 'Due Work',
            'more'     => 'More',
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
