<?php

/**
 * Eportfolio_PermissionMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Eportfolio;

use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\User;

class PermissionMapper extends Schoolbox\Mapper
{

    /**
     * Define column/property mapping
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('eportfolio_permission');
        $this->hasModel(Permission::class);

        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'page_id',
            'property'   => 'pageId',
            'type'       => self::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'type',
            'property'   => 'type',
            'type'       => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'value',
            'property'   => 'value',
            'type'       => self::TYPE_INTEGER,
        ));
    }

    /**
     * Facade method
     *
     * Create/update all permissions for a particular page
     *
     * @param int $pageId
     * @param int $parent
     * @param array $group
     * @param array $individual
     */
    public function updatePermissions($pageId, $parent, $group, $individual)
    {
        $this->removeAllPermissions($pageId);

        $this->addParentPermissions($pageId, $parent);
        $this->addGroupPermissions($pageId, $group);
        $this->addIndividualPermissions($pageId, $individual);
    }

    /**
     * Remove all permissions for a particular page
     *
     * @param int $pageId
     */
    private function removeAllPermissions($pageId)
    {
        $statement = $this->database->prepare("
            DELETE FROM  `{$this->table}`
            WHERE page_id = :identifierValue
        ");

        $statement->bindParam(':identifierValue', $pageId);
        $statement->execute();
    }

    /**
     * Add parent permissions to a particular page
     *
     * @param int $pageId
     * @param int $parent
     */
    private function addParentPermissions($pageId, $parent)
    {
        if (is_numeric($parent)) {
            $permission = new Permission();
            $permission->setPageId($pageId);
            $permission->setType(Permission::TYPE_PARENT);
            $permission->setValue($parent);

            $this->save($permission);
        }
    }

    /**
     * Add group permissions to a particular page
     *
     * @param int $pageId
     * @param array $allGroups
     */
    private function addGroupPermissions($pageId, $allGroups)
    {
        if (is_array($allGroups) && count($allGroups)) {
            foreach ($allGroups as $currentGroup) {
                if (is_numeric($currentGroup)) {
                    $permission = new Permission();
                    $permission->setPageId($pageId);
                    $permission->setType(Permission::TYPE_GROUP);
                    $permission->setValue($currentGroup);

                    $this->save($permission);
                }
            }
        }
    }

    /**
     * Add individual permissions to a particular page
     *
     * @param int $pageId
     * @param array $allIndividuals
     */
    private function addIndividualPermissions($pageId, $allIndividuals)
    {
        if (is_array($allIndividuals) && count($allIndividuals)) {
            foreach ($allIndividuals as $currentIndividual) {
                if (is_numeric($currentIndividual)) {
                    $permission = new Permission();
                    $permission->setPageId($pageId);
                    $permission->setType(Permission::TYPE_STUDENT);
                    $permission->setValue($currentIndividual);

                    $this->save($permission);
                }
            }
        }
    }

    /**
     * Find the Parent permission settings for a particular page
     *
     * @param int $pageId
     * @return Schoolbox\Collection|Permission[]
     */
    public function findParentSetting($pageId)
    {
        return $this->findByColumnList(array(
            'page_id' => $pageId,
            'type' => Permission::TYPE_PARENT,
        ));
    }

    /**
     * Finds all permission entries for the given page, type and values.
     *
     * @param int    $pageId          An ePortfolio page ID
     * @param string $type            A type of permission: one of the
     *                                `Permission::TYPE_*` constants
     * @param array  $permittedValues A list of permitted values for the
     *                                permission
     * @return Collection|Permission[]
     */
    public function findValues(int $pageId, string $type, array $permittedValues): Collection
    {
        if (empty($permittedValues)) {
            return $this->createCollection();
        }
        return $this->findByPredicates(
            $this->getPredicateBuilder()
                 ->equals('page_id', $pageId)
                 ->equals('type', $type)
                 ->in('value', $permittedValues)
        );
    }


    /**
     * Get the specified student's permissions to the specified page
     *
     * @param Page $page
     * @param User $student
     * @return GenericCollection|Permission[]
     * @throws Schoolbox\Mapper\Exception If invalid column name specified
     */
    public function findStudentPermissions(Page $page, User $student): GenericCollection
    {
        return $this->findByColumnList([
            'page_id' => $page->getPageId(),
            'type'    => Permission::TYPE_STUDENT,
            'value'   => $student->getId(),
        ]);
    }


    /**
     * Get the set of groups who have view permission on the specified page
     *
     * @param Page $page
     * @return GenericCollection|Permission[]
     * @throws Schoolbox\Mapper\Exception If invalid column name specified
     */
    public function findGroupPermissions(Page $page): GenericCollection
    {
        return $this->findByColumnList([
            'page_id' => $page->getPageId(),
            'type'    => Permission::TYPE_GROUP,
        ]);
    }
}
