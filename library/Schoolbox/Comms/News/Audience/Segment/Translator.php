<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException;
use Schoolbox\GenericCollection;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience;
use PDO;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;

/**
 * Render human-readable representations of audience segment entity values.
 *
 * It is responsible for performing the name-generation for user-configurable (ie database) entities.
 *
 * It is *not* responsible for mapping hard-coded system entities (eg entityTypes, modifiers).
 */
class Translator
{

    /** @var IConnector */
    private $database;


    /**
     * This array is of the structure:
     *
     * [
     *      entity_type => [
     *          entity_value => $name,
     *      ]
     * ]
     *
     * $example = [
     *      'folder' => [
     *          '43'  => 'English 344 (EN344, ABC123, DEF456)',
     *          '111' => 'My Class (MC111)',
     *      ],
     *      'campus' => [
     *          '2' => 'Big Campus',
     *      ]
     * ]
     * @var string[][]
     */
    private $cache = [];

    /** @var StrategyFactory */
    private $strategyFactory;


    /**
     * @param IConnector      $db
     * @param StrategyFactory $strategyFactory
     */
    public function __construct(IConnector $db, StrategyFactory $strategyFactory)
    {
        $this->database        = $db;
        $this->strategyFactory = $strategyFactory;
    }


    /**
     * @param Model $segment
     * @return string
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function getSegmentName(Audience\Model $segment): string
    {
        if ($segment->isSimple()) {
            return $this->translateNameFromEntityType($segment->getEntityType(), $segment->getEntityValue());
        }

        $childNames = [];
        foreach ($segment->getChildren() as $childSegment) {
            $childNames[] = $this->getSegmentName($childSegment);
        }

        return \implode(' ∩ ', $childNames);
    }


    /**
     * From an entityType and entityValue, attempt determine a human-readable name
     *
     * e.g. school_house 1234 equates to "Dalton House"
     *
     * @param string $entityType
     * @param string $entityValue
     * @return string
     */
    public function translateNameFromEntityType(string $entityType, ?string $entityValue): string
    {
        // Try to find a human-friendly name, but fall back to raw value if not found.
        // This may happen if the entity no longer exists (ie was hard-deleted)
        return $this->cache[$entityType][$entityValue] ?? $entityValue ?? 'None';
    }


    /**
     * Builds a cache of human-friendly labels for each entity referenced by a the article's audience segments.
     *
     * Performs this in a single DB query for efficiency
     *
     * @param News\Model $article
     * @return $this
     * @throws InsufficientHydrationException
     * @throws UnknownEntityTypeException
     * @throws \UnexpectedValueException
     */
    public function buildCache(News\Model $article): self
    {
        $segmentIds = [];
        /** @var ISimpleStrategy[] $strategiesInUse */
        $strategiesInUse = new GenericCollection(ISimpleStrategy::class);
        foreach ($article->getAudience() as $segment) {
            if ($segment->isSimple()) {
                $segmentIds[] = $segment->getId();
                $strategiesInUse[$segment->getEntityType()] = $this->strategyFactory->makeStrategy($segment->getEntityType());
                continue;
            }
            foreach ($segment->getChildren() as $childSegment) {
                $segmentIds[] = $childSegment->getId();
                $entityType = $childSegment->getEntityType();
                $strategiesInUse[$entityType] = $this->strategyFactory->makeStrategy($entityType);
            }
        }

        if (!\count($segmentIds)) {
            return $this;
        }

        $bindings  = Builder::getBindings($segmentIds, 'id');
        $sqlPieces = [];
        foreach ($strategiesInUse as $strategy) {
            $sqlPieces[] = "({$strategy->getTranslateSql($bindings->getPlaceholder())})";
        }
        $statement = $this->database->executeQuery(\implode(' UNION ALL ', $sqlPieces), $bindings->getParams());

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            // We don't attempt to translate 'NULL' entity values here, that's not a DB responsibility.
            // Instead leave that to the view-layer.
            $this->cache[$row['entity_type']][$row['entity_value']] = $row['name'];
        }

        return $this;
    }
}
