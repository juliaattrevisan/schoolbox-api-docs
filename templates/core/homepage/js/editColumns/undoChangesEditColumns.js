/** 
 * Reset the latest changes back to the original state
 *
 */

import { UPDATE_URL } from '../updateHomepage';
import UpdateHomepageEvents from '../updateHomepageEvents';
import { getFormData } from './editColumns';
import { undoColourChanges } from './editColumnsColours';
 
 
/**
 * Start listening for the reset event
 */
export function initUndoChangesEditColumns () {
    document.addEventListener(UpdateHomepageEvents.EDIT_COLUMNS_UNDO_CHANGES, onEditColumnsUndoChanges);
}
 
 /**
  * Save all the column settings, notifying of success / failure
  * 
  * @param { Element } 
  */
async function onEditColumnsUndoChanges () {
    const $toast = $('[data-relation="saveLayout"]');
    try {
        $toast.toastActivate( { text: 'Saving...' } );
        undoColourChanges();
        await fetch(UPDATE_URL, {
            method: 'POST',
            body: getFormData(),
        });
    } catch (error) {
        console.warn(error);
        $toast.toastTimeout({
            text: 'Unable to save column settings.',
            css: 'alert'
        });
    } finally {
        $toast.toastTimeout({
            text: 'Colour changes successfully undone.',
            css: 'success'
        });
    }
}