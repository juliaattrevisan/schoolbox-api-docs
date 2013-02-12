<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DueWork_Detail {

    const ACCEPT_NONE = 0;
    const ACCEPT_SINGLE = 1;
    const ACCEPT_MULTI = 2;

    const UNSUBMITTED = 'Unsubmitted';
    const SUBMITTED = 'Submitted';
    const RESUBMITTED = 'Resubmitted';
    const ON_TIME = ' on time';
    const LATE = ' late';

    const CLASS_UNSUBMITTED = 'submit regular';
    const CLASS_ON_TIME = 'submit ok';
    const CLASS_LATE = 'submit critical';

    const CLASS_PLAG_0 = 'plag-score ok';
    const CLASS_PLAG_20 = 'plag-score warn';
    const CLASS_PLAG_60 = 'plag-score critical';

    protected $submissionHandler;
    protected $submissinoStudentHandler;
    protected $submissionReturnHandler;
    protected $userHandler;
    protected $groupHandler;

    protected $dueWork;
    protected $marksheet;
    protected $sitepageData;


    public function __construct($id) {
        $this->userHandler = new User();
        $this->submissionHandler = new Submission();
        $this->submissionStudentHandler = new SubmissionStudent();
        $this->submissionReturnHandler = new SubmissionReturn();
        $this->groupHandler = new Group();

        // set things we're interested in
        $this->dueWork = $this->submissionHandler->getByID($id);
        $this->dueWork['submissionCount'] = 0;
        $this->marksheet = Marksheet_Factory::build($this->dueWork['mark_type_slug']);
    }


    public function processStudents(array $students) {
        if (!count($students)) {
            return;
        }

        foreach ($students as $user) {
            $insertion = array();

            // accepts submissions
            if ($this->getSubmissionType() != self::ACCEPT_NONE) {
                $submissions = false;

                // search by internal id
                if ($user['uid']) {
                    $insertion['user'] = $this->userHandler->getByID($user['uid']);
                    $submissions = $this->submissionStudentHandler->getByUserID($this->dueWork['id'], $user['uid']);
                } // try the synergetic id
                elseif ($user['synergy_id']) {
                    $insertion['user'] = $this->userHandler->getBySynergy($user['synergy_id']);
                    $user['uid'] = $insertion['user']['uid'];
                    $submissions = $this->submissionStudentHandler->getBySynergyID(
                        $this->dueWork['id'], $user['synergy_id']
                    );
                }

                // if the user doesn't exist in schoolbox, we don't want to see them (since we can't operate on them)
                if (!isset($insertion['user']['uid'])) {
                    continue;
                }

                // an empty array is preferred to false
                if (!$submissions) {
                    $insertion['submissions'] = array();
                    $insertion['user']['status'] = self::UNSUBMITTED;
                    $insertion['user']['class'] = self::CLASS_UNSUBMITTED;
                    $submissions = array();
                } // only use the first if this is a single-submit box
                else {
                    if ($this->getSubmissionType() == self::ACCEPT_SINGLE) {
                        $insertion['submissions'] = $submissions[0];
                        $insertion['user']['status'] = self::SUBMITTED;
                    } else {
                        $insertion['submissions'] = $submissions;
                        if (count($submissions) > 1) {
                            $insertion['user']['status'] = self::RESUBMITTED;
                        } else {
                            $insertion['user']['status'] = self::SUBMITTED;
                        }
                    }

                    // determine whether to give the ontime or late class
                    $submitTime = new Alaress_Date_Format($submissions[0]['datestamp'], 'y-m-d');
                    $dueTime = new Alaress_Date_Format($this->dueWork['date'], 'y-m-d');

                    if ($dueTime->getUnixTimestamp() < $submitTime->getUnixTimestamp()) {
                        $insertion['user']['status'] .= self::LATE;
                        $insertion['user']['class'] = self::CLASS_LATE;
                    } else {
                        $insertion['user']['status'] .= self::ON_TIME;
                        $insertion['user']['class'] = self::CLASS_ON_TIME;
                    }
                }

                $this->dueWork['submissionCount'] += count($submissions);
                $insertion['submissions'] = $submissions;

            } // does not accept submissions
            else {
                $insertion['user']['status'] = '';
                $insertion['user']['class'] = '';

                if ($user['uid']) {
                    $insertion['user'] = $this->userHandler->getByID($user['uid']);
                } // try the synergetic id
                elseif ($user['synergy_id']) {
                    $insertion['user'] = $this->userHandler->getBySynergy($user['synergy_id']);
                    $user['uid'] = $insertion['user']['uid'];
                } // ignore this user
                else {
                    continue;
                }
            }

            // get student's mark, if there is one
            $insertion['mark'] = $this->marksheet->retrieveMark($user['uid'], $this->dueWork['id']);

            $returns = $this->submissionReturnHandler->getByUser($user['uid'], $this->dueWork['id']);
            $insertion['return'] = is_array($returns) ? $returns[0] : array();

            $this->sitepageData[$user['uid']] = $insertion;
        }
    }


    /**
     * @param SitePage $page
     */
    public function renderDetails(SitePage $page) {

        require_once(SITE_root . '/include/FileType.php');
        $allowedHandler = new AllowedFile();
        $fileTypeHandler = new FileType('');

        // setup blocks, etc
        $page->setTab('submission');
        $page->setFile(TEMPLATE_content, 'submission/viewSubmission.html');
        $page->setTitle('Submission Box');

        $page->setBlock(TEMPLATE_content, 'student_row', 'student_row_block');
        $page->setBlock('student_row', 'submission_row', 'submission_row_block');
        $page->setBlock(TEMPLATE_content, 'download_btn', 'download_btn_block');
        $page->setBlock(TEMPLATE_content, 'modify_btn', 'modify_btn_block');

        $page->setBlock('submission_row', 'download_action', 'download_action_block');
        $page->setBlock('submission_row', 'delete_action', 'delete_action_block');
        $page->setBlock('submission_row', 'plag_enabled', 'plag_enabled_block');

        $closeADF = $this->getDueDate();
        $formatDateOnly = 'l, jS F Y';
        $formatDateTime = $formatDateOnly . ' g:ia';

        if($closeADF->getUsingFormat('Hi') == '2359') {
            $closeString = $closeADF->getUsingFormat($formatDateOnly);
        }
        else {
            $closeString = $closeADF->getUsingFormat($formatDateTime);
        }


        $page->setVar(
            array(
                'DUEWORK_DESCRIPTION' => Scrub::text($this->dueWork['categoryName']) . " - " . Scrub::text(
                    $this->dueWork['name']
                ),
                'DUEWORK_TYPE' => $this->dueWork['typeName'],
                'DUEWORK_DUEDATE' => $closeString,
                'CATEGORY_ID' => $this->dueWork['cid'],
                'BOX_ID' => $this->dueWork['id'],
                'SITE_ROOT' => $page->conType() . $_SERVER['HTTP_HOST'],
            )
        );

        $dem = DueWork_ErrorMapper::getInstance();
        $errs = $dem->listDisplayValues();
        $page->setVar('COMBO_OPTIONS', json_encode($errs));


        if (!$data = $this->sitepageData) {
            $page->setVar('student_row_block',  'This subject has no students in it.');
			$page->setVar('download_btn_block', '');
			$page->setVar('modify_btn_block',   '');
			$page->setVar('DUEWORK_ARCHIVED',   '');
            return;
        }

        // process each student
        foreach ($data as $record) {
            // printf('<pre style="margin-left: 300px">%s</pre>', print_r($record, true));

            // output the student row
            $page->setVar(
                array(
                    'OWNER_ID' => $record['user']['uid'],
                    'OWNER_NAME' => $this->userHandler->formatName($record['user'], '{lastname}, {firstname}', true),
                    'OWNER_ESCAPED' => addslashes(
                        $this->userHandler->formatName($record['user'], '{firstname} {lastname}')
                    ),
                    'OWNER_STATUS' => $record['user']['status'],
                    'OWNER_CLASS' => $record['user']['class'],
                    'OWNER_IMG' => sprintf('/portrait.php?userId=%s&size=square32', $record['user']['synergy_id'])
                )
            );

            $this->renderMarking(
                $page, 'OWNER_MARKING', $record['user']['uid'] . $this->dueWork['id'], $record['mark']
            );

            //
            // Reset the contents of the submission row block, so that each student starts with a fresh row
            // (without the previous student's rows)
            $page->setVar('submission_row_block', '');
            if (count($record['submissions'])) {

                $returnTime = new Alaress_Date_Format($record['return']['returned'], 'y-m-d j:i:s');

                foreach ($record['submissions'] as $submission) {

                    $submitTime = new Alaress_Date_Format($submission['datestamp'], 'y-m-d j:i:s');

                    if ($submitTime->getUnixTimestamp() > $returnTime->getUnixTimestamp(
                    ) && $returnTime->getUnixTimestamp() > 0
                    ) {
                        $afterMark = '<span class="alert"></span>';
                    } else {
                        $afterMark = '';
                    }

                    $class = ($submitTime->getUnixTimestamp() > $this->getDueDate()->getUnixTimestamp(
                    )) ? self::CLASS_LATE : '';

                    // needing to do this just to get the icon associated with an extension is slightly ridiculous
                    $filename = $submission['realname'];
                    $mime = $allowedHandler->getMime(substr($filename, strripos($filename, '.') + 1));
                    $icon = $fileTypeHandler->getIconFile($mime);

                    $plagClass = self::CLASS_PLAG_0;
                    if (is_numeric($submission['score'])) {
                        if ($submission['score'] >= 60) {
                            $plagClass = self::CLASS_PLAG_60;
                        } elseif ($submission['score'] >= 20) {
                            $plagClass = self::CLASS_PLAG_20;
                        }
                    }

                    $plagHref = sprintf(
                        "%s%s/submission/plagiarismReport/%s/?zx_h=600px",
                        $page->conType(), $_SERVER['HTTP_HOST'], $submission['plag_doc_id']
                    );

                    $page->setVar(
                        array(
                            'SUBMISSION_ID' => $submission['id'],
                            'SUBMISSION_TIME' => $submission['date'],
                            'SUBMISSION_FILENAME' => $submission['realname'],
                            'SUBMISSION_CLASS' => $class,
                            'SUBMISSION_AFTER_MARK' => $afterMark,
                            'SUBMISSION_PLAG_SCORE' => sprintf("%d%%", $submission['score']),
                            'SUBMISSION_PLAG_CLASS' => $plagClass,
                            'SUBMISSION_PLAG_HREF' => base64_encode($plagHref),
                            'SUBMISSION_EXPANDABLE' => $this->getSubmissionType() == self::ACCEPT_MULTI
                                ? 'hide expandable'
                                : '',
                            'SUBMISSION_MIMEICON' => $icon
                        )
                    );

                    // if plagscan is not registered for use, then clear the indicator rather than show 0%
                    if (Cfg::get('plagscan_api_key')) {
                        if ($submission['plag_status'] == Plagscan_Document::STATUS_COMPLETE) {
                            $page->parse('plag_enabled_block', 'plag_enabled');
                        } // is this even a plagiarism document?
                        elseif (!$submission['plag_doc_id']) {
                            $page->setVar('plag_enabled_block', '');
                        } // is being checked, but is still pending, so tell the user
                        else {
                            $page->setVar(
                                'plag_enabled_block',
                                '<span title="Plagiarism results pending" class="plag-score regular">(pending)</span>'
                            );
                        }
                    } else {
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
    public function renderMarking(SitePage $page, $handle, $htmlFormID, $markArray) {
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
    public function isArchived() {
        return $this->getDueDate()->getUsingFormat('Y') < date('Y');
    }


    /**
     * @return bool
     */
    public function isReadOnly() {
        // a markable work is read-only if the user IS NOT a super-user, AND it has been archived
        return !$this->groupHandler->isSuperuser() && $this->isArchived();
    }


    /**
     * @return int
     */
    public function getSubmissionType() {
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
    public function getDueDate() {
        return new Alaress_Date_Format($this->dueWork['date'], 'y-m-d');
    }
}
