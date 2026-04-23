<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Group\Subject\Language;

use PDO;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Group\Subject\Mapper as GroupSubjectMapper;
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
        $this->joins('group_subject_id', GroupSubjectMapper::class, 'id', null, null, 'groupSubject', 'group_subject');
        $this->joins('language_id', LanguageMapper::class, 'id', null, null, 'language', 'language');
        $this->uses('class_code', self::TYPE_STRING);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        $groupSubjectMapper = $this->getJoinMapper('group_subject');
        $languageMapper = $this->getJoinMapper('language');

        return "
            SELECT
                {$this->getColumnString()},
                {$groupSubjectMapper->getColumnString('gs')},
                {$languageMapper->getColumnString('l')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM `{$this->table}`
            INNER JOIN {$this->getJoinSql('group_subject', '', 'gs', false)}
            INNER JOIN {$this->getJoinSql('language', '', 'l', false)}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $groupSubjectMapper = $this->getJoinMapper('group_subject');
        $languageMapper = $this->getJoinMapper('language');
        $collection = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $groupSubjectLanguage = $this->hydrate($row);
            $groupSubjectLanguage->setGroupSubject($groupSubjectMapper->hydrate($row, 'gs'));
            $groupSubjectLanguage->setLanguage($languageMapper->hydrate($row, 'l'));
            $collection[$groupSubjectLanguage->getId()] = $groupSubjectLanguage;
        }

        return $collection;
    }
}
