<?php
/**
 * Defines an interface for image strategy factories.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Filetype;

use Schoolbox\Application\Exception\Exception as SchoolboxException;

interface FactoryInterface
{
    /**
     * Creates a filetype strategy.
     *
     * @param  array $imageArray
     * @return FiletypeInterface
     * @throws SchoolboxException if image isn't recognised
     */
    public function build($imageArray);
}
