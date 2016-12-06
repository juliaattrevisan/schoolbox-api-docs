<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Attachzone;

use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use Schoolbox\Resource\Attachment\Zone;
use \Schoolbox\Resource\Storage\Mapper as StorageMapper;
use \Schoolbox\Mapper\Exception as MapperException;

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
        $zone = new Zone('files');
        $zone->setMulti(false)->setAttachmentsLabel('');

        /* Figure out what the hash is
         * TODO: I'm sure there's a better way, but I can't remember it right now
         */
        $foundHash = preg_match('/hash=([a-zA-Z0-9]+)/', $task->getUrl(), $matches);
        $hash = $matches[1];

        if ($foundHash) {
            $zone->attachFiles([$hash]);

            /* Ram in an actual file instance to the task
             * Clear url field Since this is really about files now
             */
            try {
                $task
                    ->setAttachedFile(StorageMapper::getInstance()->findByHash($hash))
                    ->setUrl('');
            }
            catch (MapperException $e) {
                /* noop (pretend file didn't exist if it couldn't be findByHash()'d)
                 */
            }
        }

        return $zone;
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
        $zone->setMulti(true)->setAttachmentsLabel('');

        $files = [];

        foreach ($dueWork->getAttachedFiles() as $file) {
            $files[] = $file->getHash();
        }

        $zone->attachFiles($files);

        return $zone;
    }

    /**
     * Build an attachzone with any Attached Files preloaded into the attachzone
     *
     * @param \Schoolbox\Learning\Assessment\DueWork\Submission\Model $response
     * @return Zone
     */
    public function buildForDueWorkResponse(Submission $response)
    {
        $zone = new Zone('files');
        $zone->setMulti(true)->setAttachmentsLabel('');

        $files = [];

        /* Sort files alphabetically
         */
        $response->getAllFiles()->sortBy('filename', 'asc');

        foreach ($response->getAllFiles() as $file) {

            /* Ignore accumulation of wizzywig contents
             */
            if (!$file->getEditableInline()) {
                $files[] = $file->getHash();
            }
        }

        $zone->attachFiles($files);

        return $zone;
    }

    /**
     * Build an attachzone with $feedback's attached files preloaded.
     *
     * @param Feedback $feedback If null, build an empty attachzone
     * @return Zone
     */
    public function buildForDueWorkFeedback(Feedback $feedback = null)
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
                $files[] = $file->getHash();
            }
        }
        $zone->attachFiles($files);
        return $zone;
    }
}
