<?php

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Duration;

use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Admin\Settings\FieldType\Input;

class Renderer extends Input\Renderer
{
    /**
     * @var ValueSerializer
     */
    private ValueSerializer $valueSerializer;

    /**
     * Create a range value serializer on construct so we can use it in the renderer
     */
    public function __construct()
    {
        $this->valueSerializer = new ValueSerializer();
    }

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
            $options = $this->valueSerializer->buildOptions($config->getOptions());

            if (isset($options["min"])) {
                $data["min"] = $options["min"];
            }
            if (isset($options["max"])) {
                $data["max"] = $options["max"];
            }
            if (isset($options["timeUnit"])) {
                $data["timeUnit"] = $options["timeUnit"];
            }

            // ensure a valid value is passed in to the template
            $data['value'] = $this->valueSerializer->serializeValue($data['value'], $config);
        }
        return $data;
    }
}
