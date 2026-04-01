<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\User;

/**
 * Generate data for rendering a list of enabled assessment types.
 */
class AssessmentTypes implements IDataGenerator
{
    /**
     * @param IConfigLoader $configLoader
     * @param User $user
     */
    public function __construct(private IConfigLoader $configLoader, private \Schoolbox\User $user)
    {
    }


    /**
     * @inheritdoc
     */
    public function generate(?array $chromeData = []): array
    {
        $options = [];

        foreach (AssessmentType::getEnabledOptions($this->configLoader, $this->user) as $key => $name) {
            $options[$key] = [
                'name' => $name,
                'icon' => AssessmentType::getIconClassSuffixForType($key),
            ];
        }

        return $options;
    }
}
