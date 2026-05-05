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
use Schoolbox\Resource\Folder\Code\Mapper as FolderCodeMapper;
use Schoolbox\Resource\Folder\Code\Model as FolderCodeModel;

use function array_filter;
use function array_unique;

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

        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'saveClassCodes']);
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

        $this->applyClassCodes($collection);

        return $collection;
    }

    /**
     * @param GenericCollection|Model[] $collection
     * @return void
     */
    private function applyClassCodes(GenericCollection $collection): void
    {
        $ids = array_filter($collection->getPropertyValues('id'));

        if ($ids === []) {
            return;
        }

        $params = $this->getArrayParameters($ids, 'id');
        $folderCodeMapper = $this->getClassCodeMapper();
        $statement = $this->database->prepare(
            "
            SELECT
                gslcc.group_subject_language_id,
                gslcc.class_code,
                {$folderCodeMapper->getColumnString('fc')}
            FROM ib_group_subject_language_class_codes gslcc
            LEFT JOIN folder_code fc ON fc.code = gslcc.class_code
                AND fc.deleted_at IS NULL
            WHERE gslcc.group_subject_language_id IN {$this->getArrayPlaceholders($ids, 'id')}
            ORDER BY fc.code ASC
        "
        );
        $statement->execute($params);

        $classCodes = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $languageId = (int) $row['group_subject_language_id'];
            $classCodes[$languageId] ??= $folderCodeMapper->createCollection();
            $classCodes[$languageId]->add(
                $row['fc.code'] === null
                    ? (new FolderCodeModel())->setCode($row['class_code'])
                    : $folderCodeMapper->hydrate($row, 'fc')
            );
        }

        foreach ($collection as $model) {
            $model->setClassCode($classCodes[$model->getId()] ?? $folderCodeMapper->createCollection());
        }
    }

    /**
     * @return FolderCodeMapper
     */
    private function getClassCodeMapper(): FolderCodeMapper
    {
        return FolderCodeMapper::getInstance();
    }

    /**
     * @param Model $model
     * @return void
     */
    public function saveClassCodes(Model $model): void
    {
        $this->database
            ->prepare('DELETE FROM ib_group_subject_language_class_codes WHERE group_subject_language_id = ?')
            ->execute([$model->getId()]);

        $classCodes = array_unique($model->getClassCode()->map(
            static fn(FolderCodeModel $classCode): string => (string) $classCode->getCode()
        )->getAll());

        if ($classCodes === []) {
            return;
        }

        $statement = $this->database->prepare(
            'INSERT IGNORE INTO ib_group_subject_language_class_codes
                (group_subject_language_id, class_code)
                VALUES (?, ?)'
        );

        foreach ($classCodes as $classCode) {
            $statement->execute([$model->getId(), $classCode]);
        }
    }
}
