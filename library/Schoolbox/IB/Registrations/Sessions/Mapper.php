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
use Schoolbox\User;
use Schoolbox\UserMapper;

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
        $this->uses('exam_session', self::TYPE_STRING, null, 'examSession');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'createdBy', 'createdBy');
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->joins('updated_by', UserMapper::class, 'id', null, null, 'updatedBy', 'updatedBy');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins('deleted_by', UserMapper::class, 'id', null, null, 'deletedBy', 'deletedBy');
    }

    /**
     * @inheritDoc
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('systemPurpose', 'systemPurpose')},
                   {$this->getJoinColumnString('createdBy', 'createdBy')},
                   {$this->getJoinColumnString('updatedBy', 'updatedBy')},
                   {$this->getJoinColumnString('deletedBy', 'deletedBy')}
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
            INNER JOIN {$this->getJoinSql('createdBy', '', 'createdBy', true)}
            LEFT JOIN {$this->getJoinSql('updatedBy', '', 'updatedBy', true)}
            LEFT JOIN {$this->getJoinSql('deletedBy', '', 'deletedBy', true)}
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
        /** @var UserMapper $createdByMapper */
        $createdByMapper = $this->getJoinMapper('createdBy');
        /** @var UserMapper $updatedByMapper */
        $updatedByMapper = $this->getJoinMapper('updatedBy');
        /** @var UserMapper $deletedByMapper */
        $deletedByMapper = $this->getJoinMapper('deletedBy');
        $sessions = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $session = $this->hydrate($row);

            if ($session) {
                $session->setSystemPurpose($systemPurposeMapper->hydrate($row, 'systemPurpose'));
                /** @var User|null $createdBy */
                $createdBy = $createdByMapper->hydrate($row, 'createdBy');
                /** @var User|null $updatedBy */
                $updatedBy = $updatedByMapper->hydrate($row, 'updatedBy');
                /** @var User|null $deletedBy */
                $deletedBy = $deletedByMapper->hydrate($row, 'deletedBy');

                if ($createdBy) {
                    $session->setCreatedBy($createdBy);
                }

                if ($updatedBy) {
                    $session->setUpdatedBy($updatedBy);
                }

                if ($deletedBy) {
                    $session->setDeletedBy($deletedBy);
                }

                $sessions[$session->getId()] = $session;
            }
        }

        return $sessions;
    }
}
