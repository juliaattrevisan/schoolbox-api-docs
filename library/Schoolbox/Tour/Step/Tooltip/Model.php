<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Step\Tooltip;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Author;
use Schoolbox\Mapper\Traits\Model\Body;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\CssClass;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\JavascriptSelector;
use Schoolbox\Mapper\Traits\Model\Title;
use Schoolbox\Mapper\Traits\Model\Tourstep;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\Uuid;

class Model implements ISoftDeletable
{
    use Uuid;
    use Tourstep;
    use Author;
    use Title;
    use Body;
    use JavascriptSelector;
    use CssClass;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
}