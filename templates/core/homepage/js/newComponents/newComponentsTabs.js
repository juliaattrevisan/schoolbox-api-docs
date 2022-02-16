/** 
 * Manages the display of the new components tab 
 * (the tabs that sit at the top of the page and can be accssed with the + button)
 *
 */

import UpdateHomepageEvents from '../updateHomepageEvents';


/**
 * To ensure only the new components tab or the edit columns view is open at once
 */
export function initNewComponentsTab () {
    
    const homepage = $('#component-layout');

    // Fix slider on tab
    $('[data-tab]').on('toggled', function (event, tab) {
        tab.foundation('reflow');
        const tabData = tab.data(); // What tab is this?
        if ('hideEmpty' in tabData) {
            homepage.removeClass('show-empty');
        } else if ('showEmpty' in tabData) {
            homepage.addClass('show-empty');
        }
    });

    //to show/hide the 'add components here' placeholders in empty columns when the new tabs component is open
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
 * Used by addNewComponents.js to determine whether placeholders need to be displayed
 * 
 * @returns { Boolean } 
 */
export function isOpen () {
    const tabs = document.getElementById('tabs-1');
    return tabs.classList.contains('active');
}