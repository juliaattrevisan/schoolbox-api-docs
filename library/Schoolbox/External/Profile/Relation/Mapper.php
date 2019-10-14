<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Profile\Relation;

use Schoolbox\External\Profile;

/**
 * Class Mapper
 *
 * @package Schoolbox\External\Profile\Relation
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('sis_user_profile_relation');
        $this->hasModel(Model::class);
        $this->uses('child_profile_id', self::TYPE_INTEGER);
        $this->uses('user_profile_id', self::TYPE_INTEGER);
        $this->uses('type', self::TYPE_STRING);
        $this->joins('child_profile_id', Profile\Mapper::class, 'id', null, null, 'childProfileId', 'childProfileId');
        $this->joins('user_profile_id', Profile\Mapper::class, 'id', null, null, 'userProfileId', 'userProfileId');
    }
}
