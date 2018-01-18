<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Step\Type\Tooltip;

use Schoolbox\Tour\Step\Type\AbstractMapper;

/**
 * Class Mapper
 *
 * @method Model createModel($id = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @package Schoolbox\Tour\Step\Tooltip
 */
class Mapper extends AbstractMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        parent::mapping();
        $this->hasTable('tour_step_tooltip');
        $this->hasModel(Model::class);
        $this->uses('selector', self::TYPE_STRING, null, 'javascriptSelector');
    }
}
