<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\UserRelation\Hydrator;

use Schoolbox\External\UserRelation;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Sets a field in the user based on the value of a key in the imported row,
 * if that key exists in the row.
 *
 * @package Schoolbox\External\Importer\UserRelation\Hydrator
 */
class ExternalId implements HydratorInterface
{
    /**
     * The key in $row to set the value in the user to.
     *
     * @var string
     */
    protected $fieldname;

    /**
     * @var UserMapper
     */
    protected $mapper;

    /**
     * Constructor
     *
     * @param string     $fieldname
     * @param UserMapper $mapper
     */
    public function __construct($fieldname, UserMapper $mapper)
    {
        $this->fieldname = $fieldname;
        $this->mapper    = $mapper;
    }

    /**
     * @inheritdoc
     */
    public function hydrate($row, $relations)
    {
        // Relations not set? Don't update them
        if (isset($row[$this->fieldname]) && strlen($row[$this->fieldname])) {
            $newRelations = [];
            foreach (explode(',', $row[$this->fieldname]) as $externalId) {
                $externalId = trim($externalId);
                if (strlen($externalId)) {
                    $newRelation = new User();
                    $newRelation->setExternalId($externalId);
                    $newRelations[] = $newRelation;
                }
            }
            return $newRelations;
        }
        return $relations;
    }
}
