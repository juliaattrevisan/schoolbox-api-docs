<?php

/**
 * Result
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Textbox\Search;

use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Search\Sanitiser;
use Schoolbox\User;
use Schoolbox\Resource\Search\MapperResult\Base;
use Traversable;

class Result extends Base
{

    /**
     * Homepage TextBox component definition id
     * @var int|null
     */
    private ?int $componentDefinitionId = null;

    /**
     * Homepage TextBox component's textbox key
     * @var int|null
     */
    private ?int $componentConfigDefinitionId = null;

    /**
     * Get all the search results
     *
     * @param ArticleResolver $articleResolver
     * @param User            $user
     * @param Mapper          $mapper
     * @param Sanitiser       $sanitiser
     * @return Collection
     * @throws Mapper\Exception
     */
    protected function executeSearch(ArticleResolver $articleResolver, User $user, Mapper $mapper, Sanitiser $sanitiser)
    {
        /** @var \Schoolbox\Resource\Textbox\Mapper $mapper */
        [$componentJoin, $componentParams, $componentColumn] = $this->getComponentJoin($mapper);

        $query = "
            SELECT
                {$mapper->getColumnString('t')},
                {$mapper->getJoinMapper('category')->getColumnString('c')},
                MATCH(`heading`,`body`) AGAINST ({$sanitiser->getKeyword()}) AS `score`
                {$componentColumn}
            FROM
                `{$mapper->getTable()}` `t`
                INNER JOIN {$mapper->getJoinSql('category', 't', 'c')}
                {$componentJoin}
            WHERE
                t.deleted IS NULL AND
                (
                  MATCH(`heading`,`body`) AGAINST ({$sanitiser->getKeyword()}) OR
                  `heading` LIKE :like
                )
            ORDER BY {$sanitiser->getSortByWithAlias('t')} {$sanitiser->getSortDir()}
            LIMIT 0,{$sanitiser->getResultLimit()}
        ";

        $quotedLike = $mapper->getDatabase()->getQuoter()->escapeForLike($sanitiser->getUnquotedKeyword());

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->setFetchMode(\PDO::FETCH_ASSOC);
        $stmt->execute(array_merge($componentParams, ['like' => '%' . $quotedLike . '%']));

        $collection = $mapper->createCollection();

        foreach ($this->buildSearchResult($stmt, $user, 'c.id', $sanitiser->getOffset(), $sanitiser->getPageLimit()) as $row) {
            $textbox  = $mapper->hydrate($row, 't');
            $category = $mapper->hydrateJoin($row, 'category', 'c');

            $textbox->setCategory($category);

            $collection->add($textbox);
        }

        return $collection;
    }


    /**
     * The total number of items found by the search
     *
     * @param ArticleResolver   $articleResolver
     * @param User              $user
     * @param \Schoolbox\Mapper $mapper
     * @param Sanitiser         $sanitiser
     * @return int
     * @throws Mapper\DefinitionException
     */
    protected function executeSearchCount(ArticleResolver $articleResolver, User $user, Mapper $mapper, Sanitiser $sanitiser)
    {
        /** @var \Schoolbox\Resource\Textbox\Mapper $mapper */
        [$componentJoin, $componentParams, $componentColumn] = $this->getComponentJoin($mapper);

        $query = "
            SELECT
                c.id
                {$componentColumn}
            FROM
                `{$mapper->getTable()}` `t`
                INNER JOIN {$mapper->getJoinSql('category', 't', 'c')}
                {$componentJoin}
            WHERE
                t.deleted IS NULL AND
                (
                  MATCH(`heading`,`body`) AGAINST ({$sanitiser->getKeyword()}) OR
                  `heading` LIKE :like
                )
            LIMIT 0,{$sanitiser->getResultLimit()}
        ";

        $quotedLike = $mapper->getDatabase()->getQuoter()->escapeForLike($sanitiser->getUnquotedKeyword());

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute(array_merge($componentParams, ['like' => '%' . $quotedLike . '%']));

        return $this->buildSearchCount($stmt, $user, 'id');
    }

    /**
     * @inheritDoc
     */
    public function buildFilteredResult(Traversable $rawResult, User $user, $folderColumn)
    {
        return new \CallbackFilterIterator(
            new \IteratorIterator($rawResult),
            function ($row) use ($user, $folderColumn) {
                if (isset($row['value']) && $row['value']) {
                    // if component exists, read only user can see this
                    return $this->getAccessor()->getFolderAccess($user, (int)$row[$folderColumn]) > UserAccess::ACCESS_DENY;
                } else {
                    // if component doesn't exist, only write+ user can see this
                    return $this->getAccessor()->getFolderAccess($user, (int)$row[$folderColumn]) > UserAccess::ACCESS_READ;
                }
            }
        );
    }

    /**
     * Find homepage textBox component's config id's
     * @param \Schoolbox\Resource\Textbox\Mapper $mapper
     * @return array
     */
    private function getComponentJoin(\Schoolbox\Resource\Textbox\Mapper $mapper): array
    {
        if (!$this->componentConfigDefinitionId) {
            $configQuery = $mapper->getDatabase()->prepare(
                'SELECT component_definition_id, component_config_definition.id AS component_config_definition_id
                    FROM component_definition JOIN component_config_definition
                                                ON component_definition.id = component_config_definition.component_definition_id
                    WHERE component_definition.class = :componentClass
                      AND component_config_definition.key = :componentKey'
            );
            $configQuery->execute(
                [
                    'componentClass' => $mapper::HOMEPAGE_COMPONENT_CONTROLLER,
                    'componentKey'   => $mapper::HOMEPAGE_COMPONENT_KEY,
                ]
            );
            if ($config = $configQuery->fetch(\PDO::FETCH_ASSOC)) {
                $this->componentDefinitionId       = $config['component_definition_id'];
                $this->componentConfigDefinitionId = $config['component_config_definition_id'];
            }
        }

        $componentJoin   = '';
        $params          = [];
        $componentColumn = '';
        if ($this->componentConfigDefinitionId) {
            $componentJoin   = " LEFT JOIN component_instance ci
                                      JOIN component_config_instance cci ON ci.id = cci.component_instance_id
                                                             AND cci.component_config_definition_id = :componentConfigDefinitionId
                                                             ON c.id = ci.identifier
                                 AND ci.component_definition_id = :componentDefinitionId
                                 AND cci.value = t.textboxID ";
            $params          = [
                'componentConfigDefinitionId' => $this->componentConfigDefinitionId,
                'componentDefinitionId'       => $this->componentDefinitionId,

            ];
            $componentColumn = ', cci.value';
        }
        return [$componentJoin, $params, $componentColumn];
    }
}
