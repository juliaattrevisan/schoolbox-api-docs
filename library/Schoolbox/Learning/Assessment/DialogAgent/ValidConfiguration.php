<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent;

use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

class ValidConfiguration
{
    /**
     * @param \Schoolbox\Admin\DialogAgent\Scenario\Field\Context\Mapper $fieldContextMapper
     * @param \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Mapper $dialogAgentDataModelMapper
     */
    public function __construct(
        private \Schoolbox\Admin\DialogAgent\Scenario\Field\Context\Mapper $fieldContextMapper,
        private \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Mapper $dialogAgentDataModelMapper,
    ) {
    }


    /**
     * Decide if a fully-hydrated $assessment is actually also fully configured.
     *
     * This is used to prevent you trying to use an incomplete setup which can occur if you abandon the second
     * step in activity creation where you set up the AI
     *
     * @param Model $assessment
     * @return bool
     * @throws Exception
     * @throws Deleted
     */
    public function isSatisfiedBy(Model $assessment): bool
    {
        /* No scenario chosen
         */
        if (null === $assessment->getScenarioId()) {
            return false;
        }

        /* Assessment instances don't know about their sub-object's ids :(
         */
        $dialogAgentId = $this->dialogAgentDataModelMapper->findOneByColumn('assessment_id', $assessment->getId())->getId();

        /* Find all the contexts that an instructor was supposed to provide for each field in the scenario
         * These are used by the AI to evaluate learners' inputs
         */
        $fieldContexts = $this->fieldContextMapper->findByColumn('dialog_agent_id', $dialogAgentId);

        foreach ($fieldContexts as $fieldContext) {
            if ('' === trim((string)$fieldContext->getBody())) {
                return false;
            }
        }

        /* I guess you passed the barest minimum then
         */
        return true;
    }
}
