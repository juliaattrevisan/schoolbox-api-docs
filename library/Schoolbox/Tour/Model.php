<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Active;
use Schoolbox\Mapper\Traits\Model\Author;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Description;
use Schoolbox\Mapper\Traits\Model\Title;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\Uuid;

class Model implements ISoftDeletable
{
    use Uuid;
    use Author;
    use Title;
    use Description;
    use Active;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
}