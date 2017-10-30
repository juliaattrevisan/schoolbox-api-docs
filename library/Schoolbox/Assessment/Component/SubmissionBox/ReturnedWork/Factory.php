<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;

use Alaress_Date_Format;
use DueWork_Error;
use Marksheet_Abstract;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;

class Factory
{

    /**
     * A schoolbox user
     *
     * @var User
     */
    private $user = null;

    /**
     * An instance of Submission
     *
     * @var SubmissionBox
     */
    private $submissionBox = null;

    /**
     * The mark associated with the returned work
     * @var string
     */
    private $mark = '';

    /**
     * The comment associated with the returned work
     *
     * @var string
     */
    private $comment = '';

    /**
     * An array of rubric values selected by the marker
     *
     * @var Collection|SubmissionBox\Rubric\Descriptor[]
     */
    private $rubric = array();

    /**
     * A $_POST['attachment'][] array element
     *
     * @var array
     */
    private $attachments = array();

    /**
     * Are attachments that are not set in $this->attachments removed?
     *
     * This defaults to false, because this is most often called from a form
     * which doesn't have a file upload, and therefore passes no attachments.
     *
     * @var bool
     */
    private $removeUnsetAttachments = false;

    /**
     * An instance of ReturnedWorkMapper
     *
     * @var ReturnedWorkMapper
     */
    private $returnedWorkMapper = null;

    /**
     * A thing that came out of Marksheet_Factory
     *
     * @var Marksheet_Abstract
     */
    private $marksheet = null;

    /**
     * The time differential between using an old ReturnedWork record and spawning
     * a whole new one
     */
    private $updateWindow = 21600; // 60 * 60 * 6 seconds = 6 hours

    /**
     * A list of 'errors' which help translate non-numeric values for
     * Absent, Submitted, Not Submitted etc. into numeric ones
     *
     * @var array
     */
    private $errors = array();

    /**
     * Constructor
     *
     * $user is the person who submitted the item for marking
     * and is also synonymous with 'owner' column in the db
     *
     * @param User                        $user
     * @param SubmissionBox               $submissionBox
     * @param string                      $mark
     * @param string                      $comment
     * @param SubmissionBox\Rubric\Rubric $grid
     * @param array                       $attachments
     * @param bool                        $removeUnsetAttachments
     */
    public function __construct(
        User $user,
        SubmissionBox $submissionBox,
        $mark,
        $comment,
        $grid,
        $attachments,
        $removeUnsetAttachments
    ) {
        $this->setUser($user);
        $this->setSubmissionBox($submissionBox);
        $this->setMark($mark);
        $this->setComment($comment);
        $this->setRubric($grid);
        $this->setAttachments($attachments);
        $this->setRemoveUnsetAttachments($removeUnsetAttachments);

        /* Internally used instances
         */
        $this->setReturnedWorkMapper(new ReturnedWorkMapper());
        $this->setMarksheet(\Marksheet_Factory::build($submissionBox->getMarkType()->getSlug(), $submissionBox));

        /* Sometimes you don't submit numeric values - this mainly consists
         * of possibilities like Absent, Not Submitted, etc. These cause an error
         * due to an assumed number formatting in Marksheet/Abstract
         *
         * This translates the non-numeric values into numeric ones
         *
         * Adapted from the old returnWork.php in ajaxUpdate
         */
        $this->setErrors($this->detectErrors());
    }

    /**
     * This actually translates string Mark values into numeric ones, despite the name.
     * This occurs because MarkSheets all use normaliseMark, assuming a numeric value.
     * So you use DueWork_Errors to translate
     *
     * @return DueWork_Error[]
     */
    private function detectErrors()
    {

        // retrieve error codes
        $allErrors = \DueWork_ErrorMapper::getInstance()->findAll();

        // make associative by label (since that's what's being transmitted)
        /* @var DueWork_Error[] $errors */
        $errors = array();

        foreach ($allErrors as $currentError) {
            /* @var $currentError DueWork_Error */
            $errors[$currentError->getDisplayAs()] = $currentError;
        }

        return $errors;
    }


