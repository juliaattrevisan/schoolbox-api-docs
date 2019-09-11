<?php
/**
 * Eportfolio_PageMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Eportfolio;

use Schoolbox;
use Schoolbox\UserMapper;
use Schoolbox\GenericCollection;

/**
 * @method Page findById($identifierValue)
 * @method Page createModel($idValue = null)
 * @method Page hydrate(array $row, $alias = '', $callback = null)
 */
class PageMapper extends Schoolbox\Mapper
{

    /**
     * Define column/property mapping
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('eportfolio_page');
        $this->hasModel(Page::class);

        $this->uses('page_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('page_title', self::TYPE_STRING);
        $this->joins('uid', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->uses('template', self::TYPE_INTEGER);
        $this->uses('left_column_width', self::TYPE_STRING);
        $this->uses('left_head_font', self::TYPE_STRING);
        $this->uses('left_head_bg', self::TYPE_STRING);
        $this->uses('left_font', self::TYPE_STRING);
        $this->uses('left_bg_primary', self::TYPE_STRING);
        $this->uses('right_column_width', self::TYPE_STRING);
        $this->uses('right_head_font', self::TYPE_STRING);
        $this->uses('right_head_bg', self::TYPE_STRING);
        $this->uses('right_font', self::TYPE_STRING);
        $this->uses('right_bg_primary', self::TYPE_STRING);
        $this->uses('top_head_font', self::TYPE_STRING);
        $this->uses('top_head_bg', self::TYPE_STRING);
        $this->uses('top_font', self::TYPE_STRING);
        $this->uses('top_bg_primary', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->hasJoin(
            'permissions', [
            'multiple'  => true,
            'mapper'    => PermissionMapper::class,
            'this_key'  => 'page_id',
            'other_key' => 'page_id',
        ]
        );
    }

    /**
     * Finds all the pages owned by $ownerId that $viewerId can see
     *
     * @param $viewerId
     * @param $ownerId
     * @return GenericCollection|Page[]
     */
    public function findViewablePages($viewerId, $ownerId)
    {
        $viewablePages = $this->createCollection();

        /* -_-
         */
        $groupHandler = new \Group();

        $isViewerParent = $groupHandler->isParentOfChild($ownerId);

        /* The categories the viewer is involved in
         */
        $allCategories = $groupHandler->involvedInCategoryList($viewerId);

        $categoryIds = array();

        if (is_array($allCategories) && count($allCategories)) {
            foreach ($allCategories as $currentCategory) {
                $categoryIds[] = (int)$currentCategory['id'];
            }
        }
        /* Get all the pages with their permissions
         */
        $allPages = $this->findPagesWithPermissions($ownerId);

        foreach ($allPages as $currentPage) {

            /* They can be a superuser
             */
            $isSuperuser = $_SESSION['su'];

            /* They can be a staff member
             */
            $isStaff = $groupHandler->isStaff();

            /* They can be the owner of the page
             */
            $isOwner = ($currentPage->getOwner()->getId() == $viewerId);

            if ($isSuperuser || $isStaff || $isOwner) {
                $viewablePages->setOffset($currentPage->getPageId(), $currentPage);
            } /* If there are any specific permissions, try those too
			 */
            else {
                foreach ($currentPage->getPermissions() as $currentPermission) {

                    /* They can be a parent (the getValue() here is a binary 1 or 0)
                     */
                    $isParent = (($currentPermission->getType() === Permission::TYPE_PARENT)
                        && $currentPermission->getValue() && $isViewerParent);

                    /* They can be in a group
                     */
                    $isInGroup = (($currentPermission->getType() === Permission::TYPE_GROUP)
                        && in_array((int)$currentPermission->getValue(), $categoryIds, true));

                    /* They can be allowed specifically
                     */
                    $isStudent = (($currentPermission->getType() === Permission::TYPE_STUDENT)
                        && ((int)$currentPermission->getValue() === (int)$viewerId));

                    if ($isParent || $isInGroup || $isStudent) {
                        $viewablePages->setOffset($currentPage->getPageId(), $currentPage);
                    }
                }
            }
        }

        /* Reindex everything
         */
        $viewablePages->reindex();

        return $viewablePages;
    }

    /**
     * Finds all the pages owned by $ownerId with associated permissions
     *
     * @param $ownerId
     * @return GenericCollection|Page[]
     */
    public function findPagesWithPermissions($ownerId)
    {
        $permission_mapper = $this->getJoinMapper('permissions');

        $query = "
            SELECT
            	{$this->getColumnString('ep')},
            	{$permission_mapper->getColumnString('p')}
            FROM `{$this->table}` ep
            LEFT JOIN {$this->getJoinSql('permissions', 'ep', 'p')}
            WHERE ep.uid = :ownerId
            ORDER BY created_at, ep.page_id ASC
        ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':ownerId', $ownerId);
        $statement->execute();

        $collection = $this->createCollection();
        $idCol      = "ep.{$this->getIdentifierColumn()}";
        foreach ($statement as $row) {
            if ($collection->offsetExists($row[$idCol])) {
                $pageItem = $collection->getOffset($row[$idCol]);
            } else {
                $pageItem = $this->hydrate($row, 'ep');
                $pageItem->setPermissions($permission_mapper->createCollection());
                $collection->setOffset($row[$idCol], $pageItem);
            }

            /* If a permission was parsed from this $row, hydrate
             * put the permission with its parent page
             */
            $permissionItem = $permission_mapper->hydrate($row, 'p');
            if ($permissionItem instanceof Permission) {
                $pageItem->getPermissions()->add($permissionItem);
            }
        }

        /* Reindex everything
         *
         * foreach() can't understand it when you don't reindex to zero-based array
         */
        $collection->reindex();

        return $collection;
    }

    /**
     * Finds all publish targets. Currently, this means all eportfolio pages
     * which have either a file list or image list controller.
     *
     * @param  int $user_id
     * @return array
     */
    public function findPublishablePages($user_id)
    {
        $query = "
            SELECT DISTINCT {$this->getColumnString('p')}
            FROM {$this->getTable()} p
            INNER JOIN component_instance ci ON p.page_id = ci.identifier AND ci.module='eportfolio'
            INNER JOIN component_definition cd ON ci.component_definition_id = cd.id
            WHERE p.uid = :userId
            AND   p.deleted_at IS NULL
            AND   cd.class IN (
                'Component_Eportfolio_FileListController',
                'Component_Eportfolio_ImageController',
                'Component_Eportfolio_ImageListController',
                'Component_Eportfolio_VideoController'
            )
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('userId', $user_id);
        $statement->execute();
        $return = array();
        foreach ($this->hydrateList($statement, 'p') as $page) {
            $return[$page->getPageId()] = array('page' => $page);
        }
        return $return;
    }
}
