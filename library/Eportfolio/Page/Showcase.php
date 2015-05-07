<?php

/**
 * Eportfolio_Page_Showcase
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
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
        $this->getPage()->setBlock('has_showcased_work', 'showcased_work_subject', 'showcased_work_subject_block');
        $this->getPage()->setBlock('showcased_work_subject', 'showcased_work', 'showcased_work_block');
        $this->getPage()->setBlock('showcased_work', 'has_modify_form', 'has_modify_form_block');
        $this->getPage()->setBlock('showcased_work', 'has_submission', 'has_submission_block');
        $this->getPage()->setBlock('showcased_work', 'no_submission', 'no_submission_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'has_intro', 'has_intro_block');

        /* User details
         */
        $user = $this->getUser();

        $this->getPage()->setVar(
            array(
                'USER_FIRSTNAME' => $user->getFirstname(),
                'USER_LASTNAME' => $user->getLastname(),
            )
        );

        /* Intro text
         */
        if ($this->getEportfolioHandler()->getUserId() == $_SESSION['id']) {
            $this->getPage()->parse('has_intro_block', 'has_intro');
        } else {
            $this->getPage()->setVar('has_intro_block', '');
        }

        /* Render mime types
         */
        $this->renderMimeTypes();
    }

    /**
     * Render page
     */
    protected function render()
    {
        $showcaseMapper = new \Eportfolio\ShowcaseMapper();

        /* Gather around the loop, everyone
         */
        $allShowcasedItems = $showcaseMapper->getShowcasedSubmissionBoxes($this->getUserId());

        /* Avoid that iterative silliness by pre-processing a bit
         */
        $allSubjects = array();
        $j = 0;

        if ($allShowcasedItems->count()) {
            $previousCategory = null;

            foreach ($allShowcasedItems as $currentShowcasedItem) {
                try {
                    $currentBox = $currentShowcasedItem->getSubmissionBox();

                    $latestSubmission = $this->getLatestSubmission(
                        $this->getUserId(),
                        $currentBox->getId()
                    );

                    $latestReturn = $this->getLatestReturn(
                        $this->getUserId(),
                        $currentBox->getId()
                    );

                    /* Set up subject info
                     */
                    $allSubjects[$currentBox->getCategory()->getId()]['name'] = $currentBox->getCategory()->getName();

                    /* Push individual items into the subject
                     */
                    $allSubjects[$currentBox->getCategory()->getId()]['items'][] = array(
                        'currentBox' => $currentBox,
                        'currentShowcasedItem' => $currentShowcasedItem,
                        'latestSubmission' => $latestSubmission,
                        'latestReturn' => $latestReturn,
                    );
                } catch (\Exception $e) {
                    /* Skip showcased work that has been linked to nonexistent submission box
                     */
                }
            }
        }

        /* Post-process!
         */
        if (count($allSubjects)) {
            foreach ($allSubjects as $currentSubject) {

                /* Subject name
                 */
                $this->getPage()->setVar(
                    array(
                        'SHOWCASED_WORK_BOX_CATEGORY_NAME' => $currentSubject['name'],
                    )
                );

                /* Showcased items within the subject
                 */
                if (count($currentSubject['items'])) {
                    foreach ($currentSubject['items'] as $currentItem) {
                        $this->getPage()->setVar(
                            array(
                                'SHOWCASED_WORK_ITEM_ID' => $currentItem['currentShowcasedItem']->getId(),
                                'SHOWCASED_WORK_ITEM_USER_ID' => $currentItem['currentShowcasedItem']->getUid(),
                                'SHOWCASED_WORK_BOX_NAME' => $currentItem['currentBox']->getName(),
                                'SHOWCASED_WORK_BOX_TYPE' => $currentItem['currentBox']->getWorkType()->getName(),
                                'ON_OFF' => ++$j % 2 ? '' : 'dark'
                            )
                        );

                        /* Show submitted work information (if available)
                         */
                        if ($currentItem['latestSubmission']) {
                            $this->getPage()->setVar(
                                array(
                                    'SHOWCASED_WORK_SUBMISSION_ID' => $currentItem['latestSubmission']->getId(),
                                    'SHOWCASED_WORK_SUBMISSION_FILESIZE' => Alaress_Byte_Format::bytesToString(
                                            $currentItem['latestSubmission']->getSize()
                                        ),
                                    'SHOWCASED_WORK_SUBMISSION_SUBMITTED' => $currentItem['latestSubmission']->getCreatedAt(
                                        )->getShort(),
                                    'SHOWCASED_WORK_MIMETYPE_ICONFILE' => $currentItem['latestSubmission']->getMimetype(
                                        )->getClass(),
                                )
                            );

                            $this->getPage()->parse('has_submission_block', 'has_submission');
                            $this->getPage()->setVar('no_submission_block', '');
                        } else {
                            $this->getPage()->setVar(
                                array(
                                    'SHOWCASED_WORK_SUBMISSION_FILESIZE' => '',
                                    'SHOWCASED_WORK_SUBMISSION_SUBMITTED' => '',
                                )
                            );

                            $this->getPage()->parse('no_submission_block', 'no_submission');
                            $this->getPage()->setVar('has_submission_block', '');
                        }

                        /* Show returned work information (if available)
                         */
                        if ($currentItem['latestReturn']) {
                            $this->getPage()->setVar(
                                array(
                                    'SHOWCASED_WORK_RETURNED_MARK' => $currentItem['latestReturn']->getMark(),
                                    'SHOWCASED_WORK_RETURNED_GRADIENT' => $currentItem['latestReturn']->getGradientFromNormalizedMark(
                                        ),
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
                        if ($this->getPermission() >= WRITE_ACCESS) {
                            $this->getPage()->parse('has_modify_form_block', 'has_modify_form');
                        } else {
                            $this->getPage()->setVar('has_modify_form_block', '');
                        }

                        $this->getPage()->parse('showcased_work_block', 'showcased_work', true);

                        /* Clear children after parsing out the parent
                         */
                        $this->getPage()->setVar('has_modify_form_block', '');
                    }
                }

                $this->getPage()->parse('showcased_work_subject_block', 'showcased_work_subject', true);

                /* Clear children after parsing out the parent
                 */
                $this->getPage()->setVar('showcased_work_block', '');
            }

            $this->getPage()->parse('has_showcased_work_block', 'has_showcased_work');
            $this->getPage()->setVar('no_showcased_work_block', '');
        } else {
            $this->getPage()->parse('no_showcased_work_block', 'no_showcased_work');
            $this->getPage()->setVar('has_showcased_work_block', '');
        }
    }
}