<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\GroupSubject\Language;

use PDO;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\IB\GroupSubject\Mapper as GroupSubjectMapper;
use Schoolbox\Language\Mapper as LanguageMapper;
use Schoolbox\Mapper as BaseMapper;

/**
 * Class Mapper
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 *
 * @package Schoolbox\IB\GroupSubject
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('ib_group_subject_languages');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('group_subject_id', GroupSubjectMapper::class, 'id', null, null, 'group_subject', 'group_subject');
        $this->joins('language_id', LanguageMapper::class, 'id', null, null, 'language', 'language');
        $this->uses('class_code', self::TYPE_STRING);
    }
}
