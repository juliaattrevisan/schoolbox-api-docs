<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Field;

use Alaress\Database\Predicate\Builder;
use Schoolbox\GenericCollection;

/**
 * Class Mapper
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method null|Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection createCollection($contents = [])
 * @method Model[]|GenericCollection findAll($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('profile_field');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('roletype', self::TYPE_STRING);
        $this->uses('field', self::TYPE_STRING);
        $this->uses('display_as', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('enabled', self::TYPE_INTEGER);
        $this->uses('term', self::TYPE_STRING);
    }


    /**
     * Find $viewee's fields that $viewer can see, when $viewer
     * visits $viewee's profile screen.
     *
     * @param $viewee
     * @param $viewer
     * @return GenericCollection
     * @throws \DebugBar\DebugBarException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function findRenderableFields($viewee, $viewer): GenericCollection
    {
        $collection = $this->createCollection();

        $vieweeCampusIds = $viewee->getCampusCollection()->indexBy('id')->keys();

        $params = [
            /* What kind of user is $viewee - staff/student/parent?
             * This determines which fieldset to load
             */
            'vieweeRoleType' => $viewee->getRole()->getType(),
            /* Viewer's roletype determine visibility
             */
            'viewerRoleType' => $viewer->getRole()->getType(),
            /* You'd probably never change this, but always return enabled fields only
             */
            'enabled' => true
        ];

        /* Viewee's campuses determine availability
         */
        $vieweeCampusPlaceholders = Builder::getArrayPlaceholders($vieweeCampusIds, 'campusId');
        $params = array_merge($params, Builder::getArrayParameters($vieweeCampusIds, 'campusId'));

        /* Find an ordered list of fields there enabled for $viewee.
         * This is the maximum fields that could be rendered on a profile screen
         */
        $query = "
            SELECT f.*
            FROM {$this->getTable()} f
            INNER JOIN profile_field_campus_availability pfca ON f.id = pfca.profile_field_id
            INNER JOIN profile_field_roletype_visibility pfrv ON f.id = pfrv.profile_field_id
            WHERE f.roletype = :vieweeRoleType
            AND pfca.campus_id IN {$vieweeCampusPlaceholders}
            AND pfrv.roletype = :viewerRoleType
            AND f.enabled = :enabled
            ORDER BY f.sequence ASC 
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $field = $this->hydrate($row);

            if ($field) {
                $collection->add($field);
            }
        }

        return $collection;
    }
}
