<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException;
use Schoolbox\GenericCollection;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience;
use PDO;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use UnexpectedValueException;

use function array_merge;
use function array_unique;

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
     * @param string      $entityType
     * @param string|null $entityValue
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
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws InsufficientHydrationException
     * @throws UnexpectedValueException
     * @throws UnknownEntityTypeException
     * @throws \UnderflowException
     */
    public function buildCache(News\Model $article): self
    {
        // Extract all entity types and values from article's audiences
        $entityTypesAndValues = [];
        /** @var ISimpleStrategy[] $strategiesInUse */
        $strategiesInUse = new GenericCollection(ISimpleStrategy::class);
        foreach ($article->getAudience() as $segment) {
            if ($segment->isSimple()) {
                $entityType  = $segment->getEntityType();
                $entityValue = $segment->getEntityValue();
                if ($entityValue === null) {
                    continue;
                }
                $entityTypesAndValues[$entityType][] = $entityValue;
                $strategiesInUse[$entityType]        = $this->strategyFactory->makeStrategy($entityType);
                continue;
            }
            foreach ($segment->getChildren() as $childSegment) {
                $entityType  = $childSegment->getEntityType();
                $entityValue = $childSegment->getEntityValue();
                if ($entityValue === null) {
                    continue;
                }
                $entityTypesAndValues[$entityType][] = $entityValue;
                $strategiesInUse[$entityType]        = $this->strategyFactory->makeStrategy($entityType);
            }
        }

        if (!\count($entityTypesAndValues)) {
            return $this;
        }

        $sqlPieces = [];
        $params    = [];
        foreach ($strategiesInUse as $entityType => $strategy) {
            $entityValues = array_unique($entityTypesAndValues[$entityType]);
            $translateSql = $strategy->getTranslateSql($entityValues);
            $sqlPieces[]  = $translateSql->getSql();
            $params[]     = $translateSql->getParams();
        }
        $statement = $this->database->executeQuery(\implode(' UNION ALL ', $sqlPieces), array_merge(...$params));

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            // We don't attempt to translate 'NULL' entity values here, that's not a DB responsibility.
            // Instead leave that to the view-layer.
            $this->cache[$row['entity_type']][$row['entity_value']] = $row['name'];
        }

        return $this;
    }
}
