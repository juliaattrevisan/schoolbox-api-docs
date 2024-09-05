<?php

/**
 * Eportfolio_Page_Showcase
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as Response;
use Schoolbox\Learning\Assessment\IAssessable;
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
        $this->getPage()->setBlock('no_showcased_work', 'grades_enabled', 'grades_enabled_block');
        $this->getPage()->setBlock('no_showcased_work', 'grades_disabled', 'grades_disabled_block');
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
                'USER_FULLNAME' => $user->getFullName(),
            )
        );

        $this->getPage()->setVar('no_showcased_work_block', '');

        /* Intro text
         */
        if (
            $this->getEportfolioHandler()->getUserId() == $_SESSION['id']
            && Config::get(ConfigMapper::NAME_GRADES_ENABLED)
        ) {
            $this->getPage()->parse('has_intro_block', 'has_intro');
        } else {
            $this->getPage()->setVar('has_intro_block', '');
        }
    }

    /**
     * Render page
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    protected function render()
    {
        $showcaseMapper = new \Eportfolio\ShowcaseMapper();

        /* Gather around the loop, everyone
         */
        $allShowcasedItems = $showcaseMapper->getShowcasedSubmissionBoxes($this->getUser());

        /* Avoid that iterative silliness by pre-processing a bit
         */
        $allSubjects = [];
        $assessments = $this->getAssessments($allShowcasedItems);

        if ($allShowcasedItems->count()) {
            $previousCategory = null;

            foreach ($allShowcasedItems as $currentShowcasedItem) {
                $currentBox = $currentShowcasedItem->getSubmissionBox();
                /** @var IAssessable|null $assessment */
                $assessment  = $assessments->getOffset($currentBox->getAssessmentId());
                // if box's assessment is deleted, it's not included in $assessments
                if (!$assessment) {
                    continue;
                }
                $participant = $assessment->getParticipantIfExtant($this->user);
                if (!$participant) {
                    continue;
                }
                /** @var Response $submission */
                $submission = $participant->findMostRecentSubmittedResponse();
                /** @var Feedback $return */
                $return = $participant->findMostRecentInstructorGivenPublishedFeedback();

                /* Set up subject info
                 */
                $allSubjects[$currentBox->getCategory()->getId()]['id']   = $currentBox->getCategory()->getId();
                $allSubjects[$currentBox->getCategory()->getId()]['name'] = $currentBox->getCategory()->getName();

                /* Push individual items into the subject
                 */
                $allSubjects[$currentBox->getCategory()->getId()]['items'][] = [
                    'currentBox'           => $currentBox,
                    'currentShowcasedItem' => $currentShowcasedItem,
                    'latestSubmission'     => $submission,
                    'latestReturn'         => $return,
                ];
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
                if (is_array($currentSubject['items'])) {
                    foreach ($currentSubject['items'] as $currentItem) {
                        /** @var Eportfolio\Showcase $showcase */
                        $showcase   = $currentItem['currentShowcasedItem'];
                        /** @var Schoolbox\Assessment\Component\SubmissionBox $box */
                        $box        = $currentItem['currentBox'];
                        $assessment = $assessments->getOffset($box->getAssessmentId());
                        /** @var Response $submission */
                        $submission = $currentItem['latestSubmission'];
                        /** @var Feedback $return */
                        $return     = $currentItem['latestReturn'];
                        $workType   = $box->getAssessmentModel()->getWorkType();
                        $this->getPage()->setVar(
                            array(
                                'SHOWCASED_WORK_ITEM_ID' => $showcase->getId(),
                                'SHOWCASED_WORK_ITEM_USER_ID' => $showcase->getUid(),
                                'SHOWCASED_WORK_BOX_NAME' => $box->getAssessmentModel()->getTitle(),
                                'SHOWCASED_WORK_ASSESSMENT_ID' => $box->getAssessmentId(),
                                'SHOWCASED_WORK_BOX_TYPE' => $workType ? $workType->getName() : '',
                            )
                        );

                        /* Show submitted work information (if available)
                         */
                        if ($submission !== null) {
                            foreach ($submission->getAllFiles() as $currentFile) {
                                $this->getPage()->setVar(
                                    array(
                                        'SHOWCASED_WORK_SUBMISSION_ID'        => $submission->getId(),
                                        'SHOWCASED_WORK_FILE_NAME'            => $currentFile->getFilename(),
                                        'SHOWCASED_WORK_FILE_HASH'            => $currentFile->getHash(),
                                        'SHOWCASED_WORK_SUBMISSION_FILESIZE'  =>
                                            Alaress_Byte_Format::bytesToString($currentFile->getSize()),
                                        'SHOWCASED_WORK_SUBMISSION_SUBMITTED' => $submission->getCreatedAt()->getShort(),
                                        'SHOWCASED_WORK_MIMETYPE_ICONFILE'    => $currentFile->getMimetype()->getIconFile(),
                                    )
                                );

                                $this->getPage()->parse('submission_file_list_block', 'submission_file_list', true);
                            }
                        }

                        /* Show returned work information (if available)
                         */
                        if (
                            $return !== null
                            && $assessment->isFeedbackVisibleToUser($currentUser)
                            && ($assessment->getMarkType()->getSlug() !== Schoolbox\Assessment\Component\SubmissionBox\MarkType::SLUG_NONE)
                        ) {
                            $this->getPage()->setVar(
                                [
                                    'SHOWCASED_WORK_RETURNED_MARK'     => $return->getHumanReadableMark(),
                                    'SHOWCASED_WORK_RETURNED_GRADIENT' => Config::get('grade_gradient_enable') ? (int)($return->getNormalisedMark() / 10) : 'none',
                                ]
                            );
                        } else {
                            $this->getPage()->setVar(
                                [
                                    'SHOWCASED_WORK_RETURNED_MARK'     => '',
                                    'SHOWCASED_WORK_RETURNED_GRADIENT' => '',
                                ]
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
                        $this->getPage()->setVar('submission_file_list_block', '');
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
                $this->getPage()->setVar('grades_enabled_block', '');
                $this->getPage()->setVar('grades_disabled_block', '');
                if (Config::get(ConfigMapper::NAME_GRADES_ENABLED)) {
                    $this->getPage()->parse('grades_enabled_block', 'grades_enabled');
                } else {
                    $this->getPage()->parse('grades_disabled_block', 'grades_disabled');
                }
            }
            $this->getPage()->setVar('has_showcased_work_block', '');
        }
    }
}
