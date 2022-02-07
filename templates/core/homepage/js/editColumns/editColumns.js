/** 
 * Edit  the colours/width of the components
 *
 */

import { initEditColumnsWidths } from './editColumnsWidths';
import { initEditColumnsColours } from './editColumnsColours';
import { initEditColumnsButtons } from './editColumnsButtons';
import { initSaveEditColumns } from './saveEditColumns';

/**
 * Fire everything up 
 */
export function initEditColumns () {
    initEditColumnsWidths();
    initEditColumnsColours();
    initEditColumnsButtons();
    initSaveEditColumns();
}
