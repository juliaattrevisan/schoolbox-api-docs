<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent;

use Alaress\Database\Connector\IConnector;

class ValidConfiguration
{
    /**
     * This lets you use an Internal Database instance to do the relatively trivial evaluation
     */
    private const string QUERY = "
        SELECT COUNT(dasfc.id) AS numInvalid
        FROM dialog_agent_scenario_field_context dasfc
        INNER JOIN dialog_agent da ON dasfc.dialog_agent_id = da.id
        WHERE da.deleted_at IS NULL
        AND dasfc.deleted_at IS NULL
        AND (dasfc.body IS NULL OR 0 = LENGTH(TRIM(dasfc.body)))
        AND da.assessment_id = :assessmentId
    ";


    /**
     * @param IConnector $db
     */
    public function __construct(private IConnector $db)
    {
    }


    /**
     * Decide if a fully-hydrated $assessment is actually also fully configured.
     *
     * This is used to prevent you trying to use an incomplete setup which can occur if you abandon the second
     * step in activity creation where you set up the AI
     *
     * @param Model $assessment
     * @return bool
     */
    public function isSatisfiedBy(Model $assessment): bool
    {
        /* No scenario chosen
         */
        if (null === $assessment->getScenarioId()) {
            return false;
        }

        /* Find all the contexts that an instructor was supposed to provide for each field in the scenario
         * These are used by the AI to evaluate learners' inputs
         */
        $statement = $this->db->prepare(self::QUERY);

        $statement->execute([
            'assessmentId' => $assessment->getId()
        ]);

        return (int)$statement->fetch()['numInvalid'] === 0;
    }
}
