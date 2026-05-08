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
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\LinkedAssessments\Mapper as LinkedAssessmentsMapper;
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
     * An arbitrarily chosen prefix for new Field rows in the create/update screens, which distinguishes new rows from
     * pre-existing ones since the ui lets you spawn new rows and mix-and-match them with existing ones in some
     * arbitrary user-chosen order.
     */
    public const string NEW_KEYWORD = 'new';


    /**
     * Decorator constructor.
     *
     * @param int $lessonPlanTemplateId
     * @param array $lessonPlanFields
     * @param string $lessonPlanVisibility
     * @param null|array|string[] $attachedFiles
     * @param string $lessonPlanDuration
     */
    public function __construct(
        private int $lessonPlanTemplateId,
        private array $lessonPlanFields,
        private string $lessonPlanVisibility,
        ?array $attachedFiles,
        private string $lessonPlanDuration
    ) {
        $this->setAttachedFiles($attachedFiles);
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
        $lessonPlanLinkedAssessmentsMapper = LinkedAssessmentsMapper::getInstance();

        $assessment->setLessonPlanTemplate($this->lessonPlanTemplateId);

        /* Decide whether students can see this Lesson Plan
         */
        $assessment->setVisibility($this->lessonPlanVisibility);

        $assessment->setLessonPlanDuration($this->lessonPlanDuration);

        /* Hydrate all the template's fields too
         * We need to remember where a Lesson Plan Field came from,
         * and also which Lesson Plan Template was used
         */
        $lessonPlanTemplate = $lessonPlanTemplateMapper->findOneByColumn('id', $this->lessonPlanTemplateId, ['deleted' => true]);

        /* Convert each Lesson Plan Template Field into a Lesson Plan Field
         * along with any customisations provided by the user
         */
        $newFields = $lessonPlanFieldMapper->createCollection();

        foreach ($this->lessonPlanFields as $fieldId => $field) {
            /* Figure out which Lesson Plan Template Field is related to this Lesson Plan Field
             *
             * FIXME It could be possible that someone deletes the LPT Field while this is saving,
             *  resulting in this field going missing, due to multiplayer schoolbox. Is this
             *  something that happens often enough to safeguard further, and in what manner?
             */

            /* When you're creating a new Lesson Plan,
             * or when you're relocating an existing one (essentially identical)
             */
            $existingField = $assessment->getFields()?->getBy('id', $fieldId);

            if (null === $existingField || str_contains($fieldId, self::NEW_KEYWORD)) {
                /* This is a new field, so we need to remember from whence it came and all
                 * the details associated with its originating Lesson Plan Template Field
                 */
                $lessonPlanTemplateField = $lessonPlanTemplate->getFields()->getBy('id', $field['template']);

                $updatedField = $lessonPlanFieldMapper->createModel()
                    ->setLessonPlanTemplateField($lessonPlanTemplateField->getId())
                    ->setTitle($lessonPlanTemplateField->getTitle())
                    ->setDescription($lessonPlanTemplateField->getDescription())
                    ->setVisibility($lessonPlanTemplateField->getVisibility())
                    ->setSequence($lessonPlanTemplateField->getSequence());
            } else {
                /* This is an existing field; we only need to change its body
                 * Everything else remains unchanged
                 */
                $updatedField = $assessment->getFields()->getBy('id', $fieldId);
            }

            /* Existing Lesson Plan Fields can only get their Body contents updated
             *
             * Everything else remains the same, as enforced by the Lesson Plan Template Field
             * from which it spawned.
             */
            $updatedField->setBody($field['body']);

            /* Pile it into the stack
             */
            $newFields->add($updatedField);
        }

        /* Overwrite what was originally there
         */
        $assessment->setFields($newFields);

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

        /**
         * Linked assessments
         */
        $linkedAssessments = $lessonPlanLinkedAssessmentsMapper->findByColumn('lesson_plan_id', $assessment->getId());
        $assessment->setLinkedAssessments($linkedAssessments);
    }
}
