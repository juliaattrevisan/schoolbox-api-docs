<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Step;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Author;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Tour;
use Schoolbox\Mapper\Traits\Model\Type;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\Uuid;

class Model implements ISoftDeletable
{
    use Uuid;
    use Tour;
    use Type;
    use Author;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    // Step type constants

    const TYPE_TOOLTIP = 'tooltip';
}