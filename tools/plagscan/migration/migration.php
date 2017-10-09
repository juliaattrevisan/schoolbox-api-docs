<?php

/* Migrate the data from the migration results back to the plagscan_document table
 *
 * We couldn't modify this table during the initial migration so I need to move all
 * storage hashes back onto the plagscan_document table separately. Schoolbox assumes
 * that this storage hash exists on plagscan_document as a result of this branch.
 */

/*

for (every migrated plagscan document as migratedDocument) {
    // find the corresponding plagscan_document record by plagscan_id

    // update the plagscan_document model with the storage hash contained in migratedDocument

    // save the plagscan_document record again ensuring that html is empty
}

 */