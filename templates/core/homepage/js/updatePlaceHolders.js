/** 
 * Show/hide the place holders depending when new components tab is open or when sorting 
 * and a component container is empty
 *
 */

import UpdateHomepageEvents from './updateHomepageEvents';
import { isOpen as isNewComponentTabsOpen } from './newComponents/newComponentsTabs';

const COMPONENT_CONTAINERS_SELECTOR = '[data-components-container]'; //the top/left/right parent containers

let __placeHolderComponentsList = []; //to contain the place holder instances


/**
 * Fire everything up
 */
 export function initUpdatePlaceHolders () {
    initPlaceHolderComponents();
    document.addEventListener(UpdateHomepageEvents.EXISTING_COMPONENT_SORT_START, addPlaceHolderComponents);
    document.addEventListener(UpdateHomepageEvents.NEW_COMPONENTS_TABS_OPENED, addPlaceHolderComponents);
    document.addEventListener(UpdateHomepageEvents.EXISTING_COMPONENT_SORT_UPDATE, updatePlaceHolderComponents);
    document.addEventListener(UpdateHomepageEvents.NEW_COMPONENT_SORT_UPDATE, updatePlaceHolderComponents);
    document.addEventListener(UpdateHomepageEvents.EXISTING_COMPONENT_SORT_END, onExistingComponentSortEnd);
    document.addEventListener(UpdateHomepageEvents.NEW_COMPONENTS_TABS_CLOSED, removePlaceHolderComponents);
}

/**
 * Need to run through and check each parent container for children
 * if there's no children need to show that items can be dragged into this container
 */
function initPlaceHolderComponents () {
    document.querySelectorAll(COMPONENT_CONTAINERS_SELECTOR).forEach((container, index) => {
        const placeHolderComponent = document.createElement('div');
        placeHolderComponent.id = `placeHolderComponent-${index}`;
        placeHolderComponent.classList.add('masonry-placeholder');
        placeHolderComponent.style.display = 'block';
        placeHolderComponent.style.height = '51px';
        placeHolderComponent.dataset.component = 'true';
        placeHolderComponent.dataset.placeHolderComponent = 'true';
        placeHolderComponent.innerText = 'You can drag components into this space';
        __placeHolderComponentsList = [...__placeHolderComponentsList, placeHolderComponent];
    });
}

/**
 * Need to run through and check each parent container for children
 * if there's no children except the .masonry-placeholder need to show that items can be dragged into this container
 */
function addPlaceHolderComponents () {
    document.querySelectorAll(COMPONENT_CONTAINERS_SELECTOR).forEach((parentContainer, index) => {
        if (canAddPlaceHolderComponent(parentContainer)) { 
            const placeHolderComponent = __placeHolderComponentsList[index];
            parentContainer.append(placeHolderComponent);
        }
    });
}

/**
 * Is the parent container empty? If so a place holder can be added
 * 
 * @param { Element }
 * 
 * @returns { Boolean }
 */
function canAddPlaceHolderComponent (parentContainer) {
    return parentContainer.children.length === 0;
}

/**
 * Remove and re-add as parent containers have changed
 */
function updatePlaceHolderComponents () {
    removePlaceHolderComponents();
    addPlaceHolderComponents();
}

/**
 * Remove all the placeholders only if the new component tabs aren't open
 */
function onExistingComponentSortEnd () {
    if (!isNewComponentTabsOpen()) { //don't hide them when the new component tabs is open
        removePlaceHolderComponents();
    }
}

/**
 * Remove all the placeholders
 */
function removePlaceHolderComponents () {
    document.querySelectorAll('[data-place-holder-component]').forEach((placeHolderComponent) => {
        placeHolderComponent.remove(); //will just fail silently if isn't in the DOM
    });
}