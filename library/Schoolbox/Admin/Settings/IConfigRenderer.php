<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

/**
 * Instances of this class are able to build a matching template and data pair
 * that can be rendered in the view layer.
 */
interface IConfigRenderer
{

    /**
     * Returns the template fragment to render
     *
     * @return string
     */
    public function getTemplate(): string;


    /**
     * Returns data suitable for the template fragment
     *
     * @return mixed[]
     */
    public function getData(): array;
}
