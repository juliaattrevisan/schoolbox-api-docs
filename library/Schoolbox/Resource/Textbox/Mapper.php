<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Textbox;

use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Textbox\Search\Result;
use Schoolbox\User;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Collection;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Textbox\Component\Homepage\Controller;

/**
 * Class Mapper
 *
 * @method Model createModel($idValue = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = array())
 */
class Mapper extends \Schoolbox\Mapper implements Searchable
{

    /**
     * Textbox Homepage Controller classname
     */
    public const HOMEPAGE_COMPONENT_CONTROLLER = Controller::class;


    /**
     * Textbox Dashboard Controller classname
     */
    private const DASHBOARD_COMPONENT_CONTROLLER = 'Component_Dashboard_TextboxController';


    /**
     * Homepage uses "textbox" component config name to specify the textbox
     */
    public const HOMEPAGE_COMPONENT_KEY = 'textbox';


    /**
     * Dashboard uses "textboxSearch" component config name to specify the textbox
     */
    private const DASHBOARD_COMPONENT_KEY = 'textboxSearch';


    /**
     * Mapping
     *
     * @return Bool|void
     * @throws \InvalidArgumentException
     */
    protected function mapping()
    {
        $this->hasTable('textBox');
        $this->hasModel(Model::class);
        $this->uses('textboxID', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('deleted', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE, 'deletedAt');
        $this->joins('categoryID', FolderMapper::class, 'id', null, null, 'category', 'category');
    }


    /**
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('category', 'f')}
            FROM `" . $this->getTable() . "`
            JOIN {$this->getJoinSql('category', '', 'f')}
        ";
    }


    /**
     * Get a list of all the components and their homepage locations
     * that have alt-sourced a set of textboxes that exist in $folderId
     *
     * Returns an indexed array of tuples:
     *
     * [
     *     textbox id => [
     *         'textbox' => textbox instance,
     *         'folders' => [
     *             'folder' => folder instance,
     *             'numUsages' => number of occurrences in this folder
     *         ]
     *     ]
     * ]
     *
     * @param int $folderId
     * @return array
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function findUsage(int $folderId): array
    {
        $stack = [];

        /* Find all the textboxes in $folderId and initialise stack
         *
         * This is the list of textboxes which exist on this page and
         * other homepage textbox components might alt-source these;
         * when that happens, accumulate a list of those homepages
         */
        $textboxes = $this->findByColumn('categoryID', $folderId);

        foreach ($textboxes as $textbox) {
            $stack[$textbox->getId()] = [
                'textbox' => $textbox,
                'folders' => new Collection(),
                'dashboards' => [],
            ];
        }

        /* Find homepage usages that might have alt-sourced to one or more of the
         * textboxes that exist in this folder; textboxes in a homepage might not
         * ever have been alt-sourced at all but should still appear in the list.
         */
        $homepageUsageQuery = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('category', 'f')}
            FROM component_definition cd
            INNER JOIN component_instance ci ON cd.id = ci.component_definition_id
            INNER JOIN component_config_instance cci ON cci.component_instance_id = ci.id
            INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
            INNER JOIN textBox ON cci.value = textBox.textBoxID
            INNER JOIN folder f ON ci.identifier = f.id
            WHERE cd.`class` = :controller
            AND ccd.`key` = :key
            AND textBox.deleted IS NULL
            AND f.deleted_at IS NULL
            AND textBox.categoryID = :folderId
            GROUP BY textBox.textBoxID, f.id
            ORDER BY textBox.textBoxID
        ";

        $homepageUsageStatement = $this->database->prepare($homepageUsageQuery);

        $homepageUsageStatement->execute([
            'controller' => self::HOMEPAGE_COMPONENT_CONTROLLER,
            'key' => self::HOMEPAGE_COMPONENT_KEY,
            'folderId' => $folderId,
        ]);

        while ($row = $homepageUsageStatement->fetch(\PDO::FETCH_ASSOC)) {
            $textboxId = $row['textboxID'];

            $folder = $this->getJoinMapper('category')->hydrate($row, 'f');

            $stack[$textboxId]['folders']->add($folder);
        }

        /* Find dashboard usages too
         */
        $dashboardUsageQuery = "
            SELECT
                {$this->getColumnString()},
                `groups`.id, `groups`.name
            FROM component_definition cd
            INNER JOIN component_instance ci ON cd.id = ci.component_definition_id
            INNER JOIN component_config_instance cci ON cci.component_instance_id = ci.id
            INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
            INNER JOIN textBox textBox ON cci.value = textBox.textBoxID
            INNER JOIN `groups` ON `groups`.id = ci.identifier
            WHERE cd.`class` = :controller
            AND ccd.`key` = :key
            AND textBox.deleted IS NULL
            AND textBox.categoryID = :folderId
            AND `groups`.deleted_at IS NULL
            GROUP BY `groups`.id, textBox.textBoxID
        ";

        $dashboardUsageStatement = $this->database->prepare($dashboardUsageQuery);

        $dashboardUsageStatement->execute([
            'controller' => self::DASHBOARD_COMPONENT_CONTROLLER,
            'key' => self::DASHBOARD_COMPONENT_KEY,
            'folderId' => $folderId,
        ]);

        while ($row = $dashboardUsageStatement->fetch(\PDO::FETCH_ASSOC)) {
            $textboxId = $row['textboxID'];
            $dashboardId = $row['id'];

            $stack[$textboxId]['dashboards'][$dashboardId] = [
                'id'   => $dashboardId,
                'name' => $row['name'],
            ];
        }

        /* Sort the folder names naturally and alphabetically
         */
        foreach ($stack as $textboxData) {
            $textboxData['folders']->sortNaturally('name', Collection::ORDER_ASCENDING);
        }

        return $stack;
    }


    /**
     * Search the user table for particular user(s)
     *
     * @param Model $textbox
     * @return Collection
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function findComponentUsage(Model $textbox): Collection
    {
        $instanceMapper = \Component_InstanceMapper::getInstance();

        $query = '
            SELECT component_instance.*
            FROM component_definition
            INNER JOIN component_config_definition ON component_definition.id = component_config_definition.component_definition_id
            INNER JOIN component_config_instance ON component_config_instance.component_config_definition_id = component_config_definition.id
            INNER JOIN component_instance ON component_config_instance.component_instance_id = component_instance.id
            WHERE class = :controllerClass
            AND `key` = :key
            AND `identifier` = :categoryId
            AND `value` = :textboxId
        ';

        $statement = $this->database->prepare($query);

        $statement->execute([
            'controllerClass' => Component\Homepage\Controller::class,
            'key' => 'textbox',
            'categoryId' => $textbox->getCategory()->getId(),
            'textboxId' => $textbox->getId()
        ]);

        /* Manually construct hierarchy of component instances
         */
        $collection = new Collection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $collection->add($instanceMapper->hydrate($row));
        }

        return $collection;
    }


    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * This also returns a count of all the items found in the search, not limited
     * by params
     *
     * @param ArticleResolver $articleResolver
     * @param User            $user
     * @param string          $keyword
     * @param int             $offset
     * @param int             $limit
     * @param string          $sortProperty
     * @param string          $sortDirection
     * @return Result
     */
    public function search(ArticleResolver $articleResolver, $user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT, $sortProperty = '', $sortDirection = 'DESC')
    {
        if ($limit == 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $searcher = new Result();
        $searcher->search($articleResolver, $user, $this, $keyword, $sortProperty, $sortDirection, $offset, $limit);

        return $searcher;
    }
}
