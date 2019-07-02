<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\File;

use Schoolbox\Resource\Storage\RelationalDelete\Deletable;
use Schoolbox\Resource\Storage\RelationalDelete\IRelationalDelete;

class RelationalDelete extends Deletable implements IRelationalDelete
{

}
