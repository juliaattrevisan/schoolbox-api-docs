<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Admin\API;

use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;

class Module extends BaseModule {

    protected $basename = 'adminApi';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        // no explicit auth; all done via tokens

        $this->register('POST', '/csv',     'postUploadCsv');
        $this->register('POST', '/process', 'postProcess');
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class));
    }

}