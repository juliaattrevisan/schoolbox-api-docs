<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload\VictorianProgression;

class Precompiler
{
    /**
     * Find the most recent feedback per assessment from the $payloadStatement mega query
     * This isn't the most dashing or elegant way out, but it saves a groupwise maximum
     * on a mega join. The result set is always smaller than the initial one anyway.
     *
     * @param \PDOStatement $payloadStatement
     * @return array
     */
    public function precompile(\PDOStatement $payloadStatement): array
    {
        $stack = [];

        while ($row = $payloadStatement->fetch(\PDO::FETCH_ASSOC)) {
            $assessmentId = $row['assessment_id'];

            if (!isset($stack[$assessmentId])) {
                $stack[$assessmentId] = $row;
            }
        }

        return array_values($stack);
    }
}
