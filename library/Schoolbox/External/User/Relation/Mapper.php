<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\User\Relation;

use Schoolbox\External\User;

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
        $this->uses('emergency_contact', self::TYPE_BOOLEAN);
        $this->uses('type', self::TYPE_STRING);
        $this->joins('child_profile_id', User\Mapper::class, 'sis_user_profile_id', null, null, 'child_profile_id', 'child_profile_id');
        $this->joins('user_profile_id', User\Mapper::class, 'sis_user_profile_id', null, null, 'user_profile_id', 'user_profile_id');
    }
}
