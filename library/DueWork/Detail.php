<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;

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

    const CLASS_UNSUBMITTED = 'neutral';
    const CLASS_ON_TIME     = 'submitted';
    const CLASS_LATE        = 'submitted-late';

    const CLASS_PLAG_0  = 'plag-score ok';
    const CLASS_PLAG_20 = 'plag-score warn';
    const CLASS_PLAG_60 = 'plag-score critical';

    protected $submissionHandler;
    protected $submissionReturnHandler;
    protected $groupHandler;

    protected $dueWork;
    protected $marksheet;
    protected $sitepageData;

    const MARKSHEET = 0;
    const INLINE_GRADER = 1;

    /**
     * @var SubmissionBox
     */
    protected $submissionBox;


    /**
     * @param SubmissionBox $submissionBox
     */
    public function __construct(SubmissionBox $submissionBox)
    {
        $this->submissionHandler        = new \Submission();
        $this->submissionReturnHandler  = new SubmissionReturn();
        $this->groupHandler             = new Group();

        // set things we're interested in
        $this->submissionBox              = $submissionBox;

        $this->dueWork['submissionCount'] = 0;
        $this->marksheet                  = Marksheet_Factory::build($this->submissionBox->getMarkType()->getSlug(), $submissionBox);
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
                $submissions = Submission\FileMapper::getInstance()->findByColumnList(
                    array(
                        'owner'  => $user->getId(),
                        'box_id' => $this->submissionBox->getId(),
                        'draft'  => false,
                    )
                );

                // an empty array is preferred to false
                if ($submissions->count() < 1) {
                    $insertion['submissions']    = array();
                    $insertion['user']['status'] = self::UNSUBMITTED;
                    $insertion['user']['class']  = self::CLASS_UNSUBMITTED;
                    $insertion['user']['time']   = null;
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
                    $dueTime                   = $this->submissionBox->getDueDate();
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

            $returns             = $this->submissionReturnHandler->getByUser($user->getId(), $this->submissionBox->getId());
            $insertion['return'] = is_array($returns) ? $returns[0] : array();

            $this->sitepageData[$user->getId()] = $insertion;
        }
    }

    /**
     * $location is used to determine a context in which the DueWork_Detail
     * operates. This context is passed to renderMarking() to reconfigure
     * column dimensions for a slightly different layout in Inline Grader.
     *
     * @param SitePage $page
     * @param null $location
     */
    public function renderDetails(SitePage $page, $location = null)
    {
        // setup blocks, etc
        $page->setTab('submission');
        $page->setFile(TEMPLATE_content, 'submission/viewSubmission.html');
        $page->setTitle(Scrub::text($this->submissionBox->getCategory()->getName() . ': ' . $this->submissionBox->getName()));

        $page->setBlock(TEMPLATE_content, 'student_row', 'student_row_block');
        $page->setBlock('student_row', 'submission_row', 'submission_row_block');
        $page->setBlock('student_row', 'submission_after_mark', 'submission_after_mark_block');
        $page->setBlock(TEMPLATE_content, 'download_btn', 'download_btn_block');
        $page->setBlock(TEMPLATE_content, 'modify_btn', 'modify_btn_block');
        $page->setBlock(TEMPLATE_content, 'report_btn', 'report_btn_block');
        $page->setBlock(TEMPLATE_content, 'is_archived', 'is_archived_block');

        $page->setBlock('submission_row', 'download_action', 'download_action_block');
        $page->setBlock('submission_row', 'delete_action', 'delete_action_block');
        $page->setBlock('submission_row', 'dropdown_delete_action', 'dropdown_delete_action_block');
        $page->setBlock('submission_row', 'plag_enabled', 'plag_enabled_block');
        $page->setBlock('submission_row', 'plag_pending', 'plag_pending_block');
        $page->setBlock('submission_row', 'plag_deleted', 'plag_deleted_block');

        $page->setVar('plag_deleted_block', '');

        $closeADF       = $this->submissionBox->getDueDate();

        if ($closeADF->isAtEndOfDay()) {
            $closeString = $closeADF->getLongDateOnly();
        } else {
            $closeString = $closeADF->getLong();
        }

        $page->setVar(
            array(
                'DUEWORK_DESCRIPTION'   => Scrub::text($this->submissionBox->getName()),
                'DUEWORK_YEAR'          => $this->submissionBox->getDueDate()->getYear(),
                'DUEWORK_SEMESTER'      => $this->submissionBox->getDueDate()->getYearHalf(),
                'CATEGORY_NAME'         => Scrub::text($this->submissionBox->getCategory()->getName()),
                'DUEWORK_TYPE'          => $this->submissionBox->getWorkType()->getName(),
                'DUEWORK_MARKTYPE'      => $this->submissionBox->getMarkType()->getName(),
                'DUEWORK_DUEDATE'       => $closeString,
                'GRADE_VISIBILITY'      => $this->submissionBox->getMarkVisibilityDescription(),
                'GRADE_VISIBILITY_CODE' => $this->submissionBox->getMarkVisibility(),
                'GRADE_VISIBILITY_CSS'  => \Schoolbox\Assessment\Widgets::getCss($this->submissionBox->getMarkVisibility()),
                'CATEGORY_ID'           => $this->submissionBox->getCategory()->getId(),
                'BOX_ID'                => $this->submissionBox->getId(),
                'SITE_ROOT'             => $page->conType() . $_SERVER['HTTP_HOST'],
            )
        );

        $dem  = DueWork_ErrorMapper::getInstance();
        $errs = $dem->listDisplayValues();
        $page->setVar('COMBO_OPTIONS', json_encode($errs));


        if (!$data = $this->sitepageData) {
            $page->setVar('student_row_block', 'This subject has no students in it.');
            $page->setVar('download_btn_block', '');
            $page->setVar('modify_btn_block', '');
            $page->setVar('report_btn_block', '');
            $page->setVar('DUEWORK_ARCHIVED', '');
            return;
        }

        $tabindex = 0;

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
                    'OWNER_IMG'     => sprintf('/portrait.php?userId=%s&size=square64', $record['user']['instance']->getexternalId())
                )
            );

            $this->renderMarking($page, 'OWNER_MARKING', $record['user']['instance']->getId() . $this->submissionBox->getId(), $record['mark'], $location, $tabindex);

            //
            // Reset the contents of the submission row block, so that each student starts with a fresh row
            // (without the previous student's rows)
            $page->setVar('submission_row_block', '');

            if (count($record['submissions'])) {
                $returnTime = new Alaress_Date_Format($record['return']['returned'], 'Y-m-d H:i:s');

                foreach ($record['submissions'] as $submission) {
                    $submitTime = $submission->getCreatedAt();

                    if ($record['return']['returned'] && $submitTime->gt($returnTime)) {
                        $page->parse('submission_after_mark_block', 'submission_after_mark');
                    } else {
                        $page->setVar(
                            array(
                                'submission_after_mark_block' => ''
                            )
                        );
                    }

                    if ($submitTime->gt($this->submissionBox->getDueDate())) {
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
                        elseif ($plagScan->getPlagscanId()) {
                            // it's a plag doc, but is being checked and still pending, so tell the user
                            $page->parse('plag_pending_block', 'plag_pending');

                            $page->setVar(array(
                                'plag_enabled_block' => ''
                            ));
                        }
                        else {
                            $page->setVar('plag_enabled_block', '');
                        }
                    } // plagscan disabled, clear the indicator
                    else {
                        $page->setVar('plag_enabled_block', '');
                    }

                    if ($this->isReadOnly()) {
                        $page->setVar('delete_action_block', '');
                        $page->setVar('dropdown_delete_action_block', '');
                    } else {
                        $page->parse('delete_action_block', 'delete_action');
                        $page->parse('dropdown_delete_action_block', 'dropdown_delete_action');
                    }

                    $page->parse('submission_row_block', 'submission_row', true);
                }
            }

            $page->parse('student_row_block', 'student_row', true);

            /* Increment twice - once for the input, another for the comment
             */
            $tabindex += 2;

            /* Clear children after parsing out the parent
             */
            $page->setVar(
                array(
                    'submission_after_mark_block' => '',
                    'plag_pending_block' => '',
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

        // show or hide the report button, based on rubricality
        if ($this->marksheet->getSlug() == 'rubric') {
            $page->parse('report_btn_block', 'report_btn');
        } else {
            $page->setVar('report_btn_block', '');
        }

        // show or hide the archived warning, based on end date

        if ($this->isArchived()) {
            $page->parse('is_archived_block', 'is_archived');
        }
        else {
            $page->setVar(array(
                'is_archived_block' => ''
            ));
        }
    }

    /**
     * $location is used to reconfigure column dimensions.
     *
     * $tabindex is passed in from above; each student is parsed individually
     * here, but the loop is further up. Passed here to ensure the tabindexes
     * continue to increment correctly.
     *
     * @param SitePage $page
     * @param $handle
     * @param $htmlFormID
     * @param $markArray
     * @param $location
     * @param $tabindex
     */
    private function renderMarking(SitePage $page, $handle, $htmlFormID, $markArray, $location, $tabindex)
    {
        $templateFile = $this->isReadOnly() ? 'readonly' : 'writable';
        $page->setFile('markTpl', "submission/marktype/$templateFile.html");
        $page->setVar('UFID', $htmlFormID);

        // blockify and clear all the marktypes
        foreach ($this->submissionHandler->getMarkTypes(true) as $type) {
            $page->setBlock('markTpl', "mark_type_{$type['slug']}", "mark_type_{$type['slug']}_block");

            $page->setVar(array(
                "mark_type_{$type['slug']}_block" => '',
            ));
        }

        // each strategy knows how to output itself
        $this->marksheet->outputUI($page, $markArray);

        // render the appropriate block and parse it into the handle we were provided
        $slug = $this->marksheet->getSlug();
        /* Tabindex for speedier grading in the Marksheet
         */
        $page->setVar(array(
            'TABINDEX' => ++$tabindex
        ));

        $page->parse("mark_type_{$slug}_block", "mark_type_{$slug}");

        /* Column dimensions
         */
        if ($location == self::INLINE_GRADER) {
            $columnDimensionsWritable = 'small-12';
            $columnDimensionsReadonly = 'small-12';
        }
        else {
            $columnDimensionsWritable = 'small-12 medium-6 xlarge-8';
            $columnDimensionsReadonly = 'small-12 medium-6 xlarge-8';
        }

        $page->setVar(array(
            'COLUMN_DIMENSIONS_WRITABLE' => $columnDimensionsWritable,
            'COLUMN_DIMENSIONS_READONLY' => $columnDimensionsReadonly,
        ));

        /* Comment Tabindex
         */
        $page->setVar(array(
            'COMMENT_TABINDEX' => ++$tabindex
        ));

        $page->parse($handle, 'markTpl');
    }

    /**
     * @return bool
     */
    public function isArchived()
    {
        return $this->submissionBox->getDueDate()->getYear() < date('Y');
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
        if ($this->submissionBox->getAcceptMultiple()) {
            return self::ACCEPT_MULTI;
        } elseif ($this->submissionBox->getAcceptOnline()) {
            return self::ACCEPT_SINGLE;
        } else {
            return self::ACCEPT_NONE;
        }
    }
}
