<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

class Select extends Base
{

    /**
     * @inheritdoc
     */
    public function getTemplate(): string
    {
        return 'select.twig';
    }


    /**
     * @inheritdoc
     */
    public function getData(): array
    {
        return \array_merge(parent::getData(), [
            'options' => \explode(':', $this->getConfig()->getOptions()),
        ]);
    }
}