    /**
     * Get the returned work record to deal with
     * This either spawns a new one, or uses an old one from some time ago
     *
     * @param  bool         $allowUpdate If false, always create new record
     * @return ReturnedWork
     */
    private function retrieveReturnedWorkRecord($allowUpdate)
    {
        if (!$allowUpdate) {
            return $this->spawnNewReturnedWork();
        }

        $returnedWork = null;

        /* If you can't find an existing record, spawn a new one
         * Don't return the confirmation of submission return, because
         * that is a special entry used to directly assign a default mark
         * to a new submission.
         */
        try {
            /** @var ReturnedWork $returnedWork */
            $returnedWork = $this->getReturnedWorkMapper()->findOneByColumnList(
                array(
                    'owner' => $this->getUser()->getId(),
                    'box_id' => $this->getSubmissionBox()->getId(),
                ),
                array(
                    'returned' => 'desc'
                )
            );

            /* Resume modification of an existing returned work record that was
             * found a few moments ago, rather than spawn a new one
             *
             * This fixes the scenario where marks were entered into one record
             * and the comments (added shortly thereafter) were put into a new
             * record unintentionally
             */
            // Is this outside of the update window? if so, create a new one.
            $return_time = $returnedWork->getCreatedAt();
            $window_ago = new Alaress_Date_Format(time() - $this->getUpdateWindow(), 'U');

            /* Use the existing returned work record - it was created a short time ago or the marks are not yet visible
             */
            if ($this->getSubmissionBox()->getMarkVisibility() == IAssessable::MARK_VISIBILITY_PRIVATE
                || $return_time->getUnixTimestamp() >= $window_ago->getUnixTimestamp()
            ) {
                $returnedWork->setCreatedAt(new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
            } /* Spawn a new returned work record, so pump in a returned date
			 * and all the ownership stuff
			 */
            else {
                $returnedWork = $this->spawnNewReturnedWork();
            }
        } /* Spawn a new one with the appropriate ownerships when returning work
		 * for the fiiiiirrst tiiiimee
		 */
        catch (\Schoolbox\Mapper\Exception $e) {
            $returnedWork = $this->spawnNewReturnedWork();
        }

        return $returnedWork;
    }

    /**
     * Spawn a new returned work record with appropriate ownerships
     *
     * @return ReturnedWork
     */
    private function spawnNewReturnedWork()
    {
        $returnedWork = $this->getReturnedWorkMapper()->createModel();
        $returnedWork->setOwner($this->getUser());
        $returnedWork->setSubmissionBox($this->getSubmissionBox());
        $returnedWork->setCreatedAt(new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

        return $returnedWork;
    }


    /* Getters/Setters
     */

    /**
     * Set comment
     *
     * @param string $comment
     */
    private function setComment($comment)
    {
        $this->comment = $comment;
    }


    /**
     * Set mark
     *
     * @param string $mark
     */
    private function setMark($mark)
    {
        $this->mark = $mark;
    }

    /**
     * Get mark
     *
     * @return string
     */
    private function getMark()
    {
        return $this->mark;
    }

    /**
     * Set submission box
     *
     * @param SubmissionBox $submissionBox
     */
    private function setSubmissionBox($submissionBox)
    {
        $this->submissionBox = $submissionBox;
    }

    /**
     * Get submission box
     *
     * @return SubmissionBox
     */
    private function getSubmissionBox()
    {
        return $this->submissionBox;
    }

    /**
     * Set user
     *
     * @param User $user
     */
    private function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Get user
     *
     * @return User
     */
    private function getUser()
    {
        return $this->user;
    }

    /**
     * Set attachments
     *
     * @param array $attachments
     */
    private function setAttachments($attachments = array())
    {
        $this->attachments = $attachments;
    }

    /**
     * Get attachments
     *
     * @return array
     */
    private function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * @return boolean
     */
    private function isRemoveUnsetAttachments()
    {
        return $this->removeUnsetAttachments;
    }

    /**
     * @param  boolean $removeUnsetAttachments
     *
     * @return $this
     */
    private function setRemoveUnsetAttachments($removeUnsetAttachments)
    {
        $this->removeUnsetAttachments = $removeUnsetAttachments;
        return $this;
    }

    /**
     * Set returned work mapper
     *
     * @param ReturnedWorkMapper $returnedWorkMapper
     */
    private function setReturnedWorkMapper($returnedWorkMapper)
    {
        $this->returnedWorkMapper = $returnedWorkMapper;
    }

    /**
     * Get returned work mapper
     *
     * @return ReturnedWorkMapper
     */
    private function getReturnedWorkMapper()
    {
        return $this->returnedWorkMapper;
    }

    /**
     * Set marksheet
     *
     * @param Marksheet_Abstract $marksheet
     */
    private function setMarksheet($marksheet)
    {
        $this->marksheet = $marksheet;
    }

    /**
     * Get marksheet
     *
     * @return Marksheet_Abstract
     */
    private function getMarksheet()
    {
        return $this->marksheet;
    }

    /**
     * Set update window
     *
     * @param int $updateWindow
     */
    private function setUpdateWindow($updateWindow)
    {
        $this->updateWindow = $updateWindow;
    }

    /**
     * Get update window
     *
     * @return int
     */
    private function getUpdateWindow()
    {
        return $this->updateWindow;
    }

    /**
     * Set rubric
     *
     * @param SubmissionBox\Rubric\Rubric $rubric
     */
    private function setRubric($rubric)
    {
        $this->rubric = $rubric;
    }

    /**
     * Get rubric
     *
     * @return SubmissionBox\Rubric\Rubric
     */
    private function getRubric()
    {
        return $this->rubric;
    }

    /**
     * Set errors
     *
     * @param DueWork_Error[] $errors
     */
    private function setErrors($errors)
    {
        $this->errors = $errors;
    }

    /**
     * Get errors
     *
     * @return DueWork_Error[]
     */
    private function getErrors()
    {
        return $this->errors;
    }
}
