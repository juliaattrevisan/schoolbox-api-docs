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

use DueWork_Error;
use Marksheet_Abstract;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Collection;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
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
     * @var array
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
     * Crocodoc uuid
     *
     * @var string
     */
    private $uuid = null;

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
     * @param User $user
     * @param SubmissionBox $submissionBox
     * @param string $mark
     * @param string $comment
     * @param array $rubric
     * @param array $attachments
     * @param bool $removeUnsetAttachments
     * @param string $uuid
     */
    public function __construct(
        User $user,
        SubmissionBox $submissionBox,
        $mark,
        $comment,
        $rubric,
        $attachments,
        $removeUnsetAttachments,
        $uuid
    ) {
        $this->setUser($user);
        $this->setSubmissionBox($submissionBox);
        $this->setMark($mark);
        $this->setComment($comment);
        $this->setRubric($rubric);
        $this->setAttachments($attachments);
        $this->setRemoveUnsetAttachments($removeUnsetAttachments);
        $this->setUuid($uuid);

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
     * Start processing the Returned Work
     */
    public function start()
    {
        $allowUpdate      = true;
        $collectorFactory = new CollectorFactory();
        $attachments      = $collectorFactory->build($this->getAttachments())->getAll();
        $returnedItems    = new Collection();
        if (count($attachments) === 0) {
            // Update a single returned work item
            // (Figure out which returned work record to use - assign marks,
            //  normalized marks, ownerships, etc.)
            $returnedItems->add($this->processReturnedWork($allowUpdate));
        } else {
            foreach ($attachments as $attachment) {
                $returnedItem = $this->processReturnedWork($allowUpdate);
                // Does this item already have a storage item?
                // Don't override it if it did - create a new item.
                if ($allowUpdate && $returnedItem->getStorage()->getHash()) {
                    $returnedItem = $this->processReturnedWork(false);
                }
                $returnedItem->setStorage($attachment);
                $returnedItems->add($returnedItem);

                // Only allow update of one return.
                // Any subsequent attachments are created as new records.
                $allowUpdate = false;
            }
        }

        /* Ram them home
         */
        foreach ($returnedItems as $returnedWork) {
            /** @var ReturnedWork $returnedWork */
            try {
                $this->getReturnedWorkMapper()->save($returnedWork);

                // Rubrics have no mappers yet, so handle them the old-fashioned way.
                if (is_array($this->getRubric())) {
                    $rubric_handler = new \DueWork_Rubric_Handler();
                    $rubric_handler->saveMarks(
                        $this->getRubric(),
                        $this->getUser()->getId(),
                        $this->getSubmissionBox()->getId(),
                        $returnedWork->getReturned()->getDatabase()
                    );

                    /* wat...?
                     *
                     * What is the point of this? Why isn't it just the same as the $returnedWork->getReturned()
                     * value (that is, the timestamp when the Returned Work was last modified)
                     *
                     * Why would you want to preserve the timestamp for when this rubric_mark value
                     * was first created?
                     *
                        $stamp = null;
                        if ($update_existing) {
                            $stamp = $rubric_handler->getLatestMarkTimestamp($student_id, $box_id);
                        }
                        $rubric_handler->saveMarks($rubric, $student_id, $box_id, $stamp);
                     */
                }
            } catch (\Exception $e) {
                // noop
                // Not sure why this exception got swallowed.
                // Possibly because `saveMarks()` can fail?
            }
        }
        return $returnedItems;
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
                    'is_confirmation_of_submission' => false,
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
            $return_time = $returnedWork->getReturned();
            $window_ago = new \Alaress_Date_Format(time() - $this->getUpdateWindow(), 'U');

            /* Use the existing returned work record - it was created a short time ago or the marks are not yet visible
             */
            if ($this->getSubmissionBox()->getMarkVisibility() == SubmissionBox::MARK_VISIBILITY_PRIVATE
                || $return_time->getUnixTimestamp() >= $window_ago->getUnixTimestamp()
            ) {
                $returnedWork->setReturned(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
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
        $returnedWork->setReturned(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
        $returnedWork->setUuid($this->getUuid());

        return $returnedWork;
    }

    /**
     * Apply updates to the returned work record
     *
     * @param  bool         $allowUpdate If false, always create new record
     * @return ReturnedWork
     */
    private function processReturnedWork($allowUpdate)
    {
        $returnedWork = $this->retrieveReturnedWorkRecord($allowUpdate);
        $errors = $this->getErrors();

        if (isset($errors[$this->getMark()])) {
            /* A string mark was submitted (Absent, Not Submitted, etc)
             */
            $returnedWork->setMark($errors[$this->getMark()]->getDisplayAs());
            $returnedWork->setNormalizedMark($errors[$this->getMark()]->getNormaliseAs());
        } elseif ($this->getSubmissionBox()->isRubric()) {
            /* If it's a rubric, it normalises in a special way
             * However: if I submitted this from the view submission page there's no rubric set.
             * Don't change the norm mark if this happened.
             */
            if ($this->getRubric()) {
                $normalisedMarkArray = $this->getMarksheet()->normaliseMark($this->getRubric());
                $returnedWork->setMark($normalisedMarkArray['mark']);
                $returnedWork->setNormalizedMark($normalisedMarkArray['norm_mark']);
            }
        } elseif ((trim($this->getMark()) === '') || ($this->getMark() < 0)) {
            /* Empty mark (not zero) - treat as null
             */
            $returnedWork->setMark(null);
            $returnedWork->setNormalizedMark(null);
        } else {
            /* Just do it in the normal way
             */
            $normalisedMarkArray = $this->getMarksheet()->normaliseMark($this->getMark());
            $returnedWork->setMark($normalisedMarkArray['mark']);
            $returnedWork->setNormalizedMark($normalisedMarkArray['norm_mark']);
        }

        /* Update the existing Returned Work record with new data
         */
        $returnedWork->setComment($this->getComment());
        $returnedWork->setMarker($_SESSION['user']);
        $returnedWork->setUuid($this->getUuid());

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
     * Get comment
     *
     * @return string
     */
    private function getComment()
    {
        return $this->comment;
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
     * @param array $rubric
     */
    private function setRubric($rubric)
    {
        $this->rubric = $rubric;
    }

    /**
     * Get rubric
     *
     * @return array
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

    /**
     * Set uuid
     *
     * @param string $uuid
     */
    private function setUuid($uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * Get uuid
     *
     * @return string
     */
    private function getUuid()
    {
        return $this->uuid;
    }
}
