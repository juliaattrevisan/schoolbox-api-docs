<?php
/**
 * Synchronise_Base_Strategy_Class
 *
 * Syncs data from the external database or CSV file into the class tables.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;

abstract class Synchronise_Base_Strategy_Class extends Synchronise_Base_Strategy {
    protected $strategyTitle = "Update Schoolbox Class Details Synchronisation";

    abstract public function setupSyncedItemDetails($list_item);

    /**
     * If the users exist in Synergy but not in Schoolbox, create an account for
     * them
     *
     * @return void
     */
    public function createIfExists() {
        // This should replace the existing createIfExists() method
        $this->verbose_filter->intro();
        foreach ($this->list as $list_item) {
            $new_item = $this->setupSyncedItemDetails($list_item);
            $item_id  = $this->executeCreateUpdate($new_item);
            if ($item_id > 0) {
                $new_item['id'] = $item_id;
                $this->executePostCreateUpdate($new_item, $list_item);
            }
        }
        $this->verbose_filter->summary();
    }
}