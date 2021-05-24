<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */


/**
 * Renders a 'size' dropdown without the 'All' option
 */
class Component_Config_SizeNoAll extends \Component_Config_Size
{

    /**
     * @inheritdoc
     */
    protected function getShowAllOption()
    {
        // Don't show the 'All' option
        return false;
    }
}
