/** 
 * Manages the display of the new components tab
 *
 */

import UpdateHomepageEvents from '../updateHomepageEvents';


/**
 * Fire everything up
 */
export function initNewComponentsTab () {
    
    const homepage = $('#component-layout');

    // Fix slider on tab
    $('[data-tab]').on('toggled', function (event, tab) {
        tab.foundation('reflow');
        // What tab is this?
        const tabData = tab.data();
        if ('hideEmpty' in tabData) {
            homepage.removeClass('show-empty');
        } else if ('showEmpty' in tabData) {
            homepage.addClass('show-empty');
        }
    });



    /* Tab toggler
    * Deal with special components placeholder
    */
    $.fn.tabActivator({
        onTabOpen: function() {
            document.dispatchEvent(new Event(UpdateHomepageEvents.NEW_COMPONENTS_TABS_OPENED));
        },
        onTabClose: function() {
            document.dispatchEvent(new Event(UpdateHomepageEvents.NEW_COMPONENTS_TABS_CLOSED));
            homepage.removeClass('show-empty');
        }
    });

}

/**
 * Is the tabs open?
 * 
 * @returns { Boolean } 
 */
export function isOpen () {
    const tabs = document.getElementById('tabs-1');
    return tabs.classList.contains('active');
}