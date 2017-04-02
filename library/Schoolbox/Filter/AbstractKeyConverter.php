<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 4:30 PM
 */

namespace Schoolbox\Filter;


abstract class AbstractKeyConverter implements IKeyConverter
{
    /**
     * Sanitises a key before insertion in filter_selection table
     * @param string $key
     * @return string
     */
    public function sanitiseKey($key)
    {
        return (string)(int)$key;
    }
}
