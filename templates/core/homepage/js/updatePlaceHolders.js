/** 
 * Show/hide the place holders depending when new components tab is open or when sorting 
 * and a component container is empty
 *
 */

import { ADD_PLACE_HOLDERS, REMOVE_PLACE_HOLDERS } from './updateHomepage';

const COMPONENT_CONTAINERS_SELECTOR = '[data-components-container]'; //the top/left/right parent containers

let __placeHolderComponentsList = []; //to contain the place holder instances


/**
 * Fire everything up
 */
 export function initUpdatePlaceHolders () {
    initPlaceHolderComponents();
    document.addEventListener(ADD_PLACE_HOLDERS, addPlaceHolderComponents);
    document.addEventListener(REMOVE_PLACE_HOLDERS, removePlaceHolderComponents);
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
 * There's a bit of an issue where we can FLIP on sort change, and then also remove the placeHolderComponent
 * that is within the FLIP. This causes an issue we need to check for
 * 
 * @param { Element }
 * 
 * @returns { Boolean }
 */
function canAddPlaceHolderComponent (parentContainer) {
    return parentContainer.children.length === 0;
    // return (parentContainer.children.length === 0) || //only the existing .masonry-placeholder is in the container
        //    (parentContainer.children.length === 2 && !parentContainer.children[1].id); //the existing .masonry-placeholder and also a div that's left over from FLIP, which won't contain an ID
}

/**
 * Remove and re-add as parent containers have changed
 */
function removePlaceHolderComponents () {
    document.querySelectorAll('[data-place-holder-component]').forEach((placeHolderComponent) => {
        placeHolderComponent.remove();
    });
}