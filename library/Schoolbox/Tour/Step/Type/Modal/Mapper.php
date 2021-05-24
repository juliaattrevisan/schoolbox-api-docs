<?php

/**
 * @author      Matthew Schembri <matt.schembri@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Tour\Step\Type\Modal;

use Schoolbox\Tour\Step\Type\AbstractMapper;

/**
 * Class Mapper
 *
 * @method Model createModel($id = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @package Schoolbox\Tour\Step\Modal
 */
class Mapper extends AbstractMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        parent::mapping();
        $this->hasTable('tour_step_modal');
        $this->hasModel(Model::class);
        $this->uses('title', self::TYPE_STRING);
    }
}
