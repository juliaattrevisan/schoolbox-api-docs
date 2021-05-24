<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType;

use Schoolbox\Admin\Settings\Exceptions\FieldTypeMismatchException;
use Schoolbox\Config\Config;
use Schoolbox\Admin\Settings\IConfigRenderer;

/**
 * Provides common functionality for config field-type rendering classes
 */
abstract class AbstractRenderer implements IConfigRenderer
{

    /**
     * @inheritdoc
     * @throws FieldTypeMismatchException
     */
    public function getTemplateAndData(Config $config): array
    {
        // Ensure we received a config of the correct field type
        if ($config->getFieldId() !== $this->getFieldId()) {
            throw new FieldTypeMismatchException("Invalid config field. Renderer: '{$this->getFieldId()}', Config: '{$config->getFieldId()}'");
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
     * Returns the config's value, applying any relevant transformations
     *
     * @param Config $config
     * @return null|string
     */
    protected function getTransformedValue(Config $config): ?string
    {
        return $config->getValue();
    }


    /**
     * @param Config $config
     * @return string[]
     */
    protected function getData(Config $config): array
    {
        return [
            'name'        => $config->getName(),
            'value'       => $this->getTransformedValue($config),
            'title'       => $config->getTitle(),
            'description' => $config->getDescription(),
            'required'    => $config->getRequired(),
            'editable'    => $config->getEditable(),
            'hidden'      => $config->getHidden(),
        ];
    }
}
