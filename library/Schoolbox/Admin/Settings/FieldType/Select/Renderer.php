<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;

class Select extends Base
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
        return \array_merge(parent::getData($config), [
            'options' => \explode(':', $config->getOptions()),
        ]);
    }


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_SELECT;
    }
}
