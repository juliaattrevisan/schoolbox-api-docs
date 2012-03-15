<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Report_Group extends Report_Abstract {

    private $groupID = null;
    private $columns = array(
        'title',
        'firstname',
        'lastname',
        'campus',
        'email',
        'sb_modify',
        'sb_timetable'
    );

    public function __construct($page) {
        $this->setPermissions();
        $this->setGroupHandler(new Group());

        if (!$this->checkPermissions()) {
            error(19);

        } else {
            $this->setPage($page);

            $this->setUserHandler(new User());

            $this->setup();
        }
    }

    protected function setup() {
        // which html file to use as template
        //$this->getPage()->setFile(TEMPLATE_content, 'modules/report/group.html');

        // set blocks
        $this->getPage()->setBlock(TEMPLATE_content, 'group', 'group_block');
        $this->getPage()->setVar(array('group_block' => ''));

        $this->getPage()->setTitle('Group Report');

    }

    public function setGroupID($groupID) {
        $this->groupID = $groupID;
    }

    public function getGroupID() {
        return $this->groupID;
    }

    public function setColumns($cols) {
        $this->columns = $cols;
    }

    public function addColumns($cols) {
        array_merge($this->columns, $cols);
    }

    public function getColumns() {
        return $this->columns;
    }

    public function render() {
        // the group must not be null
        if($this->getGroupID() == null)
            return;

        // bring back a list of users in the requested group
        $result = $this->getUserHandler()->getByGroup($this->getGroupID(), false);

        $page = $this->getPage();

        $groupHandler = new Group();
        $groupName = $groupHandler->getGroupName($this->getGroupID());

        // render users
        if (!empty($result)) {
            $page->setFile('user_t', 'adminv2/report/group.html');
            $page->setBlock('user_t', 'user', 'user_block');
            $page->setBlock('user', 'timetable', 'timetable_block');
            $page->setBlock('user', 'modify', 'modify_block');
            $page->setVar(array('USER_DISP_COUNT' => count($result)));

            foreach ($result as $user) {
                // Format email address
                $emailObj = new Email();
                $address = $emailObj->formatEmailFromArray($user);

                if ($address) {
                    if (Cfg::get('site_location') == 'wenona') {
                        $email = "<a href=\"{$_SESSION['mailhandle']}$address\">Send Email</a>";
                    }
                    else {
                        $email = "<a href=\"{$_SESSION['mailhandle']}$address\">$address</a>";
                    }
                }
                else {
                    $email = '';
                }

                $groupHandler = new Group();

                $page->setVar('USER_GROUP', $groupName);

                /* If a parent is performing the search, remove the email address
                */
                if ($groupHandler->isParent()) {
                    unset($email);
                }

                $page->setVar(array(
                    'USER_ID'         => $user['id'],
                    'USER_TITLE'      => $user['title'],
                    'USER_FIRSTNAME'  => $user['firstname'],
                    'USER_SURNAME'    => $user['lastname'],
                    'USER_EMAIL'      => $email,
                    'USER_YEAR'       => empty($user['year']) ? '' : "Year {$user['year']}",
                    'USER_CAMPUS'     => stristr($user['group_name'], $user['campus_name'])?'':$user['campus_name'],
                ));

                $page->setVar('timetable_block');

                //Show student timetables to staff and parents of child
                if($this->getGroupHandler()->isStudent($user['id'])) {
                    if ($this->getGroupHandler()->isStaff() || $this->getGroupHandler()->isParentOfChild($user['id'])) {

                        /* Timetable shouldn't appear if synergy is disabled in the conf
                        */
                        if (Cfg::get('external_enable') && $user['synergy_id']) {
                            $page->parse('timetable_block', 'timetable');
                        }
                        else {
                            $page->setVar('timetable_block', '');
                        }
                    }
                }
                else {
                    //Show staff timetables to other staff
                    if($this->getGroupHandler()->isStaff() && $this->getGroupHandler()->isStaff($user['id']))
                        $page->parse('timetable_block', 'timetable');
                }

                if(!$_SESSION['su'])
                    $page->setVar('modify_block');
                else
                    $page->parse('modify_block', 'modify');

                $page->parse('user_block', 'user', true);
            }

            $page->parse('RESULT', 'user_t', true);

        } else {
            $page->setVar('RESULT', 'No results.');
        }

    }


}
