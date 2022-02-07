/** 
 * Initiates new component tabs, adding components by dragging/selected and new component buttons
 *
 */

import { initUpdateNewComponentButtons } from './updateNewComponentButtons';
import { initAddNewComponents } from './addNewComponents';
import { initNewComponentsTab } from './newComponentsTabs';
 
/**
 * Add in the behaviour for new components
 */
export function initNewComponents () {
    initUpdateNewComponentButtons();
    initAddNewComponents();
    initNewComponentsTab();
}
