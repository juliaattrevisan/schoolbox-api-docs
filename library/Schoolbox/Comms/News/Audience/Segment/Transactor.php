<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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

    /** @var Codec */
    private $codec;

    /** @var StrategyFactory */
    private $strategyFactory;


    /**
     * @param Audience\Mapper  $audienceMapper
     * @param Codec            $codec
     * @param StrategyFactory  $strategyFactory
     */
    public function __construct(Audience\Mapper $audienceMapper, Codec $codec, StrategyFactory $strategyFactory)
    {
        $this->audienceMapper  = $audienceMapper;
        $this->codec           = $codec;
        $this->strategyFactory = $strategyFactory;
    }


    /**
     * Updates the audiencing segments for a given article
     *
     * On method return, the article model's audience will match the persisted state
     *
     * Does not perform DB mutations unless logical changes to the audience have been made
     *
     * @param News\Model $article
     * @param string     $selectizeAudienceSegments
     * @return bool
     * @throws Mapper\Exception
     * @throws Mapper\Exception\InsufficientHydrationException
     * @throws \DomainException
     * @throws UnexpectedValueException
     */
    public function save(News\Model $article, string $selectizeAudienceSegments): bool
    {
        /** @var Audience\Model[]|GenericCollection $existingSegments */
        $existingSegments = $article->getAudience()->indexBy($this->audienceMapper->getIdentifierProperty());
        $postedSegments   = $this->codec->decodeSelectizeValues($selectizeAudienceSegments);

        $segmentsToAdd    = $this->audienceMapper->createCollection();
        $segmentsToDelete = $this->audienceMapper->createCollection();

        // Delete any segment that currently exists, but was not posted from form (ie it's been removed by user)
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
            }
        }
        // Add any segment that was posted but is not currently persisted
        foreach ($postedSegments as $postedSegment) {
            $found = false;
            foreach ($existingSegments as $existingSegment) {
                if ($existingSegment->equals($postedSegment)) {
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
     */
    private function saveAddedSegments(News\Model $article, GenericCollection $segmentsToAdd): bool
    {
        $isSegmentsAdded = false;
        if ($segmentsToAdd->count()) {
            $validSegmentSpecFactory = new Audience\Validation\Factory(
                $this->strategyFactory::getValidRelationshipModifiers(),
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
