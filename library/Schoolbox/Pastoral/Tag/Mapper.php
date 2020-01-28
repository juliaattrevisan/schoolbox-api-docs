<?php
/**
 * Mapper for the pastoral tags lookup table
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Tag;

use PDO;
use PDOException;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\UsableAsTagSource;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Pastoral\Tag\Model as PastoralTag;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Class Mapper
 *
 * @package Schoolbox\Pastoral\Tag
 */
class Mapper extends BaseMapper implements UsableAsTagSource
{

    /**
     * When you click into Filter By Tags it suggests the top ten most commonly
     * used pastoral tags across all non-deleted pastoral records
     */
    private const DEFAULT_SUGGESTION_MAX_ITEMS = 10;


    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('lu_pastoral_tag');
        $this->hasModel(PastoralTag::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * Assign a pastoral tag to a pastoral record
     *
     * @param Pastoral    $pastoral
     * @param PastoralTag $tag
     * @throws PDOException
     */
    public function assignToRecord(Pastoral $pastoral, PastoralTag $tag): void
    {
        $statement = $this->getDatabase()->prepare('
            INSERT IGNORE INTO pastoral_tags (pastoral_id, tag_id)
            VALUES (:pastoralId, :tagId)
        ');

        $statement->execute([
            'pastoralId' => $pastoral->getId(),
            'tagId'      => $tag->getId(),
        ]);
    }


    /**
     * Find a collection of tags associated with pastoral record
     *
     * @param Pastoral $pastoral
     *
     * @return GenericCollection
     * @throws PDOException
     */
    public function findForRecord($pastoral): GenericCollection
    {
        $statement = $this->getDatabase()->prepare("
            {$this->getSelectString()}
            INNER JOIN      `pastoral_tags` ON `pastoral_tags`.`tag_id` = `{$this->getTable()}`.`id`
            WHERE           `pastoral_tags`.`pastoral_id` = :pastoralId
            ORDER BY        `name`
        ");

        $statement->execute(['pastoralId' => $pastoral->getId()]);

        return $this->hydrateSelect($statement);
    }


    /**
     * Remove all tags from a pastoral record
     *
     * @param Pastoral $pastoral
     * @throws PDOException
     */
    public function deleteForRecord($pastoral): void
    {
        $statement = $this->getDatabase()->prepare('
            DELETE FROM `pastoral_tags`
            WHERE       `pastoral_id` = :pastoralId
        ');

        $statement->execute(['pastoralId' => $pastoral->getId()]);
    }


    /**
     * Once a pastoral tag has been removed from all records in the database it should no longer appear in the
     * selectize as an available option. This finds all unassociated tags and deletes them.
     */
    public function purgeUnassociated(): void
    {
        $statement = $this->getDatabase()->prepare("
            UPDATE    {$this->getTable()}
            SET       `deleted_at` = NOW()
            WHERE     {$this->getTable()}.`id` NOT IN (SELECT `tag_id` FROM `pastoral_tags`)
            AND       `deleted_at` IS NULL;
        ");

        $statement->execute();
    }


    /**
     * Find pastoral tags that contain the specified keyword
     *
     * @param User        $user
     * @param string      $keyword
     * @param string|null $sort
     * @param int         $offset
     * @param int         $limit
     *
     * @return GenericCollection
     * @throws PDOException
     */
    public function findForTag(User $user, $keyword, ?string $sort, $offset = 0, $limit = 0): GenericCollection
    {
        /* Default list of 10 keywords, sorted by number of usages across pastoral records
         */
        if ((string)$keyword === '') {
            $query = "
                SELECT
                    t.*,
                    COUNT(*) AS numUsages
                FROM {$this->getTable()} t
                INNER JOIN pastoral_tags pt ON t.id = pt.tag_id
                WHERE t.`deleted_at` IS NULL
                GROUP BY pt.tag_id
                ORDER BY numUsages DESC
                LIMIT :limit
            ";

            $statement = $this->getDatabase()->prepare($query);
            $statement->bindValue('limit', self::DEFAULT_SUGGESTION_MAX_ITEMS, PDO::PARAM_INT);
            $statement->execute();

            return $this->hydrateSelect($statement);
        }

        /* Otherwise match on $keyword
         */
        $query = "{$this->getSelectString()} WHERE `deleted_at` IS NULL AND `name` LIKE :search ORDER BY name";

        if ($offset > 0) {
            $query .= ' LIMIT :limit OFFSET :offset';
        } elseif ($limit > 0) {
            $query .= ' LIMIT :limit';
        }

        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('search', '%' . $keyword . '%', PDO::PARAM_STR);

        if ($offset > 0) {
            $statement->bindValue('offset', (int)$offset, PDO::PARAM_INT);
            $statement->bindValue('limit', (int)$limit, PDO::PARAM_INT);
        } elseif ($limit > 0) {
            $statement->bindValue('limit', (int)$limit, PDO::PARAM_INT);
        }

        $statement->execute();

        return $this->hydrateSelect($statement);
    }
}
