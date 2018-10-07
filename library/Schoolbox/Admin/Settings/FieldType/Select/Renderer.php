<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Select;

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
        return 'select.twig';
    }


    /**
     * @inheritdoc
     */
    protected function getData(Config $config): array
    {
        $select = [];
        $options = \explode(':', $config->getOptions());

        foreach($options as $value) {
            $select[$value] = $value;
        }
        return \array_merge(parent::getData($config), ['options' => $select]);
    }


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_SELECT;
    }
}
