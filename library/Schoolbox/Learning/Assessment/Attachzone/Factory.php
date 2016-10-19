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
use Schoolbox\Learning\Assessment\Types;
use Schoolbox\Resource\Attachment\Zone;
use \Schoolbox\Resource\Storage\Mapper as StorageMapper;
use \Schoolbox\Mapper\Exception as MapperException;

/**
 * Factory for creating \Schoolbox\Resource\Attachment\Zone instances for an
 * Assessment, or a particular assessment type.
 */
class Factory
{
    protected $assessmentTypes;

    public function __construct(Types $assessmentTypes)
    {
        $this->assessmentTypes = $assessmentTypes;
    }

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
     * Creates a zone from a given assessment type.
     *
     * @param int $type
     * @return Zone
     */
    public function buildForAssessmentType($type)
    {
        if ($this->assessmentTypes->isTask($type)) {
            return $this->buildForTask();
        } elseif ($this->assessmentTypes->isDueWork($type)) {
            return $this->buildForDueWork();
        }
        return $this->buildGeneric();
    }

    /**
     * @return Zone
     */
    protected function buildGeneric()
    {
        return new Zone('files');
    }

    /**
     * @param Task|null $task
     * @return Zone
     */
    protected function buildForTask(Task $task = null)
    {
        $zone = new Zone('files');
        $zone->setMulti(false);

        if ($task instanceof Task) {

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
        }

        return $zone;
    }

    /**
     * @param DueWork $dueWork
     * @return Zone
     */
    protected function buildForDueWork(DueWork $dueWork = null)
    {
        $zone = new Zone('files');
        $zone->setMulti(true);

        // Attach files
        if ($dueWork instanceof DueWork) {
            $files = [];
            foreach ($dueWork->getAllFiles() as $file) {
                $files[] = $file->getHash();
            }
            $zone->attachFiles($files);
        }

        return $zone;
    }
}
