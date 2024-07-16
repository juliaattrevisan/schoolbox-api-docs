<?php

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Range;

use Schoolbox\Admin\Settings\FieldType\Range\ValueSerializer;
use Schoolbox\Admin\Settings\FieldType\AbstractRenderer;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;

class Renderer extends AbstractRenderer
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
        return 'range.twig';
    }

    /**
     * @inheritdoc
     */
    protected function getData(Config $config): array
    {
        $data = parent::getData($config);
        $options = $this->valueSerializer->buildOptions($config->getOptions());

        $data["min"] = $options["min"];
        $data["max"] = $options["max"];
        $data["step"] = $options["step"];

        // Ensure the data is valid for the range input
        $data['value'] = $this->valueSerializer->serializeValue($data['value'], $config);

        return $data;
    }

    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_RANGE;
    }
}
