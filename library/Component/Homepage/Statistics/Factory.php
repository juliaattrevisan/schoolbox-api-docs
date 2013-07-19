<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Component_Homepage_Statistics_Factory extends Component_FactoryAbstract {

    /**
     * @var String The module for which this factory produces components.
     */
    protected $module;

    public function __construct(User $user, SitePage $sitepage) {
        $this->module = 'homepage_statistics';
        parent::__construct($user, $sitepage);
    }

    /**
     * Overrides parent method, since the component inclusion is not defined by the DB
     * @param $identifier
     * @return \Schoolbox\Collection
     */
    public function buildComponentList ($identifier) {

        // emulate the datastructure that would be otherwise retrieved from the DB
        // much as I *didn't* want to statically register the classes to be used... .__________.
        $include = array(
            'top'   => array('Navigator','PageVisits'),
            'left'  => array('TopVisitors', 'TopFiles'),
            'right' => array('RecentVisitors','Unvisited','TopLinks')
        );

        $instanceCollection = new Schoolbox\Collection();

        foreach($include as $col => $comps) {
            foreach($comps as $loc => $comp) {
                $inst = new Component_Instance();
                $inst
                    ->setModule('homepage_statistics')
                    ->setPosition($col)
                    ->setSequence($loc + 1)
                    ->setDefinition(new Component_Definition());
                $inst->getDefinition()
                    ->setClass(sprintf('Component_Homepage_Statistics_%sController',$comp))
                    ->setModule('homepage_statistics')
                    ->setVisible('')
                    ->setMultiple('')
                    ->setName('');

                $instanceCollection->add($inst);
            }
        }

        $componentCollection = new Schoolbox\Collection();

        if ( !empty($instanceCollection) ) {
            foreach ($instanceCollection as $instance) {
                $component = $this->buildComponent($instance);

                if ( !empty($component) ) {
                    $componentCollection->add($component);
                }
            }
        }

        return $componentCollection;
    }

}
