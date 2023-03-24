<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\LessonPlan\Decorator;

use Schoolbox\Collection;
use Schoolbox\Admin\LessonPlan\Template\Mapper as LessonPlanTemplateMapper;
use Schoolbox\Learning\Assessment\AttachedFilesTrait;
use Schoolbox\Learning\Assessment\DecoratorInterface;
use Schoolbox\Learning\Assessment\LessonPlan;
use Schoolbox\Learning\Assessment\LessonPlan\Model;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Field\Mapper as LessonPlanFieldMapper;
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
     * An arbitrarily chosen prefix for new Field rows in the
     * create/update screens, which distinguishes new rows
     * from pre-existing ones since the ui lets you spawn new
     * rows and mix-and-match them with existing ones in some
     * arbitrary user-chosen order.
     */
    private const NEW_KEYWORD = 'new';


    /**
     * Decorator constructor.
     *
     * @param array|string[] $attachedFiles
     */
    public function __construct(
        private int $lessonPlanTemplateId,
        private array $lessonPlanFields,
        private string $lessonPlanVisibility,
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
        $lessonPlanFieldMapper = LessonPlanFieldMapper::getInstance();
        $lessonPlanTemplateMapper = LessonPlanTemplateMapper::getInstance();

        /* Hydrate all the template's fields too
         * We need to remember where a Lesson Plan Field came from,
         * and also which Lesson Plan Template was used
         */
        $lessonPlanTemplate = $lessonPlanTemplateMapper->findById($this->lessonPlanTemplateId);

        $assessment->setLessonPlanTemplate($lessonPlanTemplate);

        /* Decide whether students can see this Lesson Plan
         */
        $assessment->setVisibility($this->lessonPlanVisibility);

        /* Convert each Lesson Plan Template Field into a Lesson Plan Field
         * along with any customisations provided by the user
         */
        $assessment->setFields($lessonPlanFieldMapper->createCollection());

        foreach ($this->lessonPlanFields as $fieldId => $field) {
            /* Figure out which Lesson Plan Template Field is related to this Lesson Plan Field
             *
             * FIXME It could be possible that someone deletes the LPT Field while this is saving,
             *  resulting in this field going missing, due to multiplayer schoolbox. Is this
             *  something that happens often enough to safeguard further, and in what manner?
             */
            $lessonPlanTemplateField = $lessonPlanTemplate->getFields()->getBy('id', $field['template']);

            $lessonPlanField = $lessonPlanFieldMapper->createModel()
                ->setLessonPlanTemplateField($lessonPlanTemplateField)
                ->setTitle($lessonPlanTemplateField->getTitle())
                ->setBody($field['body'])
                ->setVisibility($lessonPlanTemplateField->getVisibility())
                ->setSequence($lessonPlanTemplateField->getSequence());

            /* If it's not a new Lesson Plan Field, recycle its identifier
             */
            if (!str_contains($fieldId, self::NEW_KEYWORD)) {
                $lessonPlanField->setId($fieldId);
            }

            $assessment->getFields()->add($lessonPlanField);
        }

        /* Process each of attached files separately
         */
        $newList = new Collection();

        /* $this->getAttachedFiles() is a list of hashes
         */
        if (count($this->getAttachedFiles())) {
            /* These are the lesson_plan_file relations we already know about
             */
            $existing = $assessment->getAttachments()?->indexBy('hash')->getAll();

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
