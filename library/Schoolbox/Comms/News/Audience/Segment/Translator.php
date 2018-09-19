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
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience;
use PDO;

/**
 * Render human-readable representations of audience segment entity values.
 *
 * It is responsible for performing the name-generation for user-configurable (ie database) entities.
 *
 * It is *not* responsible for mapping hard-coded system entities (eg entityTypes, modifiers).
 */
class Translator
{

    /**
     * @var IConnector
     */
    private $database;

    /**
     * @var User
     */
    private $user;

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;

    /**
     * @var RoleAccessMapper
     */
    private $roleAccessMapper;

    /** @var bool */
    private $isModerationEnabled;

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


    /**
     * @param IConnector       $db
     * @param User             $user
     * @param Folder\Mapper    $folderMapper
     * @param RoleAccessMapper $roleAccessMapper
     * @param bool             $isModerationEnabled
     */
    public function __construct(IConnector $db, User $user, Folder\Mapper $folderMapper, RoleAccessMapper $roleAccessMapper, bool $isModerationEnabled)
    {
        $this->database            = $db;
        $this->user                = $user;
        $this->folderMapper        = $folderMapper;
        $this->roleAccessMapper    = $roleAccessMapper;
        $this->isModerationEnabled = $isModerationEnabled;
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
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function buildCache(News\Model $article): self
    {
        $segmentIds = [];
        foreach ($article->getAudience() as $segment) {
            if ($segment->isSimple()) {
                $segmentIds[] = $segment->getId();
                continue;
            }
            foreach ($segment->getChildren() as $childSegment) {
                $segmentIds[] = $childSegment->getId();
            }
        }

        if (!\count($segmentIds)) {
            return $this;
        }
        $bindings   = Builder::getBindings($segmentIds, 'id');
        $translator = new TranslatorRegistry($this->user, $this->folderMapper, $this->roleAccessMapper, $this->isModerationEnabled);
        $statement  = $this->database->executeQuery($translator->getTranslateSql($bindings->getPlaceholder()), $bindings->getParams());

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            // We don't attempt to translate 'NULL' entity values here, that's not a DB responsibility.
            // Instead leave that to the view-layer.
            $this->cache[$row['entity_type']][$row['entity_value']] = $row['name'];
        }

        return $this;
    }
}
