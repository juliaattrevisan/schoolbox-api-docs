<?php

/**
 * Mapper for tile layouts in Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Tile\Layout;

use Component_Instance;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Tile\Mapper as TileMapper;

/**
 * Mapper for tile layouts.
 *
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    public function mapping()
    {
        $this->hasTable('tile_layout');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('heading', self::TYPE_STRING);

        // Layout: will be a choice between grid and columns
        $this->uses(
            'layout_type',
            self::TYPE_INTEGER,
            self::COLUMN_ENUM,
            'layoutType',
            [Model::LAYOUT_TYPE_GRID, Model::LAYOUT_TYPE_COLUMN]
        );

        // Number of columns when using column layout
        $this->uses('column_count', self::TYPE_INTEGER);

        // Column padding: a % of the total width of the tile
        $this->uses('column_padding', self::TYPE_INTEGER);

        // Grid width: always px as far as i know
        $this->uses('grid_width', self::TYPE_INTEGER);

        // Grid padding: a number of pixels
        $this->uses('grid_padding', self::TYPE_INTEGER);

        // Aspect ratio for grid items
        $this->uses('aspect_ratio_x', self::TYPE_INTEGER);
        $this->uses('aspect_ratio_y', self::TYPE_INTEGER);

        // Created/Modified/Deleted
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('modified_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        // Join to tiles as well
        $this->joinsReverseMulti('tile_layout_id', TileMapper::class, 'tileList', 'tile');

        // join through to folder, role, homepage template
        $this->joinsVia(
            'tile_layout_for_folder',
            FolderMapper::class,
            'id',
            'folder_id',
            'tile_layout_id',
            'id',
            self::TYPE_MODEL,
            null,
            'folder'
        );
        $this->joinsVia(
            'tile_layout_for_dashboard',
            RoleMapper::class,
            'id',
            'role_id',
            'tile_layout_id',
            'id',
            self::TYPE_MODEL,
            null,
            'dashboardRole'
        );
        $this->joinsVia(
            'tile_layout_for_homepage_template',
            HomepageTemplateMapper::class,
            'id',
            'homepage_template_id',
            'tile_layout_id',
            'id',
            self::TYPE_MODEL,
            null,
            'homepageTemplate'
        );
    }


    /**
     * Select contexts (folder, dashboard, homepage template)
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('tile_layout_for_folder', 'f')},
                {$this->getJoinColumnString('tile_layout_for_dashboard', 'r')},
                {$this->getJoinColumnString('tile_layout_for_homepage_template', 'h')}
        ";
    }


    /**
     * Join on to contexts (folder, dashboard, homepage template)
     *
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('tile_layout_for_folder', '', 'f', true)}
            LEFT JOIN {$this->getJoinSql('tile_layout_for_dashboard', '', 'r', true)}
            LEFT JOIN {$this->getJoinSql('tile_layout_for_homepage_template', '', 'h', true)}
        ";
    }


    /**
     * Hydrate contexts (folder, dashboard, homepage template)
     *
     * @param PDOStatement $statement
     * @return \Schoolbox\GenericCollection
     * @throws BaseMapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();
        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);
            $model
                ->setFolder($this->hydrateJoin($row, 'tile_layout_for_folder', 'f'))
                ->setDashboardRole($this->hydrateJoin($row, 'tile_layout_for_dashboard', 'r'))
                ->setHomepageTemplate($this->hydrateJoin($row, 'tile_layout_for_homepage_template', 'h'));
            $collection[$model->getId()] = $model;
        }
        return $collection;
    }


    /**
     * Gets the tile layout, and fetches any tiles that are a part of it.
     *
     * @param mixed $identifierValue
     * @return Model
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     * @throws BaseMapper\Exception\Deleted
     */
    public function findById($identifierValue)
    {
        /** @var Model $model */
        $model      = parent::findById($identifierValue);

        /** @var TileMapper $tileMapper */
        $tileMapper = $this->getJoinMapper('tile');

        $model->setTileList($tileMapper->findForLayout($model->getId()));
        return $model;
    }


    /**
     * Find tile layouts for this component's context (homepage, dashboard,
     * homepage template).
     *
     * (Apologies for the component-related logic which arguably doesn't belong
     *  in this class.)
     *
     * @param Component_Instance $component
     * @return GenericCollection|Model[]
     */
    public function findForComponentContext(Component_Instance $component): GenericCollection
    {
        // is the component an alt-source? Find the layouts in the alt folder
        $altSource = $component->getConfigArray()['src']['value'] ?? null;
        if ($altSource) {
            return $this->findForFolder((int)$altSource);
        }

        $identifier = (int)$component->getIdentifier();
        if ($component->isHomepage()) {
            return $this->findForFolder($identifier);
        }
        if ($component->isDashboard()) {
            return $this->findForDashboard($identifier);
        }
        if ($component->isHomepageTemplate()) {
            return $this->findForHomepageTemplate($identifier);
        }
        return $this->createCollection();
    }


    /**
     * Create a new tile layout for this component.
     * * sets the heading to the component's heading
     * * ensures the tile layout gets attached to the component's context
     *
     * The object has not yet been persisted.
     *
     * @param Component_Instance $component
     * @return Model
     */
    public function createForComponent(Component_Instance $component): Model
    {
        $model = $this->createModel();
        $model->setHeading($component->getConfigByKey('componentname')->getValue() ?: 'Tiles');

        $identifier = (int)$component->getIdentifier();
        if ($component->isHomepage()) {
            $model->setFolder($this->getJoinMapper('tile_layout_for_folder')->findById($identifier));
        } elseif ($component->isDashboard()) {
            $model->setDashboardRole($this->getJoinMapper('tile_layout_for_dashboard')->findById($identifier));
        } elseif ($component->isHomepageTemplate()) {
            $model->setHomepageTemplate(
                $this->getJoinMapper('tile_layout_for_homepage_template')->findById($identifier)
            );
        }
        return $model;
    }


    /**
     * Find tile layouts for this folder.
     *
     * @param int $folderId
     * @return GenericCollection|Model[]
     */
    public function findForFolder(int $folderId): GenericCollection
    {
        return $this->findByColumn('f.id', $folderId);
    }


    /**
     * Find tile layouts for this role's dashboard.
     *
     * @param int $roleId
     * @return GenericCollection|Model[]
     */
    public function findForDashboard(int $roleId): GenericCollection
    {
        return $this->findByColumn('r.id', $roleId);
    }


    /**
     * Find tile layouts for this homepage template.
     *
     * @param int $homepageTemplateId
     * @return GenericCollection|Model[]
     */
    public function findForHomepageTemplate(int $homepageTemplateId): GenericCollection
    {
        return $this->findByColumn('h.id', $homepageTemplateId);
    }
}
