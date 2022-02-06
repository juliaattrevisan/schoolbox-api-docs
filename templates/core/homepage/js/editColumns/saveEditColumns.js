/** 
 * Save the latest column width/colour changes
 *
 */

import { UPDATE_URL } from '../updateHomepage';
import UpdateHomepageEvents from '../updateHomepageEvents';
import { appendWidth } from './editColumnsWidths';


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
            text: 'Unable to save Column settings.',
            css: 'alert'
        });
    } finally {
        $toast.toastTimeout({
            text: 'Columns Saved',
            css: 'success'
        });
    }
}

/**
 * To pass through the required FormData to delete the component in the DB
 * 
 * @param { Element } 
 * 
 * @returns { FormData }  
 */
function getFormData () {
    const formData = new FormData();
    formData.append('cid', document.getElementById('categoryID').value);
    appendWidth(formData);
    formData.append('colors[top_head_font]', 'rgba(102, 163, 208, 1.000)');
    formData.append('colors[top_head_font]', 'rgba(102, 163, 208, 1.000)');
    formData.append('colors[top_head_bg]', 'rgba(229, 239, 247, 1.000)');
    formData.append('colors[top_font_primary]', 'rgba(0, 0, 22, 1.000)');
    formData.append('colors[top_bg_primary]', 'rgba(255, 255, 255, 1.000)');
    formData.append('colors[left_head_font]', 'rgba(102, 163, 208, 1.000)');
    formData.append('colors[left_head_bg]', 'rgba(229, 239, 247, 1.000)');
    formData.append('colors[left_font_primary]', 'rgba(0, 0, 22, 1.000)');
    formData.append('colors[left_bg_primary]', 'rgba(255, 255, 255, 1.000)');
    formData.append('colors[right_head_font]', 'rgba(102, 163, 208, 1.000)');
    formData.append('colors[right_head_bg]', 'rgba(229, 239, 247, 1.000)');
    formData.append('colors[right_font_primary]', 'rgba(0, 0, 22, 1.000)');
    formData.append('colors[right_bg_primary]', 'rgba(255, 255, 255, 1.000)')
    return formData;
}