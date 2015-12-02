<?php
/**
 * Lookup
 *
 * Extract a value/label tuple from any of the Lookup possibilities
 * found in the UserMapper. These are typically used to construct
 * select dropdowns in Effect Fragments
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Effect\Fragment;

use \Schoolbox\Collection;
use \Schoolbox\ValueLabelInterface;

class Lookup {

    /**
     * A list of mappers to use when retrieving lookup data
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
     * @return Collection
     */
    public function findData($userField) {
        $stack = new Collection;

        if (in_array($userField, array_keys($this->allLookups))) {
            $mapperName = $this->allLookups[$userField];

            /* Spawn a mapper so we can utilise its findAll()
             */
            if (class_exists($mapperName)) {
                $mapper = new $mapperName();
                $allResults = $mapper->findAll();

                /* Get key/value version of a model
                 * This varies depending on the model
                 */
                foreach ($allResults as $currentResult) {
                    if ($currentResult instanceof ValueLabelInterface) {
                        $stack->add($currentResult->findValueLabelPair());
                    }
                }
            }
        }

        return $stack;
    }
} 