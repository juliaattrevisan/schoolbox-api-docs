<?php
/**
 * UserProfile
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SubjectList;

use \Schoolbox\Resource\Folder\Code\Model as CategoryCode;
use \Schoolbox\Resource\Folder\Code\Mapper as CategoryCodeMapper;

class UserProfile extends SubjectListAbstract
{

    /**
    * An instance of groupHandler
    *
    * @var null
    */
    private $groupHandler = null;

    /**
    * An instance of External_Class
    *
    * @var null
    */
    private $externalClassHandler = null;

    /**
    * An instance of userHandler
    *
    * @var null
    */
    private $userHandler = null;

    /**
    * Hook method
    *
    * Set up blocks and stuff
    */
    protected function setup()
    {
        $this->setGroupHandler(new \Group());
        $this->setExternalClassHandler(new \External_Class());
        $this->setUserHandler(new \User());

        $this->getPage()->setBlock(TEMPLATE_content, 'subject', 'subject_block');
        $this->getPage()->setBlock('subject', 'subject_row', 'subject_row_block');
        $this->getPage()->setBlock('subject', 'subject_mail', 'subject_mail_block');
        $this->getPage()->setBlock('subject_row', 'subject_item', 'subject_item_block');
        $this->getPage()->setBlock('subject_item', 'subject_teacher', 'subject_teacher_block');

        $this->getPage()->setVar(array(
            'subject_block' => '',
            'subject_row_block' => '',
            'subject_mail_block' => '',
            'subject_item_block' => '',
            'subject_teacher_block' => '',
        ));
    }

    /**
    * Expel each subject grouped by its type
    */
    public function render()
    {
        $emailObj = new \Email();

        /* Expel each subject grouped by its type
		 */
        foreach ($this->getAllSubjectLists() as $key => $currentSubjectList) {
            $emailAll = array();

            $this->getPage()->setVar(array(
                'SUBJECT_CLASSTYPE' => $key
            ));

            /* For all the classes in the current subject
			 */
            if (is_array($currentSubjectList) && count($currentSubjectList)) {
                $finalSubjectList = array();

                /* Preprocess list and remove items that should not exist if we cannot link to them
                 */
                foreach ($currentSubjectList as $currentSubject) {
                    try {
                        $category = $this->getCategoryMapper()->findByCode($currentSubject['subject']['code']);
                        $currentSubject['category']['id'] = $category->getId();
                        $currentSubject['category']['code'] = CategoryCodeMapper::getInstance()->createModel()->setCode($currentSubject['subject']['code']);
                        $finalSubjectList[] = $currentSubject;
                    } catch (\Exception $e) {
                        if (\Config::get('show_all_subjects') == 1) {
                            $finalSubjectList[] = $currentSubject; //output subject because show all subjects is enabled
                        }
                    }
                }

                $currentSubjectList = $finalSubjectList;
                if (is_array($currentSubjectList) && count($currentSubjectList)) {
                    foreach ($currentSubjectList as $currentSubject) {
                        $this->getPage()->setVar(array(
                            'subject_teacher_block' => ''
                        ));

                        if ($currentSubject['category']['code'] instanceof CategoryCode) {
                            $code = $currentSubject['category']['code']->getCode();
                        } else {
                            $code = $currentSubject['subject']['campusCode'].'/'.$currentSubject['subject']['code'];
                        }

                        // Yes, you DO have to urlencode() this twice
                        // (It gets decoded twice in /homepage/index.php)
                        $this->getPage()->setVar(array(
                            'SUBJECT_NAME' => \Scrub::text($currentSubject['categoryName']),
                            'SUBJECT_CODE' => \Scrub::text($code),
                            'SUBJECT_URL'  => is_array($currentSubject['category'])
                                && $this->getGroupHandler()->getPermission($currentSubject['category']['id']) >= READ_ACCESS
                                    ? \Scrub::text("/homepage/code/".urlencode(urlencode($currentSubject['category']['code']->getCode())))
                                    : '#',
                        ));

                        /* Fetch teacher names */
                        $teacherList = $this->getExternalClassHandler()->getTeacherName($code);

                        if (is_array($teacherList) && count($teacherList)) {
                            foreach ($teacherList as $teacherSynID) {
                                $teacher = $this->getUserHandler()->getBySynergyAndGroup($teacherSynID, $this->getGroupHandler()->getStaffGroupTypeIds());

                                if (is_array($teacher)) {
                                    $teacherName = $this->getUserHandler()->formatName($teacher);

                                    $emailAddress = $emailObj->formatEmailFromArray($teacher);
                                    $emailAll[] = $emailAddress;

                                    $this->getPage()->setVar(array(
                                        'SUBJECT_TEACHER_ID' => $teacher['uid'],
                                        'SUBJECT_TEACHER_EXTERNAL_ID' => $teacher['synergy_id'],
                                        'SUBJECT_TEACHER_NAME' => $teacherName,
                                        'SUBJECT_TEACHER_EMAIL' => $_SESSION['mailhandle'] . $emailAddress,
                                    ));

                                    $this->getPage()->parse('subject_teacher_block', 'subject_teacher', true);
                                }
                            }
                        }

                        $this->getPage()->parse('subject_item_block', 'subject_item', true);

                        /* Clear children after parsing out the parent
                         */
                        $this->getPage()->setVar(array(
                            'subject_teacher_block' => ''
                        ));

                        /* Distribute half n' half no longer
                         */
                        $this->getPage()->parse('subject_row_block', 'subject_row', true);

                        /* Clear children after parsing out the parent
                         */
                        $this->getPage()->setVar(array(
                            'subject_item_block' => ''
                        ));
                    }

                    /* Output email all subject teachers */
                    if ($this->getGroupHandler()->isStaff() && \Cfg::get('mail_enable')) {
                        $this->getPage()->setVar("REPLYTO", $emailObj->formatEmailFromArray($_SESSION));
                        $this->getPage()->setVar("EMAILALL", implode(',', $emailAll));
                        $this->getPage()->parse("subject_mail_block", "subject_mail");
                    } else {
                        $this->getPage()->setVar('subject_mail_block', '');
                    }


                    $this->getPage()->parse('subject_block', 'subject', true);

                    /* Clear children after parsing out the parent
                     */
                    $this->getPage()->setVar(array(
                        'subject_row_block' => '',
                        'subject_item_block' => '',
                    ));
                }
            }
        }
    }

    /* Getters/Setters
	 */

    /**
    * Set group handler
    *
    * @param null $groupHandler
    */
    private function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
    * Get group handler
    *
    * @return null
    */
    private function getGroupHandler()
    {
        return $this->groupHandler;
    }

    /**
    * Set external class handler
    *
    * @param null $externalClassHandler
    */
    private function setExternalClassHandler($externalClassHandler)
    {
        $this->externalClassHandler = $externalClassHandler;
    }

    /**
    * Get external class handler
    *
    * @return null
    */
    private function getExternalClassHandler()
    {
        return $this->externalClassHandler;
    }

    /**
    * Set user handler
    *
    * @param null $userHandler
    */
    private function setUserHandler($userHandler)
    {
        $this->userHandler = $userHandler;
    }

    /**
    * Get user handler
    *
    * @return null
    */
    private function getUserHandler()
    {
        return $this->userHandler;
    }
}
