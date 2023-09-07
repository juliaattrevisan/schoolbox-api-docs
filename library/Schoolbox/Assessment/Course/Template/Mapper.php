<?php

/**
 * @copyright   Copyright 2032, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Template;

use Schoolbox\GenericCollection;
use Schoolbox\Resource\HomepageTemplateMapper;

/**
 * Class Mapper
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('courseTemplates');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->joins('courseTemplateId', HomepageTemplateMapper::class, 'id', null, null, 'courseTemplate', 'courseTemplate');
        $this->joins('unitTemplateId', HomepageTemplateMapper::class, 'id', null, null, 'unitTemplate', 'unitTemplate');
        $this->uses('courseInherit', self::TYPE_BOOLEAN);
        $this->uses('unitInherit', self::TYPE_BOOLEAN);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}
