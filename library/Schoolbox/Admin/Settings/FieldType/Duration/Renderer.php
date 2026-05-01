<?php

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Duration;

use Schoolbox\Admin\Settings\FieldType\Number\Renderer as NumberRenderer;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;

class Renderer extends NumberRenderer
{
    /**
     * @inheritdoc
     */
    protected function getTemplate(): string
    {
        return 'duration.twig';
    }


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_DURATION;
    }


    /**
     * @inheritdoc
     */
    protected function getData(Config $config): array
    {
        $data = parent::getData($config);

        if ($config->getOptions()) {
            $options = json_decode($config->getOptions(), true);

            if (isset($options["timeUnit"])) {
                $data["timeUnit"] = $options["timeUnit"];
            }
        }

        return $data;
    }
}
