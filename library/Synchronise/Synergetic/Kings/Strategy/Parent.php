<?php
/**
 * Synchronise_Synergetic_Kings_Strategy_Parent
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Kings_Strategy_Parent extends Synchronise_Base_Strategy_Parent {
    /**
     * Setup verbose output
     */
   protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Kings_Verbose();
        $this
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Parent())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
         	->addHydrator(new Synchronise_Synergetic_Kings_Hydrator_ParentUserName())
            ->addHydrator(new Synchronise_Synergetic_Kings_Hydrator_Campus())
            ->addHydrator(new Synchronise_Synergetic_Kings_Hydrator_Webmail())
            ->addHydrator(new Synchronise_Synergetic_Kings_Hydrator_DisableEportfolio())
            //->addSpouseHydrator(new Synchronise_Base_Hydrator_Default())
            //->addSpouseHydrator(new Synchronise_Base_Hydrator_Spouse())
            //->addSpouseHydrator(new Synchronise_Synergetic_Kings_Hydrator_Campus())
            //->addSpouseHydrator(new Synchronise_Synergetic_Kings_Hydrator_ParentUsername())
            //->addSpouseHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
    
    public function generateList() {
    	$this->tks_user_handler = new Synchronise_Synergetic_Kings_KingsUser();
		$this->list = $this->tks_user_handler->getParentUserAccountInfo();
	}
	
	public function removeIfNotExists() {
	    $parentList = array();
        foreach($this->list as $currentParent) {
            $parentList[$currentParent['StudentContactID']] = true;
        }

        $i=0;
        foreach ($this->user_handler->getByGroup(4) as $existingParent) {
            if (empty($parentList[$existingParent['synergy_id']])) {
            
            	if ($this->perform_update) {
            		$this->verbose_filter->outputMessage('- Deleting '.$existingParent['synergy_id']."|".$existingParent['lastname'].", ".$existingParent['firstname']);
					$this->user_handler->delete($existingParent['id']);
	            } else {
	            	$this->verbose_filter->outputMessage('- Planning delete of '.$existingParent['synergy_id']."|".$existingParent['lastname'].", ".$existingParent['firstname']);
	            }
	            
                $i++;
            }
        }

        $this->verbose_filter->outputMessage("Deleted: $i parent accounts.");

	
	}
 
 /**
 * Blank out the setupSpouseDetails method.
 * (TKS Parent Views from Synergetic already contain spouses)
 */   
 public function setupSpouseDetails($external_user) {
 
 }
    
    	
}
