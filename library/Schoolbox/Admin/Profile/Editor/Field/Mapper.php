<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Field;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Admin\Profile\Editor\Payload;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;

/**
 * Class Mapper
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method null|Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection findByColumnList($columnList, array $options = [])
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
        $this->uses('icon', self::TYPE_STRING);
        $this->uses('css', self::TYPE_STRING);

        $this->hasJoin(
            'availabilityCampus',
            [
                'multiple'  => true,
                'mapper'    => \Schoolbox\Admin\Profile\Editor\Field\Availability\Campus\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'profile_field_id',
            ]
        );

        $this->hasJoin(
            'visibility',
            [
                'multiple'  => true,
                'mapper'    => \Schoolbox\Admin\Profile\Editor\Field\Visibility\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'profile_field_id',
            ]
        );
    }


    /**
     * @inheritdoc
     *
     * @return string
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
            {$this->getJoinColumnString('availabilityCampus', 'availabilityCampus')},
            {$this->getJoinColumnString('visibility', 'visibility')}
        ";
    }


    /**
     * @inheritdoc
     *
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('availabilityCampus', null, 'availabilityCampus')}
            LEFT JOIN {$this->getJoinSql('visibility', null, 'visibility')}
        ";
    }


    /**
     * @inheritdoc
     *
     * @param \PDOStatement $statement
     * @return GenericCollection
     * @throws DefinitionException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(\PDOStatement $statement): GenericCollection
    {
        $idColumn = $this->getIdentifierColumn();

        /** @var \Schoolbox\Admin\Profile\Editor\Field\Availability\Campus\Mapper $availabilityMapper */
        $availabilityMapper = $this->getJoinMapper('availabilityCampus');

        /** @var \Schoolbox\Admin\Profile\Editor\Field\Visibility\Mapper $visibilityMapper */
        $visibilityMapper = $this->getJoinMapper('visibility');

        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if ($collection->offsetExists($row[$idColumn])) {
                $field = $collection->getOffset($row[$idColumn]);
            } else {
                $field = $this->hydrate($row);

                $field->setAvailabilityCampus($availabilityMapper->createCollection());
                $field->setVisibility($visibilityMapper->createCollection());

                $collection->setOffset($row[$idColumn], $field);
            }

            $availabilityCampus = $availabilityMapper->hydrate($row, 'availabilityCampus');

            if ($availabilityCampus) {
                $field->getAvailabilityCampus()->setOffset($availabilityCampus->getId(), $availabilityCampus);
            }

            $visibility = $visibilityMapper->hydrate($row, 'visibility');

            if ($visibility) {
                $field->getVisibility()->setOffset($visibility->getId(), $visibility);
            }
        }

        return $collection;
    }


    /**
     * Find $viewee's fields that $viewer can see, when $viewer
     * visits $viewee's profile screen.
     *
     * @param User $viewee
     * @param User $viewer
     * @return GenericCollection
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function findRenderableFields(\Schoolbox\User $viewee, \Schoolbox\User $viewer): GenericCollection
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
            /* Also include the SELF pseudo-roletype
             */
            'selfRoleType' => Payload::ROLETYPE_SELF,
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
         *
         * Note that we also send back the pfrv.roletype value for each field, because
         * visibility is more than just the roletype; there are special conditionals
         * that constrain roletype further, such as config options on a user's account.
         * A staff member might have visibility on a field, but that field itself is
         * subjected to a config var that isn't in this query. It's being checked elsewhere.
         */
        $query = "
            SELECT  
                f.*,
                pfrv.roletype
            FROM {$this->getTable()} f
            INNER JOIN profile_field_campus_availability pfca ON f.id = pfca.profile_field_id
            INNER JOIN profile_field_roletype_visibility pfrv ON f.id = pfrv.profile_field_id
            WHERE f.roletype = :vieweeRoleType
            AND pfca.campus_id IN {$vieweeCampusPlaceholders}
            AND (pfrv.roletype = :viewerRoleType OR pfrv.roletype = :selfRoleType)
            AND f.enabled = :enabled
            ORDER BY f.sequence ASC 
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);

        /* Additional visibility rules to be checked in twig land
         */
        $rules = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $field = $this->hydrate($row);

            $rules[$row['field']][] = $row['roletype'];

            if ($field) {
                $collection->setOffset($field->getId(), $field);
            }
        }

        new \Alaress_Probe($collection, $rules);
        return $collection;
    }
}
