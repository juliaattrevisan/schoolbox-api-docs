/** 
 * Initiates component sorting, settings updates, removing, and collapsing
 *
 */

import { initSortExistingComponents } from './sortExistingComponents';
import { initUpdateComponentsSettings } from './updateComponentsSettings';
import { initRemoveComponents } from './removeComponents';
import { initCollapseComponents } from './collapseComponents';

/**
 * Add in the behaviour for existing components
 */
export function initExistingComponents () {
    initSortExistingComponents();
    initUpdateComponentsSettings();
    initRemoveComponents();
    initCollapseComponents();
}
