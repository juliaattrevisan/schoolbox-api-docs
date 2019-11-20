<?php
/**
 * Eportfolio_Page_Showcase
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Schoolbox\Permission\Access\UserAccess;

class Eportfolio_Page_Showcase extends Eportfolio_Page_Abstract
{

    /**
     * The template file to use
     *
     * @var string
     */
    protected $templateFile = 'eportfolio/showcase.html';

    /**
     * Initialise classes and collections
     */
    protected function initialise()
    {
    }

    /**
     * Set up blocks and stuff
     */
    protected function setup()
    {
        $this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
        $this->getPage()->setTab('ePortfolio');
        $this->getPage()->setBlock(TEMPLATE_content, 'no_showcased_work', 'no_showcased_work_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'has_showcased_work', 'has_showcased_work_block');
        $this->getPage()->setBlock('has_showcased_work', 'has_intro', 'has_intro_block');
        $this->getPage()->setBlock('has_showcased_work', 'showcased_work_subject', 'showcased_work_subject_block');
        $this->getPage()->setBlock('showcased_work_subject', 'showcased_work', 'showcased_work_block');
        $this->getPage()->setBlock('showcased_work', 'has_modify_form', 'has_modify_form_block');
        $this->getPage()->setBlock('showcased_work', 'submission_file_list', 'submission_file_list_block');

        /* User details
         */
        $user = $this->getUser();

        $this->getPage()->setVar(
            array(
                'USER_FIRSTNAME' => $user->getFirstname(),
                'USER_LASTNAME' => $user->getLastname(),
            )
        );

        $this->getPage()->setVar('no_showcased_work_block', '');

        /* Intro text
         */
        if ($this->getEportfolioHandler()->getUserId() == $_SESSION['id']) {
            $this->getPage()->parse('has_intro_block', 'has_intro');
        } else {
            $this->getPage()->setVar('has_intro_block', '');
        }
    }

    /**
     * Render page
     */
    protected function render()
    {
        $showcaseMapper = new \Eportfolio\ShowcaseMapper();

        /* Gather around the loop, everyone
         */
        $allShowcasedItems = $showcaseMapper->getShowcasedSubmissionBoxes($this->getUser());

        /* Avoid that iterative silliness by pre-processing a bit
         */
        $allSubjects = array();
        $j = 0;

        if ($allShowcasedItems->count()) {
            $previousCategory = null;

            foreach ($allShowcasedItems as $currentShowcasedItem) {
                try {
                    /** @var \Schoolbox\Assessment\Component\SubmissionBox $currentBox */
                    $currentBox = $currentShowcasedItem->getSubmissionBox();

                    $submission = $this->getLatestSubmission(
                        $this->getUser(),
                        $currentBox
                    );

                    $return = $this->getLatestReturn(
                        $this->getUser(),
                        $currentBox
                    );

                    /* Set up subject info
                     */
                    $allSubjects[$currentBox->getCategory()->getId()]['id'] = $currentBox->getCategory()->getId();
                    $allSubjects[$currentBox->getCategory()->getId()]['name'] = $currentBox->getCategory()->getName();

                    /* Push individual items into the subject
                     */
                    $allSubjects[$currentBox->getCategory()->getId()]['items'][] = array(
                        'currentBox' => $currentBox,
                        'currentShowcasedItem' => $currentShowcasedItem,
                        'latestSubmission' => $submission,
                        'latestReturn' => $return,
                    );
                } catch (\Exception $e) {
                    /* Skip showcased work that has been linked to nonexistent submission box
                     */
                }
            }
        }

        /* Post-process!
         */
        /** @var Schoolbox\User $currentUser */
        $currentUser = $_SESSION['user'];
        if (count($allSubjects)) {
            foreach ($allSubjects as $currentSubject) {

                /* Subject name
                 */
                $this->getPage()->setVar(
                    array(
                        'SHOWCASED_WORK_BOX_CATEGORY_ID' => $currentSubject['id'],
                        'SHOWCASED_WORK_BOX_CATEGORY_NAME' => $currentSubject['name'],
                    )
                );

                /* Showcased items within the subject
                 */
                if (count($currentSubject['items'])) {
                    foreach ($currentSubject['items'] as $currentItem) {
                        /** @var Eportfolio\Showcase $showcase */
                        /** @var Schoolbox\Assessment\Component\SubmissionBox $box */
                        /** @var Schoolbox\Assessment\Component\SubmissionBox\Submission\Submission $submission */
                        /** @var Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork $return */
                        $showcase   = $currentItem['currentShowcasedItem'];
                        $box        = $currentItem['currentBox'];
                        $submission = $currentItem['latestSubmission'];
                        $return     = $currentItem['latestReturn'];
                        $workType   = $box->getAssessmentModel()->getWorkType();
                        $this->getPage()->setVar(
                            array(
                                'SHOWCASED_WORK_ITEM_ID' => $showcase->getId(),
                                'SHOWCASED_WORK_ITEM_USER_ID' => $showcase->getUid(),
                                'SHOWCASED_WORK_BOX_NAME' => $box->getName(),
                                'SHOWCASED_WORK_ASSESSMENT_ID' => $box->getAssessmentId(),
                                'SHOWCASED_WORK_BOX_TYPE' => $workType ? $workType->getName() : '',
                            )
                        );

                        /* Show submitted work information (if available)
                         */
                        if ($submission && $submission->getFiles() && $submission->getFiles()->count()) {
                            foreach($submission->getFiles() as $currentFile) {
                                $this->getPage()->setVar(
                                    array(
                                        'SHOWCASED_WORK_SUBMISSION_ID'        => $submission->getId(),
                                        'SHOWCASED_WORK_FILE_NAME'        => $currentFile->getFilename(),
                                        'SHOWCASED_WORK_FILE_HASH'            => $currentFile->getHash(),
                                        'SHOWCASED_WORK_SUBMISSION_FILESIZE'  =>
                                            Alaress_Byte_Format::bytesToString($currentFile->getSize()),
                                        'SHOWCASED_WORK_SUBMISSION_SUBMITTED' => $submission->getCreatedAt()->getShort(),
                                        'SHOWCASED_WORK_MIMETYPE_ICONFILE'    => $currentFile->getMimetype()->getIconFile(),
                                    )
                                );

                                $this->getPage()->parse('submission_file_list_block', 'submission_file_list',true);
                            }

                        }

                        /* Show returned work information (if available)
                         */
                        if ($return && $box->isMarkShownToRole($currentUser->getRole())) {
                            $this->getPage()->setVar(
                                array(
                                    'SHOWCASED_WORK_RETURNED_MARK' => $return->getHumanReadableMark(),
                                    'SHOWCASED_WORK_RETURNED_GRADIENT' => $return->getGradientFromNormalizedMark(),
                                )
                            );
                        } else {
                            $this->getPage()->setVar(
                                array(
                                    'SHOWCASED_WORK_RETURNED_MARK' => '',
                                    'SHOWCASED_WORK_RETURNED_GRADIENT' => '',
                                )
                            );
                        }

                        /* Unpublish control
                         */
                        if ($this->getPermission() >= UserAccess::ACCESS_WRITE) {
                            $this->getPage()->parse('has_modify_form_block', 'has_modify_form');
                        } else {
                            $this->getPage()->setVar('has_modify_form_block', '');
                        }

                        $this->getPage()->parse('showcased_work_block', 'showcased_work', true);

                        /* Clear children after parsing out the parent
                         */
                        $this->getPage()->setVar('has_modify_form_block', '');
                        $this->getPage()->setVar('submission_file_list_block','');

                    }
                }

                $this->getPage()->parse('showcased_work_subject_block', 'showcased_work_subject', true);

                /* Clear children after parsing out the parent
                 */
                $this->getPage()->setVar('showcased_work_block', '');
            }

            $this->getPage()->parse('has_showcased_work_block', 'has_showcased_work');
        } else {
            if ($this->getEportfolioHandler()->getUserId() == $_SESSION['id']) {
                $this->getPage()->parse('no_showcased_work_block', 'no_showcased_work');
            }
            $this->getPage()->setVar('has_showcased_work_block', '');
        }
    }
}
