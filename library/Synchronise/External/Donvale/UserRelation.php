<?php
/**
 * Synchronise_External_Donvale_UserRelation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Donvale_UserRelation extends Synchronise_External_Donvale_Abstract {
	private $parentRelationColumnList = array('ID', 'StudentID', 'ParentSBAcc');

	/**
	 * Process input
	 *
	 * @return void
	 */
	public function process() {

		/* Parent child relation format
		 */
		if ($this->isMatchingColumns($this->getParentRelationColumnList())) {
			$this->processParentRelations();
		}
		/* Unrecognized format
		 */
		else {
			die("Relation file is not in a recognised format.{$this->getLineEnding()}");
		}

		echo "{$this->getLineEnding()}{$this->getLineEnding()}";
	}

	/**
	 * Process parent child relations
	 *
	 * @return void
	 */
	private function processParentRelations() {
        $this->clearRelations(External_Schoolbox_User::RELATION_TYPE_PARENT);

		while ($propertyList = $this->getRow()) {
			$this->incrementLineCount();

			/* Check number of columns
			 */
			if ($this->isInvalidPropertyList($propertyList)) {
				continue;
			}

			/* Overwrite original var -_-
			 */
			$propertyList = $this->generatePropertyList($propertyList);

            if ( !$parent = $this->getUserHandler()->getByUsername($propertyList['ParentSBAcc']) ) {
                print("Parent not found ({$propertyList['ID']}) at line #{$this->getLineCount()} in {$this->getFileName()}{$this->getLineEnding()}");

                $this->incrementErrorCount();

                continue;
            }

            if ( !$child = $this->getUserHandler()->getBySynergy($propertyList['StudentID']) ) {
                print("Child not found ({$propertyList['StudentID']}) at line #{$this->getLineCount()} in {$this->getFileName()}{$this->getLineEnding()}");

                $this->incrementErrorCount();

                continue;
            }

            $this->createRelation( array(
                'userId' => $parent['uid'],
                'relationId' => $child['uid'],
                'relationType' => External_Schoolbox_User::RELATION_TYPE_PARENT
            ) );
		}

		print("{$this->getLineEnding()}{$this->getErrorCount()} of {$this->getLineCount()} parent relations failed to load.{$this->getLineEnding()}");

		echo "{$this->getLineEnding()}{$this->getLineEnding()}";
	}

    /**
     * Removes all user relations
     *
     * @param Integer $relationType
     */
    private function clearRelations ($relationType = null) {
        if ( empty($relationType) ) {
            Database::getInstance()->query("TRUNCATE user_relation");
        }
        else {
            Database::getInstance()-> prepare("
                TRUNCATE user_relation
                WHERE `relation_type` = :relationType
            ")->execute( array( 'relationType' => $relationType ) );
        }
    }

    /**
     * Creates a user relationship
     *
     * @param Array $relation
     */
    private function createRelation ($relation) {
        Database::getInstance()->prepare("
            INSERT INTO user_relation (user_id, relation_id, relation_type)
            VALUES (:userId, :relationId, :relationType)
        ")->execute($relation);
    }

	/* Setters/Getters
	 */

    /**
   	 * Get parent relation column list
   	 *
   	 * @return Array
   	 */
   	private function getParentRelationColumnList () {
   		return $this->parentRelationColumnList;
   	}
}
