<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\LessonPlan\Decorator;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\AttachedFilesTrait;
use Schoolbox\Learning\Assessment\DecoratorInterface;
use Schoolbox\Learning\Assessment\LessonPlan;
use Schoolbox\Learning\Assessment\LessonPlan\Model;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;


/**
 * Class Decorator
 *
 * @package Schoolbox\Learning\Assessment\LessonPlan\Decorator
 */
class Decorator implements DecoratorInterface
{
    use AttachedFilesTrait;


    /**
     * Decorator constructor.
     *
     * @param array|string[] $attachedFiles
     */
    public function __construct(
        private int $lessonPlanTemplateId,
        private array $attachedFiles
    ) {
    }


    /**
     * @param Model $assessment
     * @throws \InvalidArgumentException
     * @throws Exception
     * @throws Deleted
     * @throws \UnexpectedValueException
     */
    public function decorate($assessment): void
    {
        /* Slap in the Lesson Plan Template chosen
         */
        $lessonPlanTemplatemapper = \Schoolbox\Admin\LessonPlan\Template\Mapper::getInstance();

        $assessment->setLessonPlanTemplate($lessonPlanTemplatemapper->findById($this->lessonPlanTemplateId));

        /* Process each of attached files separately
         */
        $newList = new Collection();

        /* $this->getAttachedFiles() is a list of hashes
         */
        if (count($this->getAttachedFiles())) {
            /* These are the lesson_plan_file relations we already know about
             */
            $existing = $assessment->getAttachments()->indexBy('hash')->getAll();

            $factory     = new CollectorFactory();
            $attachments = $factory->build($this->getAttachedFiles())->getAll();

            /* Exclude any hashes that are no longer in the set
             */
            foreach ($attachments as $file) {
                $lessonPlanFileFile = $existing[$file->getHash()] ?? new LessonPlan\File();

                $lessonPlanFileFile->setStorage($file)
                    ->setBasename($file->getBasename());

                $newList->add($lessonPlanFileFile);
            }
        }

        $assessment->setAttachments($newList);
    }
}
