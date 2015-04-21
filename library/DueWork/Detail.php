<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DueWork_Detail
{

    const ACCEPT_NONE   = 0;
    const ACCEPT_SINGLE = 1;
    const ACCEPT_MULTI  = 2;

    const UNSUBMITTED = 'Not submitted';
    const SUBMITTED   = 'Submitted';
    const RESUBMITTED = 'Resubmitted';
    const ON_TIME     = ' on time';
    const LATE        = ' late';

    const CLASS_UNSUBMITTED = 'submit-regular';
    const CLASS_ON_TIME     = 'submit-ok';
    const CLASS_LATE        = 'submit-critical';

    const CLASS_PLAG_0  = 'plag-score ok';
    const CLASS_PLAG_20 = 'plag-score warn';
    const CLASS_PLAG_60 = 'plag-score critical';

    protected $submissionHandler;
    protected $submissionStudentHandler;
    protected $submissionReturnHandler;
    protected $userHandler;
    protected $groupHandler;

    protected $dueWork;
    protected $marksheet;
    protected $sitepageData;


    /**
     * @param Schoolbox\Assessment\Component\SubmissionBox $submissionBox
     */
    public function __construct(Schoolbox\Assessment\Component\SubmissionBox $submissionBox)
    {
        $this->userHandler              = new User();
        $this->submissionHandler        = new Submission();
        $this->submissionStudentHandler = new SubmissionStudent();
        $this->submissionReturnHandler  = new SubmissionReturn();
        $this->groupHandler             = new Group();

        // set things we're interested in
        $this->dueWork                    = $this->submissionHandler->getByID($submissionBox->getId()); // -_-'
        $this->dueWork['submissionCount'] = 0;
        $this->marksheet                  = Marksheet_Factory::build($this->dueWork['mark_type_slug'], $submissionBox);
    }

    /**
     * Method for retrieving the data set that would be passed to Sitepage's
     * rendering mechanism.
     *
     * @return array
     */
    public function getSitepageData()
    {
        return $this->sitepageData;
    }

    public function processStudents(\Schoolbox\Collection $students)
    {
        if (!count($students)) {
            return;
        }

        $this->sitepageData = array();

        foreach ($students as $user) {
            $insertion = array();

            // accepts submissions
            if ($this->getSubmissionType() != self::ACCEPT_NONE) {
                $insertion['user']['instance'] = $user;
                $submissions = Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper::getInstance()->findByColumnList(array('owner'=>$user->getId(), 'box_id'=>$this->dueWork['id']));

                // an empty array is preferred to false
                if ($submissions->count() < 1) {
                    $insertion['submissions']    = array();
                    $insertion['user']['status'] = self::UNSUBMITTED;
                    $insertion['user']['class']  = self::CLASS_UNSUBMITTED;
                    $insertion['user']['time']   = new Alaress_Date_Format(0, 'U');
                    $submissions                 = array();
                }
                else {
                    $insertion['submissions'] = $submissions;
                    if ($submissions->count() > 1) {
                        $insertion['user']['status'] = self::RESUBMITTED;
                    } else {
                        $insertion['user']['status'] = self::SUBMITTED;
                    }

                    // determine whether to give the ontime or late class
                    $submitTime                = $submissions->first()->getCreatedAt();
                    $dueTime                   = new Alaress_Date_Format($this->dueWork['date'], 'Y-m-d');
                    $insertion['user']['time'] = $submitTime;


                    if ($dueTime->getUnixTimestamp() < $submitTime->getUnixTimestamp()) {
                        $insertion['user']['status'] .= self::LATE;
                        $insertion['user']['class'] = self::CLASS_LATE;
                    } else {
                        $insertion['user']['status'] .= self::ON_TIME;
                        $insertion['user']['class'] = self::CLASS_ON_TIME;
                    }
                }

                $this->dueWork['submissionCount'] += count($submissions);

            } // does not accept submissions
            else {
                $insertion['user']['instance'] = $user;
                $insertion['user']['status']   = '';
                $insertion['user']['class']    = '';
            }

            // get student's mark, if there is one
            $insertion['mark'] = $this->marksheet->retrieveMark($user->getId());

            $returns             = $this->submissionReturnHandler->getByUser($user->getId(), $this->dueWork['id']);
            $insertion['return'] = is_array($returns) ? $returns[0] : array();

            $this->sitepageData[$user->getId()] = $insertion;
        }
    }


    /**
     * @param SitePage $page
     */
    public function renderDetails(SitePage $page)
    {

        $allowedHandler  = new AllowedFile();
        $fileTypeHandler = new FileType('');

        // setup blocks, etc
        $page->setTab('submission');
        $page->setFile(TEMPLATE_content, 'submission/viewSubmission.html');
        $page->setTitle(Scrub::text($this->dueWork['categoryName']) . ': ' . Scrub::text($this->dueWork['name']));

        $page->setBlock(TEMPLATE_content, 'student_row', 'student_row_block');
        $page->setBlock('student_row', 'submission_row', 'submission_row_block');
        $page->setBlock('student_row', 'submission_after_mark', 'submission_after_mark_block');
        $page->setBlock(TEMPLATE_content, 'download_btn', 'download_btn_block');
        $page->setBlock(TEMPLATE_content, 'modify_btn', 'modify_btn_block');

        $page->setBlock('submission_row', 'download_action', 'download_action_block');
        $page->setBlock('submission_row', 'delete_action', 'delete_action_block');
        $page->setBlock('submission_row', 'plag_enabled', 'plag_enabled_block');
        $page->setBlock('submission_row', 'plag_deleted', 'plag_deleted_block');

        $page->setVar('plag_deleted_block', '');

        $closeADF       = $this->getDueDate();

        if ($closeADF->isAtEndOfDay()) {
            $closeString = $closeADF->getLongDateOnly();
        } else {
            $closeString = $closeADF->getLong();
        }

        $page->setVar(
            array(
                'DUEWORK_DESCRIPTION' => Scrub::text($this->dueWork['name']),
                'DUEWORK_YEAR'        => date('Y', strtotime($this->dueWork['date'])),
                'DUEWORK_SEMESTER'    => DueWork_All::getSemester(strtotime($this->dueWork['date'])),
                'CATEGORY_NAME'       => Scrub::text($this->dueWork['categoryName']),
                'DUEWORK_TYPE'        => $this->dueWork['typeName'],
                'DUEWORK_DUEDATE'     => $closeString,
                'CATEGORY_ID'         => $this->dueWork['cid'],
                'BOX_ID'              => $this->dueWork['id'],
                'SITE_ROOT'           => $page->conType() . $_SERVER['HTTP_HOST'],
            )
        );

        $dem  = DueWork_ErrorMapper::getInstance();
        $errs = $dem->listDisplayValues();
        $page->setVar('COMBO_OPTIONS', json_encode($errs));


        if (!$data = $this->sitepageData) {
            $page->setVar('student_row_block', 'This subject has no students in it.');
            $page->setVar('download_btn_block', '');
            $page->setVar('modify_btn_block', '');
            $page->setVar('DUEWORK_ARCHIVED', '');
            return;
        }

        // process each student
        foreach ($data as $record) {
            // printf('<pre style="margin-left: 300px">%s</pre>', print_r($record, true));

            // output the student row
            $page->setVar(
                array(
                    'OWNER_ID'      => $record['user']['instance']->getId(),
                    'OWNER_NAME'    => $record['user']['instance']->formatName('{lastname}, {firstname}'),
                    'OWNER_ESCAPED' => addslashes($record['user']['instance']->formatName('{firstname} {lastname}')),
                    'OWNER_STATUS'  => $record['user']['status'],
                    'OWNER_CLASS'   => $record['user']['class'],
                    'OWNER_TIME'    => $record['user']['time'] instanceof Alaress_Date_Format ? $record['user']['time']->getShort() : '',
                    'OWNER_IMG'     => sprintf('/portrait.php?userId=%s&size=square32', $record['user']['instance']->getexternalId())
                )
            );

            $this->renderMarking($page, 'OWNER_MARKING', $record['user']['instance']->getId() . $this->dueWork['id'], $record['mark']);

            //
            // Reset the contents of the submission row block, so that each student starts with a fresh row
            // (without the previous student's rows)
            $page->setVar('submission_row_block', '');

            if (count($record['submissions'])) {
                $returnTime = new Alaress_Date_Format($record['return']['returned'], 'Y-m-d H:i:s');

                foreach ($record['submissions'] as $submission) {
                    $submitTime = $submission->getCreatedAt();

                    if ($submitTime->getUnixTimestamp() > $returnTime->getUnixTimestamp() && $returnTime->getUnixTimestamp() > 0) {
                        $page->parse('submission_after_mark_block', 'submission_after_mark');
                    } else {
                        $page->setVar(
                            array(
                                'submission_after_mark_block' => ''
                            )
                        );
                    }

                    if ($submitTime->getUnixTimestamp() > $this->getDueDate()->getUnixTimestamp()) {
                        $class                     = self::CLASS_LATE;
                        $submissionTimeDescription = 'Submitted Late';
                    } else {
                        $class                     = '';
                        $submissionTimeDescription = 'Submitted On Time';
                    }

                    $plagScan = new Plagscan_Document();

                    // needing to do this just to get the icon associated with an extension is slightly ridiculous
                    if ($submission->getStorage()->getMimeType()) {

                        $icon = $submission->getStorage()->getMimeType()->getIconFile();

                        $plagScan = $submission->getPlagscan();

                        $plagClass = self::CLASS_PLAG_0;

                        if (is_numeric($plagScan->getScore())) {
                            if ($plagScan->getScore() >= 60) {
                                $plagClass = self::CLASS_PLAG_60;
                            } elseif ($plagScan->getScore() >= 20) {
                                $plagClass = self::CLASS_PLAG_20;
                            }
                        }

                        $plagHref = sprintf(
                            "%s%s/submission/plagiarismReport/%s/?zx_h=600px",
                            $page->conType(),
                            $_SERVER['HTTP_HOST'],
                            $plagScan->getPlagscanId()
                        );
                    }


                    $page->setVar(
                        array(
                            'SUBMISSION_ID'               => $submission->getId(),
                            'SUBMISSION_TIME'             => $submission->getCreatedAt()->getShort(),
                            'SUBMISSION_FILENAME'         => $submission->getStorage()->getBaseName(),
                            'SUBMISSION_CLASS'            => $class,
                            'SUBMISSION_TIME_DESCRIPTION' => $submissionTimeDescription,
                            'SUBMISSION_AFTER_MARK'       => $afterMark,
                            'SUBMISSION_PLAG_SCORE'       => sprintf("%d%%", $plagScan->getScore()),
                            'SUBMISSION_PLAG_CLASS'       => $plagClass,
                            'SUBMISSION_PLAG_HREF'        => base64_encode($plagHref),
                            'SUBMISSION_EXPANDABLE'       => $this->getSubmissionType() == self::ACCEPT_MULTI
                                    ? 'hide expandable'
                                    : '',
                            'SUBMISSION_SELECTED'         => $page->arg[2] == $submission->getId() ? 'highlight' : '',
                            'SUBMISSION_MIMEICON'         => $icon
                        )
                    );

                    // if plagscan is not registered for use, then clear the indicator rather than show 0%
                    if (Cfg::get('plagscan_api_key')) {
                        // if it's deleted (pre- or post-completion) treat it as such
                        if ($plagScan->isDeleted() || $plagScan->getStatus() == Plagscan_Document::STATUS_UNSCANNABLE) {
                            $page->setVar('plag_enabled_block', '');

                            // do we have a number we can output for a score?
                            if ($plagScan->getStatus() == Plagscan_Document::STATUS_COMPLETE) {
                                $page->parse('plag_deleted_block', 'plag_deleted');
                            } // no value returned, hide it
                            else {
                                $page->setVar('plag_deleted_block', '');
                            }
                        } // not deleted? ok, is it completed?
                        elseif ($plagScan->getStatus() == Plagscan_Document::STATUS_COMPLETE) {
                            $page->parse('plag_enabled_block', 'plag_enabled');
                        } // not complete? is this even a plagiarism document?
                        elseif (!$plagScan->getPlagscanId()) {
                            $page->setVar('plag_enabled_block', '');
                        } // it's a plag doc, but is being checked and still pending, so tell the user
                        else {
                            $page->setVar(
                                'plag_enabled_block',
                                '<span title="Plagiarism results pending" class="plag-score regular">(pending)</span>'
                            );
                        }
                    } // plagscan disabled, clear the indicator
                    else {
                        $page->setVar('plag_enabled_block', '');
                    }

                    if ($this->isReadOnly()) {
                        $page->setVar('delete_action_block', '');
                    } else {
                        $page->parse('delete_action_block', 'delete_action');
                    }

                    $page->parse('submission_row_block', 'submission_row', true);
                }
            }

            $page->parse('student_row_block', 'student_row', true);

            /* Clear children after parsing out the parent
             */
            $page->setVar(
                array(
                    'submission_after_mark_block' => ''
                )
            );
        }

        // final housekeeping

        // show or hide download button
        if ($this->dueWork['submissionCount']) {
            $page->parse('download_btn_block', 'download_btn');
        } else {
            $page->setVar('download_btn_block', '');
        }

        // show or hide edit button, based on readonly status
        if ($this->isReadOnly()) {
            $page->setVar('modify_btn_block', '');
        } else {
            $page->parse('modify_btn_block', 'modify_btn');
        }

        // show or hide the archived warning, based on end date
        $page->setVar('DUEWORK_ARCHIVED', $this->isArchived() ? '<b class="warning">Archived</b>' : '');
    }


    /**
     * @param SitePage $page
     * @param $handle
     * @param $htmlFormID
     * @param $markArray
     */
    public function renderMarking(SitePage $page, $handle, $htmlFormID, $markArray)
    {
        $templateFile = $this->isReadOnly() ? 'readonly' : 'writable';
        $page->setFile('markTpl', "submission/marktype/$templateFile.html");
        $page->setVar('UFID', $htmlFormID);

        // blockify and clear all the marktypes
        foreach ($this->submissionHandler->getMarkTypes(true) as $type) {
            $page->setBlock('markTpl', "mark_type_{$type['slug']}", "mark_type_{$type['slug']}_block");
            $page->setVar("mark_type_{$type['slug']}_block", '');
        }

        // each strategy knows how to output itself
        $this->marksheet->outputUI($page, $markArray);

        // render the appropriate block and parse it into the handle we were provided
        $slug = $this->marksheet->getSlug();
        $page->parse("mark_type_{$slug}_block", "mark_type_{$slug}");

        $page->parse($handle, 'markTpl');
    }


    /**
     * @return bool
     */
    public function isArchived()
    {
        return $this->getDueDate()->getYear() < date('Y');
    }


    /**
     * @return bool
     */
    public function isReadOnly()
    {
        // a markable work is read-only if the user IS NOT a super-user, AND it has been archived
        return !$this->groupHandler->isSuperuser() && $this->isArchived();
    }


    /**
     * @return int
     */
    public function getSubmissionType()
    {
        if ($this->dueWork['multi_submission']) {
            return self::ACCEPT_MULTI;
        } elseif ($this->dueWork['accept_submission']) {
            return self::ACCEPT_SINGLE;
        } else {
            return self::ACCEPT_NONE;
        }
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getDueDate()
    {
        return new Alaress_Date_Format($this->dueWork['date'], 'Y-m-d');
    }
}
