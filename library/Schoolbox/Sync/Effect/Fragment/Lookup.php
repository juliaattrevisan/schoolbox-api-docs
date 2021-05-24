<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Effect\Fragment;

use Schoolbox\Collection;
use Schoolbox\ValueLabelInterface;

/**
 * Class Lookup
 *
 * Extract a value/label tuple from any of the Lookup possibilities
 * found in the UserMapper. These are typically used to construct
 * select dropdowns in Effect Fragments
 *
 * @package Schoolbox\Sync\Effect\Fragment
 */
class Lookup
{

    /**
     * A list of mappers to use when retrieving lookup data
     *
     * @var array
     */
    private $allLookups = [
        'residentialHouse' => '\HouseResidentialMapper',
        'schoolHouse' => '\HouseMapper',
        'role' => '\Schoolbox\RoleMapper',
        'year' => '\Schoolbox\YearLevel\Mapper',
        'campusCollectionRemove' => '\Schoolbox\CampusMapper',
        'campusCollection' => '\Schoolbox\CampusMapper',
        'userAccessRead' => '\Schoolbox\Resource\GroupMapper',
        'userAccessWrite' => '\Schoolbox\Resource\GroupMapper',
        'userAccessRemove' => '\Schoolbox\Resource\GroupMapper',
    ];

    /**
     * Get lookup data
     *
     * @param string $userField
     * @param string|null $roleType
     * @return Collection
     */
    public function findData(string $userField, ?string $roleType = null): Collection
    {
        $stack = new Collection();

        if (array_key_exists($userField, $this->allLookups)) {
            $mapperName = $this->allLookups[$userField];

            /* Spawn a mapper so we can utilise its findAll()
             */
            if (class_exists($mapperName)) {
                $mapper = new $mapperName();

                if ($userField === 'role') {
                    /* Special Case for Role Mapper: this mapper should restrict
                     * possibilities to those appropriate for the current Ruleset
                     */
                    $allResults = $mapper->findByColumn('group_type', $roleType);
                } elseif (in_array($userField, ['userAccessRead', 'userAccessWrite', 'userAccessRemove'])) {
                    /* Special case for userAccess* options, which need folder selection
                     * in the ui
                     */
                    $allResults = $mapper->findFolders();
                } else {
                    $allResults = $mapper->findAll();
                }

                /* Get key/value version of a model
                 * This varies depending on the model
                 */
                foreach ($allResults as $currentResult) {
                    if ($currentResult instanceof ValueLabelInterface) {
                        $pair = $currentResult->findValueLabelPair();

                        $stack->setOffset($pair['value'], $pair['label']);
                    }
                }
            }
        }

        return $stack;
    }
}
