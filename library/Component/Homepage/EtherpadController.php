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

	   $this->setVar(array(
		   'etherpad_pad_block' => ''
	   ));

	   /* Find all the pads associated with this list of groups
	    */
	   $padMapper = new \Etherpad\PadMapper();

	   $allPads = $padMapper->findByColumn('homepage_id', $this->instance->getIdentifier());

	   foreach($allPads as $currentPad) {
		   $this->setVar(array(
			   'ETHERPAD_PAD_ID' => $currentPad->getId(),
			   'ETHERPAD_NAME' => $currentPad->getDisplayName(),
			   'ETHERPAD_CREATED_AT' => $currentPad->getCreatedAt()->getDateOnly(),
		   ));

		   $this->parse('etherpad_pad_block', 'etherpad_pad', true);
	   }

       return true;
   }
}
