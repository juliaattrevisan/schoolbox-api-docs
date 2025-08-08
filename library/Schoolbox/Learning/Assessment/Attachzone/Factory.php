<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Attachzone;

use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LessonPlan\Model;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as Submission;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use Schoolbox\Resource\Attachment\Zone;

/**
 * Factory for creating \Schoolbox\Resource\Attachment\Zone instances for an
 * Assessment, or a particular assessment type.
 */
class Factory
{
    /**
     * Creates a zone from a given assessment.
     *
     * @param IAssessable $assessment
     * @return Zone
     */
    public function buildForAssessment(IAssessable $assessment): Zone
    {
        if ($assessment instanceof Task) {
            return $this->buildForTask($assessment);
        }

        if ($assessment instanceof \Schoolbox\Learning\Assessment\LessonPlan\Model) {
            return $this->buildForLessonPlan($assessment);
        }

        if ($assessment instanceof DueWork) {
            return $this->buildForDueWork($assessment);
        }

        return $this->buildGeneric();
    }

    /**
     * Build a generic attachzone
     *
     * @return Zone
     */
    protected function buildGeneric(): Zone
    {
        return new Zone('files');
    }

    /**
     * Build an attachzone with any Attached Files preloaded into the attachzone
     *
     * @param Task $task
     * @return Zone
     */
    protected function buildForTask(Task $task): Zone
    {
        $hashes = [];

        /* Take advantage of a somewhat obscure implementation inside the Attachzone
         * see Zone.php:99, where it will just use an IStorage instance with whatever its
         * contents are. If you provide it a hash, it'll go to the storage table and extract
         * the last known details about the file (including its latest name)
         *
         * If you give it the IStorage instance, it'll just blindly use that without visiting
         * the storage table
         *
         * This seems really obscure, not sure if intentionally obscure? Anyway
         */
        foreach ($task->getAttachedFiles() as $file) {
            $hashes[] = $file;
        }

        return (new Zone('files'))
             ->setMulti(true)
             ->setAttachmentsLabel('')
             ->attachFiles($hashes);
    }


    /**
     * Build an attachzone with any Attached Files preloaded into the attachzone
     *
     * @param Model $lessonPlan
     * @return Zone
     */
    protected function buildForLessonPlan(\Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan): Zone
    {
        $hashes = [];

        /* Take advantage of a somewhat obscure implementation inside the Attachzone
         * see Zone.php:99, where it will just use an IStorage instance with whatever its
         * contents are. If you provide it a hash, it'll go to the storage table and extract
         * the last known details about the file (including its latest name)
         *
         * If you give it the IStorage instance, it'll just blindly use that without visiting
         * the storage table
         *
         * This seems really obscure, not sure if intentionally obscure? Anyway
         *
         * getAttachments() can return null during the creational phase where the
         * GenericCollection isn't ever set.
         */
        if (null !== $lessonPlan->getAttachments()) {
            foreach ($lessonPlan->getAttachments() as $file) {
                $hashes[] = $file;
            }
        }

        return (new Zone('files'))
            ->setMulti(true)
            ->setAttachmentsLabel('')
            ->attachFiles($hashes);
    }


    /**
     * Build an attachzone with any Attached Files preloaded into the attachzone
     *
     * @param DueWork $dueWork
     * @return Zone
     */
    protected function buildForDueWork(DueWork $dueWork): Zone
    {
        $zone = new Zone('files');
        $zone->setMulti(true)
             ->setAttachmentsLabel('')
             ->attachFiles($dueWork->getAttachedFiles());

        return $zone;
    }

    /**
     * Build an attachzone with any Attached Files preloaded into the attachzone
     *
     * @param \Schoolbox\Learning\Assessment\DueWork\Response\Model $response
     * @return Zone
     */
    public function buildForDueWorkResponse(Submission $response): Zone
    {
        $zone = new Zone('files');
        $zone->setMulti(true)
             ->setAttachmentsLabel('');

        $files = [];
        /* Sort files alphabetically
         */
        $response->getAllFiles()->sortBy('filename', 'asc');

        foreach ($response->getAllFiles() as $file) {
            /* Ignore accumulation of:
             * wizzywig contents
             * kami worksheets
             */
            if (!($file->getEditableInline() || $file->getIsWorksheet())) {
                $files[] = $file;
            }
        }

        $zone->attachFiles($files);

        return $zone;
    }

    /**
     * Build an attachzone with $feedback's attached files preloaded.
     *
     * @param Feedback|null $feedback If null, build an empty attachzone
     * @return Zone
     */
    public function buildForDueWorkFeedback(?Feedback $feedback = null): Zone
    {
        $zone = (new Zone('feedbackFiles'))
            ->setMulti(true)
            ->setAttachmentsLabel('');

        $files = [];

        if ($feedback instanceof Feedback) {
            /* Sort files alphabetically
             */
            $feedback->getAllFiles()->sortBy('filename', 'asc');

            foreach ($feedback->getAllFiles() as $file) {
                /* Ignore accumulation of:
                 * kami worksheets
                 */
                if (!$file->getIsWorksheet()) {
                    $files[] = $file;
                }
            }

            $zone->attachFiles($files);
        }
        return $zone;
    }
}
