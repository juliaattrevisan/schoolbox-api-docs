<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Blog\Homepage\Image;

use Schoolbox\Resource\Storage\RelationalDelete\IRelationalDelete;
use Schoolbox\Resource\Storage\RelationalDelete\Deletable;

class RelationalDelete extends Deletable implements IRelationalDelete
{

}
