/** 
 * Save the latest column width/colour changes
 *
 */

import { UPDATE_URL } from '../updateHomepage';
import UpdateHomepageEvents from '../updateHomepageEvents';
import { getFormData } from './editColumns';


/**
 * Start listening for the save event
 */
export function initSaveEditColumns () {
    document.addEventListener(UpdateHomepageEvents.EDIT_COLUMNS_SAVE, onEditColumnsSave);
}

/**
 * Save all the column settings, notifying of success / failure
 * 
 * @param { Element } 
 */
 async function onEditColumnsSave () {
    const $toast = $('[data-relation="saveLayout"]');
    try {
        $toast.toastActivate( { text: 'Saving...' } );
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
            text: 'Columns successfully saved.',
            css: 'success'
        });
    }
}