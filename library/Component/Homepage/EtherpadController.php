<?php

/**
 * Component_Homepage_EtherpadController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_EtherpadController extends Component_Homepage_ControllerAbstract {

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        return true;
    }

	/**
	 * Outputs HTML template defined by config variable 'template'
	 *
	 * @param String $templateHandle
	 * @return bool
	 */
   public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'etherpad')) {
            return false;
        }

	   $this->parseConfig();

	   $this->setBlock($templateHandle, 'etherpad_pad', 'etherpad_pad_block');

	   /* Find all the groups associated with this homepage
	    */
	   $groupMapper = new \Etherpad\GroupMapper();

	   $allGroups = $groupMapper->findByColumn('homepage_id', 52);

	   $ids = array();

	   /* Cheaply uniquify
	    */
	   foreach($allGroups as $currentGroup) {
		   $ids[$currentGroup->getGroupId()] = '';
	   }

	   /* Find all the pads associated with this list of groups
	    */
	   $padMapper = new \Etherpad\PadMapper();

	   $allPads = $padMapper->findByColumn('group_id', array_keys($ids));

	   foreach($allPads as $currentPad) {
		   $this->setVar(array(
			   'ETHERPAD_PAD_ID' => $currentPad->getPadId(),
			   'ETHERPAD_NAME' => $currentPad->getName()
		   ));

		   $this->parse('etherpad_pad_block', 'etherpad_pad', true);
	   }

       return true;
   }
}
