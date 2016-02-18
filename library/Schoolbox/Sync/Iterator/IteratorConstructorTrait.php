<?php
/**
 * Constructor trait for IteratorIterator extending classes.
 *
 * Ensures the inner iterator is an Iterator and not just a Traversable.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Iterator;

use Iterator;
use IteratorIterator;
use Schoolbox\External\Normalise\User;
use Traversable;

trait IteratorConstructorTrait
{
    /**
     * Constructor - updated to ensure $iterator instanceof Iterator.
     *
     * @param Traversable $iterator
     */
    public function __construct(Traversable $iterator)
    {
        if ($iterator instanceof Iterator) {
            parent::__construct($iterator);
        } else {
            parent::__construct(new IteratorIterator($iterator));
        }
    }
}
