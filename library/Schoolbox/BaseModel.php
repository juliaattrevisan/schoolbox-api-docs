<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox;

use Schoolbox\Mapper\Traits\Model\CreateCollection;

/**
 * Common implementation of all models
 *
 * This should only contain implementation that we are sure that ALL models will implement.
 */
class BaseModel implements IModel
{
    use CreateCollection;
}
