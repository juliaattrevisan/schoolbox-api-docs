<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

abstract class AbstractFactoryFactory implements IFactoryFactory
{

    /**
     * What entity type does is the factoryFactory responsible for?
     *
     * @const int ENTITY
     */
    const ENTITY = 0;


    /**
     * @return string
     * @throws \DomainException If called on abstract, or concrete class that does not override
     */
    public static function getEntity()
    {
        if(0 === static::ENTITY){
            throw new \DomainException('Concrete classes must define this constant');
        }
        return static::ENTITY;
    }
}
