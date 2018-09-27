<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

class SelectKey extends Select
{

    public function getData(): array
    {
        $options = [];
        foreach (\explode(':', $this->getConfig()->getOptions()) as $keyAndValue) {
            [$key, $val] = \explode(';', $keyAndValue);
            $options[$key] = $val;
        }
        return \array_merge(parent::getData(), [
            'options' => $options,
        ]);
    }
}
