<?php

/**
 * @copyright   Copyright 2032, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Template;

use PDOStatement;
use Schoolbox\GenericCollection;
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
        $this->uses('courseInherit', self::TYPE_BOOLEAN);
        $this->uses('unitInherit', self::TYPE_BOOLEAN);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('courseTemplate', 'courseTemplate')},
                {$this->getJoinColumnString('unitTemplate', 'unitTemplate')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('courseTemplate', '', 'courseTemplate', true)}
            INNER JOIN {$this->getJoinSql('unitTemplate', '', 'unitTemplate', true)}
        ";
    }

    protected function hydrateSelect(PDOStatement $statement)
    {
        $templates = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $template = $this->hydrate($row);
            /** @var null|HomepageTemplate $courseTemplate */
            $courseTemplate = $this->hydrateJoin($row, 'courseTemplate', 'courseTemplate');
            $template->setCourseTemplate($courseTemplate);
            /** @var null|HomepageTemplate $unitTemplate */
            $unitTemplate = $this->hydrateJoin($row, 'unitTemplate', 'unitTemplate');
            $template->setUnitTemplate($unitTemplate);
            $templates->add($template);
        }
        return $templates;
    }
}
