<?php

/**
 * Result
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Textbox\Search;

use Component_Config_Collapse;
use Component_Config_ShowComponentTitle;
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
     * @var array|null
     */
    private ?array $componentConfigDefinitionId = null;

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
                if (
                    isset($row['component_value'])
                    && (int)$row['component_value']
                    && ((int)$row['collapse'] === 0 || (int)$row['show_title'] === 1)
                ) {
                    // if component exists and it's not hidden, read only user can see this
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
                'SELECT component_definition_id,
                        component_config_definition.id AS component_config_definition_id,
                        component_config_definition.key AS `key`
                    FROM component_definition JOIN component_config_definition
                                                ON component_definition.id = component_config_definition.component_definition_id
                    WHERE component_definition.class = :componentClass
                      AND component_config_definition.key IN (:componentKey, :collapse, :showComponentTitle)'
            );
            $configQuery->execute(
                [
                    'componentClass'     => $mapper::HOMEPAGE_COMPONENT_CONTROLLER,
                    'componentKey'       => $mapper::HOMEPAGE_COMPONENT_KEY,
                    'collapse'           => Component_Config_Collapse::KEY,
                    'showComponentTitle' => Component_Config_ShowComponentTitle::KEY,
                ]
            );
            $configs = $configQuery->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($configs as $config) {
                $this->componentDefinitionId                       ??= $config['component_definition_id'];
                $this->componentConfigDefinitionId[$config['key']] = $config['component_config_definition_id'];
            }
        }

        $componentJoin   = '';
        $params          = [];
        $componentColumn = '';
        if ($this->componentConfigDefinitionId) {
            $componentJoin   = "
                LEFT JOIN component_instance ci
                -- (
                          JOIN component_config_instance cci ON ci.id = cci.component_instance_id
                                                             AND cci.component_config_definition_id = :componentTextBoxId
                          LEFT JOIN component_config_instance cci2 ON cci.component_instance_id = cci2.component_instance_id
                                                                   AND cci2.component_config_definition_id = :collapse
                          LEFT JOIN component_config_instance cci3 ON cci.component_instance_id = cci3.component_instance_id
                                                                    AND cci3.component_config_definition_id = :showComponentTitle
                -- )
                ON c.id = ci.identifier
                AND ci.component_definition_id = :componentDefinitionId
                AND ci.module = 'homepage'
                AND cci.value = t.textboxID ";
            $params          = [
                'componentDefinitionId' => $this->componentDefinitionId,
                'componentTextBoxId'    => $this->componentConfigDefinitionId[$mapper::HOMEPAGE_COMPONENT_KEY],
                'collapse'              => $this->componentConfigDefinitionId[Component_Config_Collapse::KEY],
                'showComponentTitle'    => $this->componentConfigDefinitionId[Component_Config_ShowComponentTitle::KEY],

            ];
            $componentColumn = ', cci.value as component_value, cci2.value as collapse, cci3.value as show_title';
        }
        return [$componentJoin, $params, $componentColumn];
    }
}
