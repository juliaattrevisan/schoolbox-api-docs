/** 
 * Initiates component sorting, settings updates, removing, and collapsing
 *
 */

import { canEditHomepage } from '../updateHomepage';
import { initCollapseComponents } from './collapseComponents';
import { initSortExistingComponents } from './sortExistingComponents';
import { initUpdateComponentsSettings } from './updateComponentsSettings';
import { initRemoveComponents } from './removeComponents';

/**
 * Add in the behaviour for existing components
 */
export function initExistingComponents () {
    initCollapseComponents();
    if (canEditHomepage()) {
        initSortExistingComponents();
        initUpdateComponentsSettings();
        initRemoveComponents();
    }
}