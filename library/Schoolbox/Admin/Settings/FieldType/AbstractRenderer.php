<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

use Schoolbox\Config\Config;
use Schoolbox\Admin\Settings\IConfigRenderer;
use InvalidArgumentException;

abstract class Base implements IConfigRenderer
{

    /**
     * @inheritdoc
     */
    public function getTemplateAndData(Config $config): array
    {
        // Ensure we received a config of the correct field type
        if ($config->getFieldId() !== $this->getFieldId()) {
            throw new InvalidArgumentException("Invalid config field. Renderer: '{$this->getFieldId()}', Config: '{$config->getFieldId()}'");
        }

        return [
            'template' => $this->getTemplate(),
            'data'     => $this->getData($config),
        ];
    }


    /**
     * Returns the template fragment to render
     *
     * @return string
     */
    abstract protected function getTemplate(): string;


    /**
     * Returns the id of the field type this renderer relates to. Used to ensure we don't
     * attempt to render a config of an incompatible type
     *
     * @return int
     */
    abstract protected function getFieldId(): int;


    /**
     * @param Config $config
     * @return string[]
     */
    protected function getData(Config $config): array
    {
        return [
            'name'        => $config->getName(),
            'value'       => $config->getValue(),
            'title'       => $config->getTitle(),
            'description' => $config->getDescription(),
            'required'    => $config->getRequired(),
            'editable'    => $config->getEditable(),
        ];
    }
}
