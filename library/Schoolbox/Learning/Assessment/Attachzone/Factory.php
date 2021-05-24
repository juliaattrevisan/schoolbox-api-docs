<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Attachzone;

use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
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
    public function buildForAssessment(IAssessable $assessment)
    {
        if ($assessment instanceof Task) {
            return $this->buildForTask($assessment);
        } elseif ($assessment instanceof DueWork) {
            return $this->buildForDueWork($assessment);
        }
        return $this->buildGeneric();
    }

    /**
     * Build a generic attachzone
     *
     * @return Zone
     */
    protected function buildGeneric()
    {
        return new Zone('files');
    }

    /**
     * Build an attachzone with any Attached Files preloaded into the attachzone
     *
     * @param Task $task
     * @return Zone
     */
    protected function buildForTask(Task $task)
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
     * @param DueWork $dueWork
     * @return Zone
     */
    protected function buildForDueWork(DueWork $dueWork)
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
    public function buildForDueWorkResponse(Submission $response)
    {
        $zone = new Zone('files');
        $zone->setMulti(true)
             ->setAttachmentsLabel('');

        $files = [];
        /* Sort files alphabetically
         */
        $response->getAllFiles()->sortBy('filename', 'asc');

        foreach ($response->getAllFiles() as $file) {
            /* Ignore accumulation of wizzywig contents
             */
            if (!$file->getEditableInline()) {
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
    public function buildForDueWorkFeedback(Feedback $feedback = null)
    {
        $zone = (new Zone('feedbackFiles'))
            ->setMulti(true)
            ->setAttachmentsLabel('');

        if ($feedback instanceof Feedback) {
            /* Sort files alphabetically
             */
            $feedback->getAllFiles()->sortBy('filename', 'asc');

            $zone->attachFiles($feedback->getAllFiles());
        }
        return $zone;
    }
}
