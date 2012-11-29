<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Stpatricks_Strategy_ParentRelation extends Synchronise_External_Stpatricks_Strategy_Abstract {

    /**
     * Process user
     */
    public function createIfExists() {

        $this->getVerboseFilter()->intro();

        if(is_array($this->entityData)) {

            // clear existing relations registered
            $this->clearRelations();

            $synStmt = Database::getInstance()->prepare("
                SELECT `id` FROM `user` WHERE synergy_id = :sid
            ");
            $relationStmt = Database::getInstance()->prepare("
                INSERT INTO `user_relation` VALUES(:student, :parent, 1)
            ");

            foreach($this->entityData as $relation) {
                // Check that the relation is valid
                $synStmt->execute(array('sid' => $relation['studentID']));
                $child = $synStmt->fetchColumn();
                $synStmt->execute(array('sid' => $relation['parentID']));
                $parent = $synStmt->fetchColumn();

                // if both were succesfully located in the DB, insert the relation
                if($child && $parent) {
                    $relationStmt->execute(array(
                        'student' => $child,
                        'parent' => $parent
                    ));
                    if($this->getVerboseFilter()->getVerbose()) {
                        echo " Relation between $child ({$relation['studentID']}) and $parent ({$relation['parentID']}) created/planned\n";
                    }
                }
                elseif($this->getVerboseFilter()->getVerbose()) {
                    echo " Relation between synergyIDs `{$relation['studentID']}` and `{$relation['parentID']}` failed\n";
                }
            }

        }
        else {
            throw new UnderflowException("No data parsed from the csv file.");
        }
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
                DELETE FROM user_relation
                WHERE `relation_type` = :relationType
            ")->execute( array( 'relationType' => $relationType ) );
        }
    }
}
