<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Trigger;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Author;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\JavascriptEvent;
use Schoolbox\Mapper\Traits\Model\JavascriptSelector;
use Schoolbox\Mapper\Traits\Model\Tour;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\UrlRegex;
use Schoolbox\Mapper\Traits\Model\Uuid;

class Model implements ISoftDeletable
{
    use Uuid;
    use Author;
    use Tour;
    use JavascriptSelector;
    use JavascriptEvent;
    use UrlRegex;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
}