<?php

declare(strict_types=1);

namespace Schoolbox\Profile\Button\Role\Availability;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping(): void
    {
        $this->hasTable('profile_button_role_availability');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('profile_button_id', self::TYPE_INTEGER);
        $this->uses('role_id', self::TYPE_INTEGER);
    }
}
