<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task\Decorator;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\AttachedFilesTrait;
use Schoolbox\Learning\Assessment\DecoratorInterface;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Task\File as TaskFile;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

/**
 * Class Decorator
 *
 * @package Schoolbox\Learning\Assessment\Task\Decorator
 */
class Decorator implements DecoratorInterface
{
    use TaskTrait;
    use AttachedFilesTrait;


    /**
     * Decorator constructor.
     *
     * @param bool $hasSelfAssessed
     * @param string $url
     * @param array|string[] $attachedFiles
     */
    public function __construct($hasSelfAssessed, $url, $attachedFiles)
    {
        $this->setHasSelfAssessed($hasSelfAssessed);
        $this->setUrl($url);
        $this->setAttachedFiles($attachedFiles);
    }


    /**
     * @param Task $assessment
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function decorate($assessment)
    {
        $assessment->setIsSelfAssessable($this->getHasSelfAssessed());

        /* URLs are now stored separately from Attached Files
         */
        $assessment->setUrl($this->getUrl());

        /* Process each of attached files separately
         */
        $newList = new Collection();

        /* $this->getAttachedFiles() is a list of hashes
         */
        if (count($this->getAttachedFiles())) {
            /* These are the task_file relations we already know about
             */
            $existing = $assessment->getAttachedFiles()->indexBy('hash')->getAll();

            $factory     = new CollectorFactory();
            $attachments = $factory->build($this->getAttachedFiles())->getAll();

            /* Exclude any hashes that are no longer in the set
             */
            foreach ($attachments as $file) {
                /* If this was already in the $assessment model:
                 *
                 * Reuse the id in the task_file table so we don't keep accumulating relations
                 * Get its latest name and update that
                 *
                 * Otherwise insert it as a new task_file relationship
                 */
                $taskFile = $existing[$file->getHash()] ?? new TaskFile();

                $taskFile->setStorage($file)
                    ->setBasename($file->getBasename());

                $newList->add($taskFile);
            }
        }

        $assessment->setAttachedFiles($newList);
    }
}
