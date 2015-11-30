<?php
/**
 * Lookup
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Effect\Fragment;


class Lookup {

    /**
     * A list of mappers to use when retrieving
     * lookup data
     *
     * @var array
     */
    private $allLookups = [
        'residentialHouse' => '\HouseResidentialMapper',
        'schoolHouse' => '\HouseMapper',
    ];

    /**
     * Get lookup data
     *
     * @param $userField
     * @return array
     */
    public function findData($userField) {
        if (in_array($userField, array_keys($this->allLookups))) {
            $mapperName = $this->allLookups[$userField];

            /* Spawn a mapper so we can utilise its findAll()
             */
            if (class_exists($mapperName)) {
                $mapper = new $mapperName();
                $allResults = $mapper->findAll();

                $stack = [];

                /* Get key/value version of a model
                 * This varies depending on the model
                 */
                foreach ($allResults as $currentResult) {
                    $stack = '';
                }

                return $stack;
            }
        }

        return [];
    }
} 