<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Homepage_DataMapper extends Generic_DataMapper {

    const TABLE = 'homepage_config';
    const INDEX = 'cid';

    public function __construct() {
        // initialise query object
        parent::__construct();

        $this->select('*');

        $this->setHydrateField('components');
    }

    public function hydrateComponents(Facade_Homepage $homepage, $args = '') {
        $cMap = new Facade_Component_DataMapper();

        $components = $cMap->read(array('cid' => $homepage->get('cid')));

        foreach($components as $c) {
            $c->unserializeConfig();
        }

        $homepage->set('components', $components);

    }
}
