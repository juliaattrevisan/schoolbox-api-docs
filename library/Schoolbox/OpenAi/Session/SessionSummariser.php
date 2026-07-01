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
use Schoolbox\OpenAi\Moderation\Event\Status as ModerationEventStatus;

/**
 * These types are shapes of partial database rows required to summarise logs.
 *
 * A partial row of the `ai_session_log` table:
 * (OpenAi\Session\Log\Model isn't used here because that includes more data than is needed here.)
 * @phpstan-type SessionLogSummaryRow array{
 *     id: int,
 *     ai_session_id: string,
 *     created_at: string,
 *     log_format: string|null,
 *     return_code: int|null,
 *     error_message: string|null
 * }
 *
 * A partial row of the `ai_moderation_event` table:
 * (OpenAi\Moderation\Event\Model isn't used here because that includes more data than is needed here.)
 * @phpstan-type ModerationEventSummaryRow array{
 *     id: int,
 *     target_ai_session_id: string,
 *     status: string|null
 * }
 */
class SessionSummariser
{
    private const int HTTP_STATUS_CODE_OK = 200;
    private const array MODERATION_STATUS_PRIORITY = [
        ModerationEventStatus::FURTHER_ACTION_REQUIRED,
        ModerationEventStatus::REQUIRES_ATTENTION,
        ModerationEventStatus::FURTHER_ACTION_TAKEN,
        ModerationEventStatus::NO_ACTION_REQUIRED,
        ModerationEventStatus::NO_BEHAVIOUR_FLAGGED,
    ];

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
     * Fetch summary-relevant moderation event rows for the supplied session IDs, indexed by target_ai_session_id.
     *
     * @param string[] $sessionIds
     * @return array<string, list<ModerationEventSummaryRow>>
     */
    public function getModerationEventRowsBySessionId(array $sessionIds): array
    {
        $sessionIds = array_values(array_unique($sessionIds));

        if (!$sessionIds) {
            return [];
        }

        $moderationEventRowsBySessionId = array_fill_keys($sessionIds, []);
        $statement = $this->database->prepare(
            'SELECT
                id,
                target_ai_session_id,
                status
            FROM `ai_moderation_event`
            WHERE target_ai_session_id IN ' . Builder::getArrayPlaceholders($sessionIds, 'session_id') . '
            ORDER BY target_ai_session_id ASC, id ASC'
        );

        foreach (Builder::getArrayParameters($sessionIds, 'session_id') as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $sessionId = (string)$row['target_ai_session_id'];
            $moderationEventRowsBySessionId[$sessionId][] = [
                'id' => (int)$row['id'],
                'target_ai_session_id' => $sessionId,
                'status' => null === $row['status'] ? null : (string)$row['status'],
            ];
        }

        return $moderationEventRowsBySessionId;
    }

    /**
     * Hydrate a session with the derived fields used by the admin usage list.
     *
     * Session log rows must be supplied in the order used to determine the first viewable log.
     * Rows from $this->getSessionLogRowsBySessionId() meet this requirement.
     *
     * @param Model $session
     * @param list<SessionLogSummaryRow> $sessionLogRows
     * @param list<ModerationEventSummaryRow> $moderationEventRows
     * @return void
     */
    public function hydrateSessionWithSummaryInfo(Model $session, array $sessionLogRows, array $moderationEventRows): void
    {
        $derivedLogFormat = null;
        $derivedHasError = false;
        $moderationStatus = null;
        $moderationStatusPriority = count(self::MODERATION_STATUS_PRIORITY);

        foreach ($sessionLogRows as $row) {
            if (null === $derivedLogFormat && null !== $row['log_format']) {
                $derivedLogFormat = $row['log_format'];
            }

            $returnCode = $row['return_code'];
            if (
                null !== $row['error_message']
                || (null !== $returnCode && self::HTTP_STATUS_CODE_OK !== $returnCode)
            ) {
                $derivedHasError = true;
            }
        }

        foreach ($moderationEventRows as $row) {
            $rowStatusPriority = $this->getModerationStatusPriority($row['status']);

            if ($rowStatusPriority < $moderationStatusPriority) {
                $moderationStatus = $row['status'];
                $moderationStatusPriority = $rowStatusPriority;
            }
        }

        $session
            ->setDerivedLogFormat($derivedLogFormat)
            ->setDerivedHasError($derivedHasError)
            ->setDerivedModerationStatus($moderationStatus);
    }

    /**
     * @param string|null $status
     * @return int
     */
    private function getModerationStatusPriority(?string $status): int
    {
        $status = null === $status ? null : ModerationEventStatus::tryFrom($status);
        $priority = array_search($status, self::MODERATION_STATUS_PRIORITY, true);

        return false === $priority ? count(self::MODERATION_STATUS_PRIORITY) : $priority;
    }
}
