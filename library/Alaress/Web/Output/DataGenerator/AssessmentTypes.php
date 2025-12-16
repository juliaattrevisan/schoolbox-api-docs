<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\AssessmentType;

/**
 * Generate data for rendering a list of enabled assessment types.
 */
class AssessmentTypes implements IDataGenerator
{
    /**
     * @param IConfigLoader $configLoader
     */
    public function __construct(private IConfigLoader $configLoader) {
    }


    /**
     * @inheritdoc
     */
    public function generate(?array $chromeData = []): array
    {
        $ltiEnabled = $this->configLoader->get(ConfigMapper::NAME_LTI_ASSESSMENTS_ENABLE);

        // TODO: avoid this AI config copypasta to check if dialog agent is enabled
        $aiConfig = new \Schoolbox\Admin\OpenAI\Config();
        $featureConfig = $aiConfig->decode((string)$this->configLoader->get(ConfigMapper::NAME_AI_DIALOGAGENT));
        $dialogAgentEnabled = $aiConfig->isEnabled($featureConfig);

        return AssessmentType::getOptions(
            $ltiEnabled,
            $dialogAgentEnabled,
        );
    }
}
