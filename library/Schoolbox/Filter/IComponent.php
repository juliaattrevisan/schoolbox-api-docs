<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */


namespace Schoolbox\Filter;


/**
 * A filter panel component knows how to render itself in a filter panel.
 *
 * A filter factory is responsible for building it.
 *
 * By its nature, a Controller knows which extra args are required for specific components and
 * can pass runtime parameters into the factory build method.
 */
interface IComponent
{

    /**
     * @return IDefinition
     */
    public function getDefinition();


    /**
     * Return the sub-template to include in the filter panel
     *
     * @return string
     */
    public function getTemplate();


    /**
     * Return the data to render in the the sub-template in the filter panel
     *
     * Intended to be called in twig includes
     *
     * @return \Schoolbox\Filter\Selection\Model[]
     */
    public function toContext();
}
