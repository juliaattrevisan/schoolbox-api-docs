<?php
/**
 * Mapper for the pastoral tags lookup table
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Tag;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\Pastoral\Tag\Model as PastoralTag;
use Schoolbox\Pastoral\Model as Pastoral;

class Mapper extends BaseMapper implements BaseMapper\UsableAsTagSource
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('lu_pastoral_tag');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');
    }

    /**
     * Assign a pastoral tag to a pastoral record
     *
     * @param Pastoral $pastoral
     * @param PastoralTag $tag
     */
    public function assignToRecord(Pastoral $pastoral, PastoralTag $tag)
    {
        $statement = $this->getDatabase()->prepare("
            INSERT IGNORE INTO pastoral_tags (pastoral_id, tag_id) VALUES (:pastoralId, :tagId)
        ");

        $statement->execute([
            'pastoralId' => $pastoral->getId(),
            'tagId' => $tag->getId()
        ]);
    }

    /**
     * Find out if a pastoral tag is associated with a pastoral record
     *
     * @param Pastoral $pastoral
     * @param array $tagNames
     * @return bool
     */
    public function isAssociatedWith(Pastoral $pastoral, $tagNames)
    {
        if (count($tagNames)) {
            $count = 0;
            $namePlaceholders = '';

            $bindings = ['pastoralId' => $pastoral->getId()];

            foreach ($tagNames as $tagName) {
                if ($namePlaceholders == '') {
                    $namePlaceholders .= ", ";
                }

                $namePlaceholders .= ":name_{$count}";
                $bindings[":name_{$count}"] = $tagName;

                $count++;
            }

            $statement = $this->getDatabase()->prepare("
                SELECT      COUNT(*) AS `count` 
                FROM        {$this->getTable()}
                INNER JOIN  `pastoral_tags` ON `pastoral_tags`.`tag_id` = `{$this->getTable()}`.`id`
                WHERE       `pastoral_tags`.`pastoral_id` = :pastoralId AND  
                            `name` IN ({$namePlaceholders})
            ");

            $statement->execute($bindings);
            return $statement->fetchColumn() > 0;
        }

        return false;
    }

    /**
     * Find a collection of tags associated with pastoral record
     *
     * @param Pastoral $pastoral
     * @return Collection
     */
    public function findForRecord($pastoral)
    {
        $statement = $this->getDatabase()->prepare("
        
            {$this->getSelectString()}
            
            INNER JOIN      `pastoral_tags` ON `pastoral_tags`.`tag_id` = `{$this->getTable()}`.`id`
            WHERE           `pastoral_tags`.`pastoral_id` = :pastoralId AND
                            `pastoral_tags`.`deleted_at` IS NULL
            ORDER BY        `name`
        ");

        $statement->execute(['pastoralId' => $pastoral->getId()]);

        return $this->hydrateSelect($statement);
    }

    /**
     * Remove all tags from a pastoral record
     *
     * @param Pastoral $pastoral
     */
    public function deleteForRecord($pastoral)
    {
        $statement = $this->getDatabase()->prepare("
            UPDATE    `pastoral_tags` 
            SET       `deleted_at` = NOW() 
            WHERE     `pastoral_id` = :pastoralId
        ");

        $statement->execute(['pastoralId' => $pastoral->getId()]);
    }

    /**
     * Once a pastoral tag has been removed from all records in the database it should no longer appear in the
     * selectize as an available option. This finds all unassociated tags and deletes them.
     */
    public function purgeUnassociated()
    {
        $statement = $this->getDatabase()->prepare("
            UPDATE    {$this->getTable()}
            SET       `deleted_at` = NOW()
            WHERE     {$this->getTable()}.`id` NOT IN (SELECT `tag_id` FROM `pastoral_tags`)
        ");

        $statement->execute();
    }

    /**
     * Find pastoral tags that contain the specified keyword
     *
     * @param User $user
     * @param string $keyword
     * @param int $offset
     * @param int $limit
     * @return Collection
     */
    public function findForTag(User $user, $keyword, $offset = 0, $limit = 0)
    {
        if (strlen($keyword) === 0) {
            return new Collection();
        }

        $query = "{$this->getSelectString()} WHERE `deleted_at` IS NULL AND `name` LIKE :search";

        if ($offset > 0) {
            $query .= ' LIMIT :limit OFFSET :offset';
        } elseif ($limit > 0) {
            $query .= ' LIMIT :limit';
        }

        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('search', '%' . $keyword . '%', PDO::PARAM_STR);

        if ($offset > 0) {
            $statement->bindValue('offset', intval($offset), PDO::PARAM_INT);
            $statement->bindValue('limit', intval($limit), PDO::PARAM_INT);
        } elseif ($limit > 0) {
            $statement->bindValue('limit', intval($limit), PDO::PARAM_INT);
        }

        $statement->execute();

        return $this->hydrateSelect($statement);
    }
}
