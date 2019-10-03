<?php
/**
 * Decorator
 *
 * Decorate an IAssessable instance with Due Work-specific fields
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Decorator;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\Grid\Factory as GridFactory;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\RowSet\ValidRowSet;
use Schoolbox\Collection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\DecoratorInterface;
use Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

class Decorator implements DecoratorInterface
{
    use DueWorkTrait;

    /**
     * Decorator constructor.
     *
     * @param        $acceptOnline
     * @param        $markType
     * @param        $gradingScheme
     * @param        $useRubric
     * @param        $markPerCapability ,
     * @param        $useMarkRange
     * @param        $maxMark
     * @param        $rubricData
     * @param        $attachedFiles
     * @param string $selfReviewable
     * @param        $similarityCheckEnabled
     */
    public function __construct(
        $acceptOnline,
        $markType,
        $gradingScheme,
        $useRubric,
        $markPerCapability,
        $useMarkRange,
        $maxMark,
        $rubricData,
        $attachedFiles,
        $selfReviewable,
        bool $similarityCheckEnabled
    ) {
        $this->setAcceptOnline(trim($acceptOnline));
        $this->setMarkType(trim($markType));
        $this->setGradingScheme(trim($gradingScheme));
        $this->setUseRubric(trim($useRubric));
        $this->setMarkPerCapability(trim($markPerCapability));
        $this->setUseMarkRange(trim($useMarkRange));
        $this->setMaxMark($maxMark);
        $this->setRubricData($rubricData);
        $this->setAttachedFiles($attachedFiles);
        $this->setSelfReviewable($selfReviewable);
        $this->setSimilarityCheckEnabled($similarityCheckEnabled);
    }

    /**
     * @param DueWork $assessment
     */
    public function decorate($assessment)
    {
        if ($this->getAcceptOnline() == DueWork::ACCEPT_ONLINE_NONE) {
            $assessment->setAcceptsNone();
        }
        else if ($this->getAcceptOnline() == DueWork::ACCEPT_ONLINE_SINGLE) {
            $assessment->setAcceptsSingle();
        }
        else if ($this->getAcceptOnline() == DueWork::ACCEPT_ONLINE_MULTI) {
            $assessment->setAcceptsMultiple();
        }

        /* TODO: Replace old classes and stuff?
         */
        $gradingGroup = new \Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model();
        $gradingGroup->setId($this->getGradingScheme());

        $assessment->setGradeGroup($gradingGroup);

        /* TODO: Replace old classes and stuff?
         */
        $markType = new \Schoolbox\Assessment\Component\SubmissionBox\MarkType();
        $markType->setId($this->getMarkType());
        $assessment->setMarkType($markType);

        $assessment->setUseRubric($this->getUseRubric());
        $assessment->setMarkPerCapability($this->getMarkPerCapability());
        $assessment->setUseMarkRange($this->getUseMarkRange());

        if ($this->getSelfReviewable() !== null) {
            // Something has come through from post, set to that
            $assessment->setSelfReviewability($this->getSelfReviewable());
        } elseif ($assessment->getSelfReviewability() === null) {
            // $assessment doesn't have an existing setting - set to default
            $assessment->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_NONE);
        } // Otherwise let the existing setting persist



        if ($this->getUseRubric()) {
            $rubricMapper = RubricDataSource::getInstance();
            $gridFactory  = new GridFactory();
            $existingGrid = new Grid(
                $assessment,
                $rubricMapper->retrieveRowSet($assessment->getBaseContext(), true)
            );
            $newGrid = $gridFactory->buildFromPostData($existingGrid, $this->getRubricData());

            // (Forgive me too for perpetuating this)
            /** @var RosettaStone $l10n */
            // TODO this should be a part of buildFromPostData, but I don't feel like injecting all its dependencies
            $l10n = \Schoolbox\Mapper::getDi()->get('l10n');
            $spec = new ValidRowSet(
                $l10n,
                UnitSpecificationMapper::getInstance(),
                $rubricMapper,
                $existingGrid->getRowSet(),
                false,
                $newGrid->getAssessment()
            );

            // `isSatisfiedBy` needs to raise an exception instead of just
            // returning false.
            // If it does just return false: that's a logic exception, fix it
            if (!$spec->isSatisfiedBy($newGrid->getRowSet())) {
                throw new \InvalidArgumentException("Save of row set failed for unknown reason");
            }

            $assessment->setRubricGrid($newGrid);

            // The max mark for a rubric-using assessment is always the same as
            // the max mark from its capabilities
            $markMax = $newGrid->calculateMaxValueFromCapabilities();
            $assessment->setMaxMark($markMax);
        } else {
            // The max mark is always whatever got sent in the form
            $assessment->setMaxMark($this->getMaxMark());
        }

        // Only spawn the mark scheme after the max mark is finalized
        // (as it might have changed from what was submitted to the form)
        $markSchemeFactory = new \Schoolbox\Learning\MarkScheme\Factory();
        $assessment->setMarkScheme($markSchemeFactory->spawnMarkSchemeFor($assessment));

        /* If there were attachments, accumulate them
         * Otherwise set attachedFiles to an empty collection
         */
        $newList = new Collection();

        if (count($this->getAttachedFiles())) {
            // Rebuild attached files list
            $factory     = new CollectorFactory();
            $attachments = $factory->build($this->getAttachedFiles())->getAll();

            foreach ($attachments as $file) {
                $newList->add(
                    (new DueWorkFile())
                        ->setStorage($file)
                        ->setBasename($file->getBasename())
                );
            }
        }

        $assessment->setAttachedFiles($newList);

        /* Similarity Check
         */
        $assessment->setSimilarityCheckEnabled($this->getSimilarityCheckEnabled());
    }
}
