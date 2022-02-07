/** 
 * Edit  the colours/width of the component columns
 *
 */

import { initEditColumnsWidths, appendWidth } from './editColumnsWidths';
import { initEditColumnsColours, appendColours } from './editColumnsColours';
import { initEditColumnsButtons } from './editColumnsButtons';
import { initResetEditColumns } from './resetEditColumns';
import { initUndoChangesEditColumns } from './undoChangesEditColumns';
import { initSaveEditColumns } from './saveEditColumns';

/**
 * Fire everything up 
 */
export function initEditColumns () {
    initEditColumnsWidths();
    initEditColumnsColours();
    initEditColumnsButtons();
    initResetEditColumns();
    initUndoChangesEditColumns();
    initSaveEditColumns();
}

/**
 * To pass through the required FormData to update the column settings
 * 
 * @returns { FormData }  
 */
export function getFormData () {
    const formData = new FormData();
    formData.append('cid', document.getElementById('categoryID').value);
    appendWidth(formData);
    appendColours(formData);
    return formData;
}