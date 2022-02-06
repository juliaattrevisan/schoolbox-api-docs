/** 
 * Edit  the colours/width of the components
 *
 */

import { initEditColumnWidths } from './editColumnsWidths';
import { initEditColumnButtons } from './editColumnsButtons';
import { initSaveEditColumns } from './saveEditColumns';

/**
 * Fire everything up 
 */
export function initEditColumns () {
    initEditColumnWidths();
    initEditColumnButtons();
    initSaveEditColumns();
}
