<?php

/**
 * Yep, it's a factory that builds factories.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
class Component_FactoryFactory
{
    /**
     * Builds a component factory.
     *
     * @param string         $context  The type of factory to build (dashboard, homepage, eportfolio)
     * @param Schoolbox\User $user     A user to feed into the factory
     * @param SitePage       $sitepage A sitepage to feed into the factory
     * @param bool           $template A sitepage to feed into the factory
     *
     * @return Component_FactoryAbstract
     * @throws InvalidArgumentException if $context wasn't recognized
     */
    public function build($context, Schoolbox\User $user, SitePage $sitepage, $template = false)
    {
        switch ($context) {
            case 'dashboard':
                return new Component_Dashboard_Factory($user, $sitepage);
            case 'homepage':
                return new Component_Homepage_Factory($user, $sitepage, $template);
            case 'homepage_template':
                return new Component_Homepage_Factory($user, $sitepage, true);
            case 'eportfolio':
                return new Component_Eportfolio_Factory($user, $sitepage, $template);
            case 'eportfolio_template':
                return new Component_Eportfolio_Factory($user, $sitepage, true);
        }
        throw new InvalidArgumentException("Context \"$context\" not a valid component context");
    }
}
