<?php

/**
 * IB Registration Session Mapper
 * @author    Haris Ali
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions;

use PDO;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\IB\SystemPurpose\Mapper as SystemPurposeMapper;
use Schoolbox\Mapper as BaseMapper;

/**
 * Class Mapper
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 *
 * @package Schoolbox\IB\Registrations\Sessions
 */
class Mapper extends BaseMapper
{
    public const string EXAM_SESSION_MAY = 'May';
    public const string EXAM_SESSION_NOVEMBER = 'November';
    public const array EXAM_SESSIONS = [
        self::EXAM_SESSION_MAY,
        self::EXAM_SESSION_NOVEMBER,
    ];
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('ib_registration_sessions');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('system_purpose_id', SystemPurposeMapper::class, 'id', null, null, 'systemPurpose', 'systemPurpose');
        $this->uses('academic_year', self::TYPE_STRING, null, 'academicYear');
        $this->uses('exam_session', self::TYPE_STRING, self::COLUMN_ENUM, 'examSession', Model::EXAM_SESSIONS);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('created_by', self::TYPE_INTEGER, null, 'createdBy');
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('updated_by', self::TYPE_INTEGER, null, 'updatedBy');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->uses('deleted_by', self::TYPE_INTEGER, null, 'deletedBy');
    }

    /**
     * @inheritDoc
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('systemPurpose', 'systemPurpose')}
        ";
    }

    /**
     * @inheritDoc
     */
    protected function getFromString(): string
    {
        return "
            FROM      {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('systemPurpose', '', 'systemPurpose')}
        ";
    }

    /**
     * @param PDOStatement $statement
     * @return GenericCollection
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        /** @var SystemPurposeMapper $systemPurposeMapper */
        $systemPurposeMapper = $this->getJoinMapper('systemPurpose');
        $sessions = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $session = $this->hydrate($row);

            if ($session) {
                $session->setSystemPurpose($systemPurposeMapper->hydrate($row, 'systemPurpose'));
                $sessions[$session->getId()] = $session;
            }
        }

        return $sessions;
    }
}
