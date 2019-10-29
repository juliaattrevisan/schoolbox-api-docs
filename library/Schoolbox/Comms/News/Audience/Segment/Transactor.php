<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Alaress\Specification\Exception\UnsatisfiedException;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use UnexpectedValueException;

/**
 * Responsible for the intricacies of news article audience segment persistence.
 */
class Transactor
{

    /** @var Audience\Mapper */
    private $audienceMapper;

    /** @var StrategyFactory */
    private $strategyFactory;


    /**
     * @param Audience\Mapper  $audienceMapper
     * @param StrategyFactory  $strategyFactory
     */
    public function __construct(Audience\Mapper $audienceMapper, StrategyFactory $strategyFactory)
    {
        $this->audienceMapper  = $audienceMapper;
        $this->strategyFactory = $strategyFactory;
    }


    /**
     * Updates the audiencing segments for a given article
     *
     * On method return, the article model's audience will match the persisted state
     *
     * Does not perform DB mutations unless logical changes to the audience have been made
     *
     * @param News\Model                         $article
     * @param GenericCollection|Audience\Model[] $postedSegments
     * @return bool
     * @throws Mapper\Exception
     * @throws Mapper\Exception\InsufficientHydrationException
     * @throws UnexpectedValueException
     * @throws News\Exceptions\UnknownEntityTypeException
     */
    public function save(News\Model $article, GenericCollection $postedSegments): bool
    {
        /** @var Audience\Model[]|GenericCollection $existingSegments */
        $existingSegments = $article->getAudience()->indexBy($this->audienceMapper->getIdentifierProperty());

        // Delete any segment that currently exists, but was not posted from form (ie it's been removed by user)
        $segmentsToDelete = $this->audienceMapper->createCollection();
        foreach ($existingSegments as $existingSegment) {
            $found = false;
            foreach ($postedSegments as $postedSegment) {
                if ($existingSegment->equals($postedSegment)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $segmentsToDelete->add($existingSegment);
                $existingSegments->offsetUnset($existingSegment->getId());
            }
        }

        // Derive unique existing segments. (I don't know how duplicates are occurring, but they are)
        $updatedSegments = $this->audienceMapper->createCollection();
        foreach ($existingSegments as $existingSegment) {
            $unique = true;
            foreach ($updatedSegments as $updatedSegment) {
                if ($existingSegment->equals($updatedSegment)) {
                    $segmentsToDelete->add($existingSegment);
                    $unique = false;
                    break;
                }
            }
            if ($unique) {
                $updatedSegments->setOffset($existingSegment->getId(), $existingSegment);
            }
        }

        // Add any segment that was posted but is not currently persisted
        $segmentsToAdd = $this->audienceMapper->createCollection();
        foreach ($postedSegments as $postedSegment) {
            $found = false;
            foreach ($existingSegments as $existingSegment) {
                if ($existingSegment->equals($postedSegment)) {
                    $found = true;
                    break;
                }
            }
            // Ensure we don't persist new duplicates
            foreach ($segmentsToAdd as $segmentToAdd) {
                if ($segmentToAdd->equals($postedSegment)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $segmentsToAdd->add($postedSegment);
            }
        }

        $isSegmentsDeleted = $this->deleteRemovedSegments($article, $segmentsToDelete);
        $isSegmentsAdded   = $this->saveAddedSegments($article, $segmentsToAdd);

        foreach ($segmentsToAdd as $addedSegment) {
            $updatedSegments->offsetSet($addedSegment->getId(), $addedSegment);
        }
        $article->setAudience($updatedSegments);

        return $isSegmentsDeleted || $isSegmentsAdded;
    }


    /**
     * @param News\Model                         $article
     * @param GenericCollection|Audience\Model[] $segmentsToDelete
     * @return bool
     * @throws Mapper\Exception
     */
    private function deleteRemovedSegments(News\Model $article, GenericCollection $segmentsToDelete): bool
    {
        $changesMade = false;
        foreach ($segmentsToDelete as $segmentToDelete) {
            $isDeleted = $this->audienceMapper->deleteSegment($segmentToDelete);
            if ($isDeleted) {
                $article->getAudience()->offsetUnset($segmentToDelete->getId());
                $changesMade = true;
            }
        }
        return $changesMade;
    }


    /**
     * @param News\Model        $article
     * @param GenericCollection $segmentsToAdd
     * @return bool
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     * @throws UnsatisfiedException
     * @throws News\Exceptions\UnknownEntityTypeException
     */
    private function saveAddedSegments(News\Model $article, GenericCollection $segmentsToAdd): bool
    {
        $isSegmentsAdded = false;
        if ($segmentsToAdd->count()) {
            $validSegmentSpecFactory = new Audience\Validation\Factory(
                $this->strategyFactory->getValidRelationshipModifiers(),
                $this->strategyFactory::getValidNullEntities()
            );
            $validSegmentSpec        = $validSegmentSpecFactory->makeValidSegmentSpecification();

            foreach ($segmentsToAdd as $segmentToAdd) {
                $segmentToAdd->setCommsNewsArticle($article);

                if (!$validSegmentSpec->isSatisfiedBy($segmentToAdd)) {
                    throw new UnsatisfiedException($segmentToAdd, $validSegmentSpec);
                }

                $wasSaved = $this->audienceMapper->saveSegment($segmentToAdd);
                if (false !== $wasSaved) {
                    $article->getAudience()->offsetSet($segmentToAdd->getId(), $segmentToAdd);
                    $isSegmentsAdded = true;
                }
            }
        }
        return $isSegmentsAdded;
    }
}
