<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Help\Superstar;

use Alaress\Database\Connector\IConnector;

class PreviousAwards
{
    /**
     * @param IConnector $database
     */
    public function __construct(private IConnector $database)
    {
    }


    /**
     * Find all the previous badges awarded to people as per $badgeMapping set
     * We don't want to give the same achievement twice and the nuanced logic
     * makes this a bit harder in native sql megaqueries
     *
     * Instead we construct a list of previously awarded badges and do the
     * subtraction at runtime, which also allows us to say when someone actually
     * got the achievement.
     *
     * @param array $badgeMapping
     * @return array
     * @throws \PDOException
     */
    public function generate(array $badgeMapping): array
    {
        $ids = [];

        foreach ($badgeMapping as $unitSpecId => $mapping) {
            $ids[] = (int)$mapping[Candidacy::SUPERSTAR]['id'];
            $ids[] = (int)$mapping[Candidacy::STAR]['id'];
        }

        /* We're going to inject this straight in to the jugular
         *   1. It's hardcoded by us, manually, so the usual risk of sql injection avoided
         *   2. It's always cast to int, not that we'd ever sql inject ourselves unsafely anyway
         *   3. It's only surfaced in an admin-only report
         *   4. If you hate it enough you can predicate-builder this, but it goes slower
         *
         * If you have an omega ton of ids you might reach a query limit...but I doubt this will
         * be a practical concern. The OLC wouldn't be manageable under such circumstances.
         */
        $badgeIds = implode(',', $ids);

        $query = "
            SELECT 
                badge_id, 
                recipient_id,
                awarded_at
            FROM badge_user
            WHERE badge_id IN ({$badgeIds})
            AND status = :status
            AND deleted_at IS NULL
        ";

        $statement = $this->database->prepare($query);

        $statement->execute([
            'status' => \Schoolbox\Learning\Badge\Award\Model::AWARD_STATUS_ACHIEVEMENT
        ]);

        /* Remember a list of (user id, badge id) tuples
         * for a direct lookup via twig ugh
         */
        $stack = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $stack[$row['recipient_id']][$row['badge_id']] = $row['awarded_at'];
        }

        return $stack;
    }
}
