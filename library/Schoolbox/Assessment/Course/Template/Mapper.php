<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Template;

use PDOStatement;
use Schoolbox\GenericCollection;
use UnexpectedValueException;
use Schoolbox\Resource\HomepageTemplate;
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
     * Initial Default template was created with db ID 1
     * This should be used for any courses that were previously created
     * and courses spawned by `Convert this page to a unit page` action
     */
    public const INITIAL_DEFAULT = 1;

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
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyActualTemplate']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'validateName']);
    }

    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('courseTemplate', 'courseTemplate')},
                {$this->getJoinColumnString('unitTemplate', 'unitTemplate')}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('courseTemplate', '', 'courseTemplate', true)}
            LEFT JOIN {$this->getJoinSql('unitTemplate', '', 'unitTemplate', true)}
        ";
    }

    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var HomepageTemplateMapper $homepageTemplateMapper */
        $homepageTemplateMapper = $this->getJoinMapper('courseTemplate');
        $templates = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $template = $this->hydrate($row);
            // if course template is No Homepage, then set that
            if (HomepageTemplate::HOMEPAGE_NONE !== $row['courseTemplateId']) {
                /** @var null|HomepageTemplate $courseTemplate */
                $courseTemplate = $this->hydrateJoin($row, 'courseTemplate', 'courseTemplate');
            } else {
                $courseTemplate = $homepageTemplateMapper->getNoHomepageTemplate();
            }
            $template->setCourseTemplate($courseTemplate);
            // if unit template is No Homepage, then set that
            if (HomepageTemplate::HOMEPAGE_NONE !== $row['unitTemplateId']) {
                /** @var null|HomepageTemplate $unitTemplate */
                $unitTemplate = $this->hydrateJoin($row, 'unitTemplate', 'unitTemplate');
            } else {
                $unitTemplate = $homepageTemplateMapper->getNoHomepageTemplate();
            }
            $template->setUnitTemplate($unitTemplate);
            $templates->add($template);
        }
        return $templates;
    }

    /** @inheritDoc */
    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC'];
    }

    /**
     * Validates templates selected are real
     *
     * Either -1 or one of HomepageTemplateMapper
     *
     * @param Model $model
     * @return bool
     * @throws UnexpectedValueException
     */
    protected function verifyActualTemplate(Model $model): bool
    {
        $templateList = $this->getJoinMapper('courseTemplate')->findAll(['deleted' => true, 'cache' => false])->indexBy('id')->keys();

        // test course first
        if (!(in_array($model->getCourseTemplate()->getId(), $templateList) || HomepageTemplate::HOMEPAGE_NONE === $model->getCourseTemplate()->getId())) {
            throw new UnexpectedValueException(
                "Unknown course page template"
            );
        }
        if (!(in_array($model->getUnitTemplate()->getId(), $templateList) || HomepageTemplate::HOMEPAGE_NONE === $model->getUnitTemplate()->getId())) {
            throw new UnexpectedValueException(
                "Unknown unit page template"
            );
        }
        return true;
    }

    /**
     * Validates that the name is not empty and is unique
     *
     * @param Model $model
     * @return bool
     * @throws UnexpectedValueException
     */
    protected function validateName(Model $model): bool
    {
        if (trim($model->getName()) === '') {
            throw new UnexpectedValueException("Template name cannot be empty");
        }

        if (!$this->isUnique(['name' => $model->getName()], $model->getId())) {
            throw new UnexpectedValueException(
                sprintf(
                    'A template with the name "%s" already exists, you must have a unique name',
                    $model->getName()
                )
            );
        }

        return true;
    }
}
