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
     * @return array|string[]
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
        $data['BASE_HREF'] = (new \Schoolbox\Url\Factory(ConfigMapper::getInstance()->getConfigLoader()))->buildFullyQualified()->setScheme("https");
        return $data;
    }
}
