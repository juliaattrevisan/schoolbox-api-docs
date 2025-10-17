<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Admin\OpenAI;

use Alaress\Web\Output\DataGenerator\IDataGenerator;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

class DataGenerator implements IDataGenerator
{
    public function __construct(
        private IConfigLoader $config,
        private ConfigMapper $configMapper
    ) {
    }

    /**
     * Generating an array of AI text Feature configurations
     *
     * @param array|null $chromeData
     * @return array
     * @throws \JsonException
     */
    public function generate(?array $chromeData = []): array
    {
        // returns each feature / module for ai, its creds, and if its enabled
        $textProvider = $this->config->get(ConfigMapper::NAME_OPENAI_TEXT_ENDPOINT);
        $aiConfig = new Config();
        return $aiConfig->fetchFeatures($this->configMapper);
    }
}
