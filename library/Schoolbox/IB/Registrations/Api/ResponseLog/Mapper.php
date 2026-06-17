<?php

/**
 * IB registration API response log mapper
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Api\ResponseLog;

use PDO;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Registrations\Sessions\Mapper as RegistrationSessionMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Mapper as RegistrationSessionStudentMapper;
use Schoolbox\Mapper as BaseMapper;

/**
 * @method Model createModel($idValue = null)
 * @method ?Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    protected function mapping(): void
    {
        $this->hasTable('ib_registration_api_response_log');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('registration_session_id', RegistrationSessionMapper::class, 'id', null, null, 'registrationSession', 'registrationSession');
        $this->joins('registration_session_student_id', RegistrationSessionStudentMapper::class, 'id', null, null, 'registrationSessionStudent', 'registrationSessionStudent');
        $this->uses('msg_id', self::TYPE_STRING, null, 'msgId');
        $this->uses('request_type', self::TYPE_STRING, null, 'requestType');
        $this->uses('status', self::TYPE_STRING);
        $this->uses('message', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
    }

    /**
     * @return GenericCollection|Model[]
     */
    public function findLatestErrorsForSession(int $registrationSessionId): GenericCollection
    {
        $query = "
            SELECT ranked.*
            FROM (
                SELECT l.*,
                       ROW_NUMBER() OVER (
                           PARTITION BY l.registration_session_student_id
                           ORDER BY l.created_at DESC, l.id DESC
                       ) AS row_num
                FROM {$this->getTable()} l
                WHERE l.registration_session_id = :registrationSessionId
                  AND l.registration_session_student_id IS NOT NULL
                  AND l.status = :status
            ) ranked
            WHERE ranked.row_num = 1
            ORDER BY ranked.created_at DESC, ranked.id DESC
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'registrationSessionId' => $registrationSessionId,
            'status' => Model::STATUS_ERROR,
        ]);

        $logs = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $log = $this->hydrate($row);

            if ($log instanceof Model) {
                $logs->add($log);
            }
        }

        return $logs;
    }
}
