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
use Schoolbox\Learning\DialogAgent\ValidDialogAgentEnabled;

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
        return AssessmentType::getOptions(
            $this->configLoader->get(ConfigMapper::NAME_LTI_ASSESSMENTS_ENABLE),
            new ValidDialogAgentEnabled($this->configLoader)->isSatisfied(),
        );
    }
}
