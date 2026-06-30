<?php

/**
 * Helper for summarising AI session logs.
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;

/**
 * This is the lightweight row shape required to summarise logs.
 *
 * @phpstan-type SessionLogSummaryRow array{
 *     id: int,
 *     ai_session_id: string,
 *     created_at: string,
 *     log_format: string|null,
 *     return_code: int|null,
 *     error_message: string|null
 * }
 */
class SessionSummariser
{
    private const int HTTP_STATUS_CODE_OK = 200;

    /**
     * @param IConnector $database
     */
    public function __construct(private IConnector $database)
    {
    }

    /**
     * Fetch summary-relevant log rows for the supplied session IDs, indexed by ai_session_log.ai_session_id.
     *
     * @param string[] $sessionIds
     * @return array<string, list<SessionLogSummaryRow>>
     */
    public function getSessionLogRowsBySessionId(array $sessionIds): array
    {
        $sessionIds = array_values(array_unique($sessionIds));

        if (!$sessionIds) {
            return [];
        }

        $sessionLogRowsBySessionId = array_fill_keys($sessionIds, []);
        $statement = $this->database->prepare(
            'SELECT
                id,
                ai_session_id,
                created_at,
                log_format,
                return_code,
                error_message
            FROM `ai_session_log`
            WHERE ai_session_id IN ' . Builder::getArrayPlaceholders($sessionIds, 'session_id') . '
            ORDER BY ai_session_id ASC, created_at ASC, id ASC'
        );

        foreach (Builder::getArrayParameters($sessionIds, 'session_id') as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $sessionId = (string)$row['ai_session_id'];
            // Normalise raw PDO values into the documented SessionLogSummaryRow shape.
            $sessionLogRowsBySessionId[$sessionId][] = [
                'id' => (int)$row['id'],
                'ai_session_id' => $sessionId,
                'created_at' => (string)$row['created_at'],
                'log_format' => null === $row['log_format'] ? null : (string)$row['log_format'],
                'return_code' => null === $row['return_code'] ? null : (int)$row['return_code'],
                'error_message' => null === $row['error_message'] ? null : (string)$row['error_message'],
            ];
        }

        return $sessionLogRowsBySessionId;
    }

    /**
     * Summarise session log rows into the derived fields used by the admin usage list.
     *
     * Session log rows must be supplied in the order used to determine the first viewable log.
     * Rows from $this->getSessionLogRowsBySessionId() meet this requirement.
     *
     * @param list<SessionLogSummaryRow> $sessionLogRows
     * @return array{derived_log_format: string|null, derived_has_error: bool}
     */
    public function summariseSessionLogRows(array $sessionLogRows): array
    {
        $summary = [
            'derived_log_format' => null,
            'derived_has_error' => false,
        ];

        foreach ($sessionLogRows as $row) {
            if (null === $summary['derived_log_format'] && null !== $row['log_format']) {
                $summary['derived_log_format'] = $row['log_format'];
            }

            $returnCode = $row['return_code'];
            if (
                null !== $row['error_message']
                || (null !== $returnCode && self::HTTP_STATUS_CODE_OK !== $returnCode)
            ) {
                $summary['derived_has_error'] = true;
            }
        }

        return $summary;
    }
}
