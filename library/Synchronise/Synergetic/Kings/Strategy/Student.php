<?php
/**
 * Synchronise_Synergetic_Kings_Strategy_Student
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Kings_Strategy_Student extends Synchronise_Base_Strategy_Student {
    /**
     * Setup verbose output and hydrator classes.
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Synergetic_Kings_Verbose();
        $this
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Student())
            ->addHydrator(new Synchronise_Synergetic_Kings_Hydrator_Campus())
            ->addHydrator(new Synchronise_Synergetic_Kings_Hydrator_House())
            ->addHydrator(new Synchronise_Synergetic_Kings_Hydrator_EnableEportfolio())
			->addHydrator(new Synchronise_Synergetic_Kings_Hydrator_Group_Student())
			->addHydrator(new Synchronise_Synergetic_Kings_Hydrator_StudentStatus())
			->addHydrator(new Synchronise_Synergetic_Kings_Hydrator_Webmail())
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
    
	public function generateList() {
    	$this->tks_user_handler = new Synchronise_Synergetic_Kings_KingsUser();
		$this->list = $this->tks_user_handler->getStudentUserAccountInfo();
	}
	
	public function removeIfNotExists() {
	    $userList = array();
        foreach($this->list as $currentUser) {
            $userList[$currentUser['StudentID']] = true;
        }

		$existingList = array_merge($this->user_handler->getByGroup(3), $this->user_handler->getByGroup(5));
	

        $i=0;
		foreach ($existingList as $existingStudent) {
			if ($existingStudent['synergy_id'] != '' && empty($userList[$existingStudent['synergy_id']])) {
            	if ($this->perform_update) {
            		/* JT: Actually exceutes an update on the record.
            			Disables account and clears House attributes.
            			(We don't want to accidentally delete a student's work)
            		*/
#            		$existingStudent['house'] = null;
#	  		$existingStudent['residential_house_id'] = null;
	  		$existingStudent['enabled'] = 0;
            		$this->verbose_filter->outputMessage('- Disabling '.$existingStudent['synergy_id']."|".$existingStudent['lastname'].", ".$existingStudent['firstname']);
	                #$this->executeCreateUpdate($existingStudent);
	            } else {
	            	$this->verbose_filter->outputMessage('- Planning disable of '.$existingStudent['synergy_id']."|".$existingStudent['lastname'].", ".$existingStudent['firstname']);
	            }
	            
                $i++;
            }
        }

        $this->verbose_filter->outputMessage("Disable: $i student accounts.");

	
	}
}
