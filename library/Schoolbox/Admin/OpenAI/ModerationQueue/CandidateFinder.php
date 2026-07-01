<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\ModerationQueue;

use Alaress\Database\Connector\IConnector;
use Schoolbox\OpenAi\Feature;

class CandidateFinder
{
    /**
     * @param IConnector $database The database connection used for queue candidate lookup.
     */
    public function __construct(private readonly IConnector $database)
    {
    }

    /**
     * Finds sessions that have never been moderated, or have newer logs than their latest moderation event.
     *
     * @param int $limit  The maximum number of candidates to return.
     * @param int $offset The number of eligible candidates to skip.
     * @return Candidate[]
     */
    public function find(int $limit, int $offset = 0): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $statement = $this->database->prepare("
            SELECT
                session_id,
                last_message_sent_at,
                last_moderated_at
            FROM (
                SELECT
                    `ai_session`.id AS session_id,
                    MAX(`ai_session_log`.created_at) AS last_message_sent_at,
                    (
                        SELECT MAX(`ai_moderation_event`.created_at)
                        FROM `ai_moderation_event`
                        WHERE `ai_moderation_event`.target_ai_session_id = `ai_session`.id
                    ) AS last_moderated_at
                FROM `ai_session`
                JOIN `ai_session_log` ON `ai_session_log`.ai_session_id = `ai_session`.id
                WHERE `ai_session`.feature <> :excludedFeature
                GROUP BY `ai_session`.id
            ) candidates
            WHERE last_moderated_at IS NULL
                OR last_message_sent_at > last_moderated_at
            ORDER BY last_message_sent_at ASC, session_id ASC
            LIMIT {$limit}
            OFFSET {$offset}
        ");
        $statement->execute(['excludedFeature' => Feature::AI_MODERATION_AGENT->value]);

        return array_map(
            $this->hydrateCandidate(...),
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    /**
     * @param array{session_id: string, last_message_sent_at: string, last_moderated_at: string|null} $row
     * @return Candidate
     */
    private function hydrateCandidate(array $row): Candidate
    {
        return new Candidate(
            $row['session_id'],
            new \Alaress_Date_Format($row['last_message_sent_at'], \Alaress_Date_Format::DATABASE_FORMAT),
            null === $row['last_moderated_at']
                ? null
                : new \Alaress_Date_Format($row['last_moderated_at'], \Alaress_Date_Format::DATABASE_FORMAT),
        );
    }
}
