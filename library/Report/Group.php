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

    // csv column mapping: index => humanreadable
    private $columns = array(
        'title'     => 'title',
        'firstname' => 'firstname',
        'lastname'  => 'lastname',
        'campus'    => 'campus',
        'alt_email' => 'email'
    );

    public function __construct($page) {
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
        $this->getPage()->setFile('user_t', 'adminv2/report/group.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'group', 'group_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');
        $this->getPage()->setBlock('user_t', 'user', 'user_block');
        $this->getPage()->setBlock('user', 'modify', 'modify_block');
        $this->getPage()->setVar(array('group_block' => ''));
        $this->getPage()->setTitle('Group Report');

    }

    public function setGroupID($groupID) {
        $this->groupID = $groupID;
    }

    public function getGroupID() {
        return $this->groupID;
    }

    public function getGroupName() {
        return $this->getGroupHandler()->getGroupName($this->getGroupID());
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
            error(19);

        $page = $this->getPage();

        if(!is_numeric($page->arg[2]) || empty($page->arg[2]) || $page->arg[2] < 1)
            $pageNum = 1;
        else
            $pageNum = $page->arg[2];

        $perPage = Cfg::get('search_type_limit') * 2;

        // bring back a list of users in the requested group
        $fullResult = $this->getUserHandler()->getByGroup($this->getGroupID(), false);
        $visibleResult = $this->getUserHandler()->getSelectionByGroup($this->getGroupID(), false, ($pageNum - 1) * $perPage, $perPage);

        $pageCount = ceil(count($fullResult) / $perPage);

        // figure out which pagination buttons should be displayed
        if($pageNum < 2)
            $page->setVar('previous_button_block', '');
        else
            $page->parse('previous_button_block', 'previous_button');

        if($pageNum >= $pageCount)
            $page->setVar('next_button_block', '');
        else
            $page->parse('next_button_block', 'next_button');

        $pageSlug = "group/{$page->arg[1]}/";

        if($pageCount > 1) {
            for($j = 1; $j <= $pageCount; $j++) {
                if($pageNum == $j)
                    $page->setVar('CURRENT', ' current');
                else
                    $page->setVar('CURRENT', '');

                $page->setVar('PAGE_INDEX', $j);
                $page->setVar('PAGE_INDEX_REF', $pageSlug . $j);

                $page->parse('page_number_block','page_number', true);
            }
        }
        else
            $page->setVar('page_number_block', '');

        $outputPagination = array(
            'PREVIOUS_PAGE' => $pageSlug . ($pageNum - 1),
            'NEXT_PAGE'		=> $pageSlug . ($pageNum + 1),
            'LAST_PAGE'		=> $pageSlug . $pageCount,
        );

        $page->setVar($outputPagination);

        $groupHandler = new Group();
        $groupName = $groupHandler->getGroupName($this->getGroupID());

        // render users
        if (!empty($visibleResult)) {

            $page->setVar(array(
                'USER_DISP_COUNT' => count($fullResult),
                'USER_GROUP'      => $groupName
            ));

            foreach ($visibleResult as $user) {
                // Format email address
                $emailObj = new Email();
                $address = $emailObj->formatEmailFromArray($user);
                $email = $address ? "<a href=\"{$_SESSION['mailhandle']}$address\">$address</a>" : '';

                $page->setVar(array(
                    'USER_ID'         => $user['id'],
                    'USER_TITLE'      => $user['title'],
                    'USER_FIRSTNAME'  => $user['firstname'],
                    'USER_SURNAME'    => $user['lastname'],
                    'USER_EMAIL'      => $email,
                    'USER_YEAR'       => empty($user['year']) ? '' : "Year {$user['year']}",
                    'USER_CAMPUS'     => stristr($user['group_name'], $user['campus_name'])?'':$user['campus_name'],
                ));

                if(!$_SESSION['su'])
                    $page->setVar('modify_block');
                else
                    $page->parse('modify_block', 'modify');

                $page->parse('user_block', 'user', true);
            }

            $page->parse('RESULT', 'user_t', true);

        } else {
            $page->setVar(array(
                'RESULT'                => 'No results.',
                'USER_DISP_COUNT'       => count($fullResult),
                'USER_GROUP'            => $groupName,
                'previous_button_block' => '',
                'next_button_block'     => '',
                'page_number_block'     => ''
            ));
        }

    }

    public function export() {
        $exporter = new Report_Exporter($this->getGroupName());
        $exporter->setHeadings($this->getColumns());
        $exporter->setData($this->getUserHandler()->getByGroup($this->getGroupID(), false));
        $exporter->export('CSV');
    }
}
