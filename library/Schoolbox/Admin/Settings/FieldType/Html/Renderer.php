<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Html;

use Schoolbox\Admin\Settings\FieldType\AbstractRenderer;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;

class Renderer extends AbstractRenderer
{
    // To enable tokens in the CKEditor
    private const OPTION_HAS_TOKENS = 'has-tokens';
    private const EDITOR_OPTIONS    = [
        self::OPTION_HAS_TOKENS
    ];

    /**
     * @inheritdoc
     */
    protected function getTemplate(): string
    {
        return 'html.twig';
    }


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_HTML;
    }

    /**
     * @param Config $config
     * @return array<string, mixed>
     */
    protected function getData(Config $config): array
    {
        /**
         * grab the standard twig data, and append to the array the base_href for ckeditor to build absolute URLs
         * this was originally created for the email signature, but will also work with the rest of the config html fields
         *
         * This will not update existing entries, only when adding a new link with the ckeditor link dialogue
         */
        $data =  parent::getData($config);
        $options = $this->buildOptions($config->getOptions() ?? '');
        $data['BASE_HREF'] = (new \Schoolbox\Url\Factory(ConfigMapper::getInstance()->getConfigLoader()))->buildFullyQualified()->withScheme("https");
        $data['hasTokens'] = in_array(self::OPTION_HAS_TOKENS, $options);
        return $data;
    }

    /**
     * Converts a string representation of options into an array form
     *
     * @param string $optionsString
     * @return array
     */
    private function buildOptions(string $optionsString): array
    {
        $options = [];
        foreach (\explode(':', $optionsString) as $value) {
            if (!in_array($value, self::EDITOR_OPTIONS)) {
                continue;
            }
            $options[$value] = $value;
        }
        return $options;
    }
}
