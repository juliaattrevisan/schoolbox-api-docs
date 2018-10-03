<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\EntityType\Folder;

use Schoolbox\Comms\News\Audience\Segment\EntityType\StrategyBase;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use Alaress\Database\Predicate\InvalidColumnException;
use UnexpectedValueException;
use Schoolbox\Mapper;
use InvalidArgumentException;

class Strategy extends StrategyBase
{

    public const ENTITY_TYPE = 'folder';

    /** @var Search */
    private $search;


    /**
     * @param UserAccessMapper $userAccessMapper
     * @param bool             $staffCanPost
     * @param Search           $search
     * @throws InvalidArgumentException
     */
    public function __construct(UserAccessMapper $userAccessMapper, bool $staffCanPost, Search $search)
    {
        parent::__construct($userAccessMapper, $staffCanPost);

        if ($search->getEntityType() !== self::ENTITY_TYPE) {
            throw new InvalidArgumentException("Invalid entity type: '{$search->getEntityType()}'");
        }
        $this->search = $search;
    }


    /**
     * @inheritdoc
     */
    public function canBeTargetedByUser(User $user): bool
    {
        // All users can always target folders
        return true;
    }


    /**
     * @inheritdoc
     * @throws InvalidColumnException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        return $this->search->getSearchSql($searchPlaceholder);
    }


    /**
     * @inheritdoc
     * @throws UnexpectedValueException
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        return "
            SELECT cna1.entity_type, cna1.entity_value, f.name
            FROM 
            (SELECT folder.id,
                CONCAT(name,  
                CASE
                WHEN COUNT(code) > 0
                THEN CONCAT(' (', GROUP_CONCAT(code SEPARATOR ', '), ')')
                ELSE ''
                END) AS name
            FROM folder
            LEFT JOIN folder_code ON folder.id = folder_code.folder_id AND folder_code.deleted_at IS NULL
            WHERE folder.id IN 
                (SELECT DISTINCT entity_value
                FROM comms_news_audience cna2
                WHERE entity_type = '{$this->getEntityType()}'
                AND cna2.id IN ($segmentIdsPlaceholder))
            AND folder.deleted_at IS NULL
            GROUP BY folder.id) AS f 
            INNER JOIN comms_news_audience cna1 ON cna1.entity_value = f.id AND entity_type = '{$this->getEntityType()}'
        ";
    }


    /**
     * @inheritdoc
     */
    public function canTargetNullEntityValue(): bool
    {
        // Quite possible for a user to not have any folders
        return true;
    }


    /**
     * @inheritdoc
     */
    public function getValidRelationshipModifiers(User $user): array
    {
        return [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF];
    }
}
