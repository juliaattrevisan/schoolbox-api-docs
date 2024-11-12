<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Tab;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Admin\Profile\Editor\Payload;
use Schoolbox\GenericCollection;
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
        $this->hasTable('profile_tab');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('roletype', self::TYPE_STRING);
        $this->uses('tab', self::TYPE_STRING);
        $this->uses('display_as', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);

        $this->hasJoin(
            'availabilityCampus',
            [
                'multiple'  => true,
                'mapper'    => \Schoolbox\Admin\Profile\Editor\Tab\Availability\Campus\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'profile_tab_id',
            ]
        );

        $this->hasJoin(
            'visibility',
            [
                'multiple'  => true,
                'mapper'    => \Schoolbox\Admin\Profile\Editor\Tab\Visibility\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'profile_tab_id',
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
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(\PDOStatement $statement): GenericCollection
    {
        $idColumn = $this->getIdentifierColumn();

        /** @var \Schoolbox\Admin\Profile\Editor\Tab\Availability\Campus\Mapper $availabilityMapper */
        $availabilityMapper = $this->getJoinMapper('availabilityCampus');

        /** @var \Schoolbox\Admin\Profile\Editor\Tab\Visibility\Mapper $visibilityMapper */
        $visibilityMapper = $this->getJoinMapper('visibility');

        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if ($collection->offsetExists($row[$idColumn])) {
                $tab = $collection->getOffset($row[$idColumn]);
            } else {
                $tab = $this->hydrate($row);

                $tab->setAvailabilityCampus($availabilityMapper->createCollection());
                $tab->setVisibility($visibilityMapper->createCollection());

                $collection->setOffset($row[$idColumn], $tab);
            }

            $availabilityCampus = $availabilityMapper->hydrate($row, 'availabilityCampus');

            if ($availabilityCampus) {
                $tab->getAvailabilityCampus()->setOffset($availabilityCampus->getId(), $availabilityCampus);
            }

            $visibility = $visibilityMapper->hydrate($row, 'visibility');

            if ($visibility) {
                $tab->getVisibility()->setOffset($visibility->getId(), $visibility);
            }
        }

        return $collection;
    }


    /**
     * Find $viewee's tabs that $viewer can see, when $viewer
     * visits $viewee's profile screen.
     *
     * @param User $viewee
     * @param User $viewer
     * @return GenericCollection
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    public function findRenderableTabs(\Schoolbox\User $viewee, \Schoolbox\User $viewer): GenericCollection
    {
        $collection = $this->createCollection();

        /* Intentionally exit early when parents view students outside of their family
         * Literally show nothing
         */
        if ($viewee->getRole()->isStudent() && $viewer->getRole()->isParent() && !$viewer->isParentOf($viewee)) {
            return $collection;
        }

        /* Otherwise, we have a data set to process
         */
        $vieweeCampusIds = $viewee->getCampusCollection()->indexBy('id')->keys();

        $params = [
            /* What kind of user is $viewee - staff/student/parent?
             * This determines which tabset to load
             */
            'vieweeRoleType' => $viewee->getRole()->getType(),
            /* Viewer's roletype determine visibility
             */
            'viewerRoleType' => $viewer->getRole()->getType(),
        ];

        /* Viewee's campuses determine availability
         */
        $vieweeCampusPlaceholders = Builder::getArrayPlaceholders($vieweeCampusIds, 'campusId');
        $params = array_merge($params, Builder::getArrayParameters($vieweeCampusIds, 'campusId'));

        /* Find an ordered list of tabs enabled for $viewee.
         * This is the maximum tabs that could be rendered on a profile screen
         *
         * Note that if you're viewing your own profile, we need to include tabs assigned
         * the SELF pseudo-roletype. If you're viewing someone else's profile, we can
         * exclude any tabs with the SELF pseudo-roletype.
         */
        if ($viewee->getId() === $viewer->getId()) {
            $query = "
                SELECT DISTINCT
                    f.*,
                    {$this->getJoinColumnString('visibility', 'ptrv')}
                FROM {$this->getTable()} f
                INNER JOIN profile_tab_campus_availability ptca ON f.id = ptca.profile_tab_id
                INNER JOIN profile_tab_roletype_visibility ptrv ON f.id = ptrv.profile_tab_id
                WHERE f.roletype = :vieweeRoleType
                AND ptca.campus_id IN {$vieweeCampusPlaceholders}
                AND (ptrv.roletype = :viewerRoleType OR ptrv.roletype = :selfRoleType)
                ORDER BY f.sequence ASC
            ";

            $params['selfRoleType'] = Payload::ROLETYPE_SELF;
        } else {
            $query = "
                SELECT DISTINCT
                    f.*,
                    {$this->getJoinColumnString('visibility', 'ptrv')}
                FROM {$this->getTable()} f
                INNER JOIN profile_tab_campus_availability ptca ON f.id = ptca.profile_tab_id
                INNER JOIN profile_tab_roletype_visibility ptrv ON f.id = ptrv.profile_tab_id
                WHERE f.roletype = :vieweeRoleType
                AND ptca.campus_id IN {$vieweeCampusPlaceholders}
                AND ptrv.roletype = :viewerRoleType
                ORDER BY f.sequence ASC
            ";
        }

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);

        /* Allow each roletype's visibility rule to be further analysed in twig land
         * because there are additional constraints which reduce this set of visibilities,
         * such as configuration of a user account or some other rule that isn't in
         * this sql query itself (also, some schools override this in custom work)
         */
        /** @var \Schoolbox\Admin\Profile\Editor\Tab\Visibility\Mapper $visibilityMapper */
        $visibilityMapper = $this->getJoinMapper('visibility');

        /* Additional visibility rules to be checked in twig land
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $instance = $this->hydrate($row);

            /* Start collating visibilities here
             */
            if ($instance && null === $collection->getOffset($instance->getId())) {
                $instance->setVisibility($visibilityMapper->createCollection());
                $collection->setOffset($instance->getId(), $instance);
            }

            $tab = $collection->getOffset($instance->getId());

            /* Remember the visibility rules for further scrutiny
             */
            /** @var Visibility\Model $visibility */
            $visibility = $visibilityMapper->hydrate($row, 'ptrv');

            /* Could set up a circular reference here, but probably not needed
             *   $visibility->setTab($tab);
             */
            $tab->getVisibility()->setOffset($visibility->getRoleType(), $visibility);
        }

        return $collection;
    }
}
