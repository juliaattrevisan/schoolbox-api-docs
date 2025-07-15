<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Number;

use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Admin\Settings\FieldType\Input;

class Renderer extends Input\Renderer
{
    /**
     * @var IntValueSerializer
     */
    private IntValueSerializer $valueSerializer;

    /**
     * Create a range value serializer on construct so we can use it in the renderer
     */
    public function __construct()
    {
        $this->valueSerializer = new IntValueSerializer();
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

            // if the value is not set or outside the range, set it to the minimum
            $data['value'] = $this->valueSerializer->serializeValue($data['value'], $config);
        }
        return $data;
    }

    protected function getTemplate(): string
    {
        return 'number.twig';
    }

    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_NUMBER;
    }
}
