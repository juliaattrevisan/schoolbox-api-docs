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
     * @throws \Schoolbox\Resource\Exception
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

        if (count($this->getAttachedFiles())) {
            $factory     = new CollectorFactory();
            $attachments = $factory->build($this->getAttachedFiles())->getAll();

            foreach ($attachments as $file) {
                $x = new TaskFile();

                $newList->add(
                    (new TaskFile())
                        ->setStorage($file)
                        ->setBasename($file->getBasename())
                );
            }
        }

        $assessment->setAttachedFiles($newList);
    }
}
