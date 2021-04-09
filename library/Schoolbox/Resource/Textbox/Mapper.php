<?php
/**
 * TextboxMapper
 *
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
 * @method Model createModel($idValue = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = array())
 */
class Mapper extends \Schoolbox\Mapper implements Searchable
{

    /**
     * Textbox Homepage Controller
     */
    private const COMPONENT_CONTROLLER = Controller::class;


    /**
     * Component configuration key - alt source textboxes seem to share this config value too
     */
    private const COMPONENT_KEY = 'textbox';


    /**
     * Mapping
     *
     * @return Bool|void
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

        /* Find homepage usages
         */
        $homepageUsageQuery = "
            SELECT 
                {$this->getColumnString()},
                {$this->getJoinColumnString('category', 'f')},
                COUNT(*) AS numUsages
            FROM textBox 
            INNER JOIN folder f ON textBox.categoryID = f.id
            WHERE textBox.categoryID = :folderId
            AND textBox.deleted IS NULL
            AND f.deleted_at IS NULL
            GROUP BY f.id, textBox.textBoxID
        ";

        $homepageUsageStatement = $this->database->prepare($homepageUsageQuery);

        $homepageUsageStatement->execute([
            'folderId' => $folderId,
        ]);

        while ($row = $homepageUsageStatement->fetch(\PDO::FETCH_ASSOC)) {
            $textbox = $this->hydrate($row);
            $folder = $this->getJoinMapper('category')->hydrate($row, 'f');

            if (!isset($stack[$textbox->getId()])) {
                $stack[$textbox->getId()] = [
                    'textbox' => $textbox,
                    'folders' => [],
                    'dashboards' => [],
                ];
            }

            $stack[$textbox->getId()]['folders'][$folder->getId()] = [
                'folder' => $folder,
                'numUsages' => $row['numUsages']
            ];
        }

        /* Find dashboard usages too
         */
        $dashboardUsageQuery = "
            SELECT 
                {$this->getColumnString()},
                groups.id, groups.name,
                COUNT(*) AS numUsages
            FROM component_definition cd
            INNER JOIN component_instance ci ON cd.id = ci.component_definition_id
            INNER JOIN component_config_instance cci ON cci.component_instance_id = ci.id
            INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
            INNER JOIN textBox textBox ON cci.value = textBox.textBoxID
            INNER JOIN groups ON groups.id = ci.identifier
            WHERE cd.`class` = 'Component_Dashboard_TextboxController'
            AND ccd.`key` = 'textboxSearch'
            AND textBox.deleted IS NULL
            AND groups.deleted_at IS NULL
            GROUP BY groups.id, textBox.textBoxID
        ";

        $dashboardUsageStatement = $this->database->prepare($dashboardUsageQuery);

        $dashboardUsageStatement->execute([
            'controller' => self::COMPONENT_CONTROLLER,
            'key' => self::COMPONENT_KEY,
            'folderId' => $folderId,
        ]);

        while ($row = $dashboardUsageStatement->fetch(\PDO::FETCH_ASSOC)) {
            $textbox = $this->hydrate($row);
            $dashboardId = $row['id'];

            if (!isset($stack[$textbox->getId()])) {
                $stack[$textbox->getId()] = [
                    'textbox' => $textbox,
                    'folders' => [],
                    'dashboards' => [],
                ];
            }

            $stack[$textbox->getId()]['dashboards'][$dashboardId] = [
                'name' => $row['name'],
                'numUsages' => $row['numUsages']
            ];
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
