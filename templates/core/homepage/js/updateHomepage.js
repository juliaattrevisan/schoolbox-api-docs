/** 
 * Handles the majority of functionality on a Home page, including adding, sorting, and updating
 *
 */

import { gsap, Draggable, Flip } from 'gsap/all';
import { insertElementBefore, insertElementAfter, getClosestElement, isElementAboveOtherElement, 
         isElementBelowOtherElement, setStylesOnElement } from '@js/util/domUtils';
import { submitNewComponentOrder } from './submitNewComponentOrder';
         

gsap.registerPlugin(Draggable, Flip);

const COMPONENET_CONTAINERS_SELECTOR = '[data-components-container]'; //the top/left/right parent containers
export const COMPONENT_SELECTOR = '[data-component], [data-component-palette]'; //any component that will be dragged/added/sorted
const COMPONENT_TRIGGER_SELECTOR = '[data-component-handle]'; //the top bar of the component that will be used as the drag trigger
const SORTABLE_SELECTOR = '[data-component]'; //existing components that can be sorted
const ANIMATE_SPEED = 0.3; //the speed of animations
const MIN_MOVEMENT = 50; //minimum amount of movement before we can FLIP again

let __draggablesList = []; //to contain the Draggable instances, to enable/disable depending on the #component-lock-switch status and add/remove when components added/removed
let __placeHolderComponentsList = []; //to contain the Draggable instances, to enable/disable depending on the #component-lock-switch status and add/remove when components added/removed
let __activeComponent; //the component that's currently being dragged/sorted around
let __placeHolderComponent; //the element that subs in for the __activeComponent being sorted
let __hasSortChange = false; //to know whether to update the DB with sort change
let __isScrollingTimeout = false; //to prevent sorting when moving a sortable up/down the window
let __isScrolling = false;
let __isFlipping = false; //prevent quick shifts / odd flickers while using FLIP
let __isChangingSize = false; //if required, wait for the element to shrink down to just the top bar before dragging as some elements are too long to move around effectively
let __startDragPoint = { left: 0, top: 0 }; //to prevent rapid fire position changes, have to move a minimum amount prior to another change


/*****************************************************************
 * 
 * INITIATION
 * 
 ****************************************************************/

/**
 * Fire up Draggable instances, looping through each sortable to assign a Draggable instance 
 */
const initDraggables = () => {
    document.querySelectorAll(COMPONENT_SELECTOR).forEach((sortableItem) => {
        __draggablesList = [...__draggablesList, getDraggable(sortableItem)];
    });
}

/**
 * To enable Dragging functionality to the chosen item
 * Refer to https://greensock.com/docs/v3/Plugins/Draggable for further info
 */
const getDraggable = (sortableItem) => {
    const sortableItemTrigger = isExistingComponent(sortableItem) ? sortableItem.querySelector(COMPONENT_TRIGGER_SELECTOR) : sortableItem;//existing components use a handle, the ones in the palette do not
    sortableItemTrigger.addEventListener('click', (event) => event.preventDefault());
    sortableItemTrigger.addEventListener('dblclick', (event) => event.preventDefault());
    return Draggable.create(sortableItem, {
        type: 'x, y',
        trigger: sortableItemTrigger,
        autoScroll: 1,
        onDragStart: (event) => onDragStart(event),
        onDrag: () => onDrag(),
        onDragEnd: () => onDragEnd(),
    })[0];
}

/**
 * Is a new component, one that's currently within the palette
 * 
 * @param { DOMElement } 
 */
const isNewComponent = (component) => {
    return component.hasAttribute('data-component-palette');
}

/**
 * Is already on the page
 * 
 * @param { DOMElement } 
 */
 const isExistingComponent = (component) => {
    return component.hasAttribute('id');
}

/**
 * To respond to any events that require action
 */
 const initListeners = () => {
    // initComponentLockSwitchListener(); - may add in again when actually adding the palette functionality
    initOnComponentAddedListener();
    initOnComponentRemovedListener();
}

/**
 * Listen to changes and enable/disable the sorting based on the status of #component-lock-switch
 */
// const initComponentLockSwitchListener = () => {
//     const componentLockSwitch = document.getElementById('component-lock-switch').querySelector('input');
//     componentLockSwitch.addEventListener('change', (event) => updateDraggablesStatus(event.target.checked));
// }

/**
 * Run through each Draggable instance and enable/disable
 * 
 * @param { Boolean }  
 */
 const updateDraggablesStatus = (isEnabled) => {
    __draggablesList.forEach((draggable) => isEnabled ? draggable.enable() : draggable.disable()); 
}

/**
 * To add a newly created component to the sortable list
 */
 const initOnComponentAddedListener = () => {
    document.addEventListener('jquery.masonry__onComponentAdded', (event) => {
        const newDraggable = getDraggable(document.getElementById(event.detail.componentID));
        const componentLockSwitchStatus = document.getElementById('component-lock-switch').querySelector('input').checked; //check the status of the input to know whether the new sortable should be active
        newDraggable.enabled(componentLockSwitchStatus); //check 
        __draggablesList = [...__draggablesList, newDraggable]; //add the component into the Draggable instances list
        updateSortableElements(); //update the position of all elements due to the new element that's been added
    });
}

/**
 * To remove a component to the sortable list
 */
 const initOnComponentRemovedListener = () => {
    document.addEventListener('jquery.masonry__onComponentRemoved', (event) => {
        const draggableToRemove = __draggablesList.find((draggable) => getComponentID(draggable.target) === event.detail.componentID);
        draggableToRemove.kill(); //disables the Draggable instance
        __draggablesList = __draggablesList.filter((draggable) => getComponentID(draggable.target) !== event.detail.componentID); //update the __draggablesList list, pullling out the removed component
        updateSortableElements(); //update the position of all elements due to the new element being added
    });
}

/**
 * need to run through and check each parent container for children
 * if there's no children need to show that items can be dragged into this container
 */
 const initPlaceHolderComponents = () => {
    document.querySelectorAll(COMPONENET_CONTAINERS_SELECTOR).forEach(() => {
        const placeHolderComponent = document.createElement('div');
        placeHolderComponent.classList.add('masonry-placeholder');
        placeHolderComponent.style.display = 'block';
        placeHolderComponent.style.height = '51px';
        placeHolderComponent.dataset.component = 'true';
        placeHolderComponent.dataset.placeHolderComponent = 'true';
        placeHolderComponent.innerText = 'You can drag components into this space';
        __placeHolderComponentsList = [...__placeHolderComponentsList, placeHolderComponent];
    });
}


/*****************************************************************
 * 
 * START DRAG
 * 
 ****************************************************************/

/**
 * Sub in a placeholder to use in place of the element being dragged, 
 * which gets pulled out so it can go into any other parent container 
 * that contains the COMPONENT_SELECTOR
 */
const onDragStart = (event) => {
    __activeComponent = event.target.closest(COMPONENT_SELECTOR); //use the parent container regardless of selection to ensure correct dimensions etc
    __placeHolderComponent = getPlaceholderElement();
    __startDragPoint = updateDragPoint(); //to track when we can allow sorting check based on MIN_MOVEMENT
    updateDocumentForDrag();
    disableAllOtherDraggables();
    extractActiveComponent();
    updateSortableElements(); //ensure we have the most up to date positions
    if (isExistingComponent(__activeComponent)) {
        collapseExistingComponentContent();
    } else {
        __isChangingSize = false;
        addPlaceHolderComponents();
        hideExistingMasonryPlaceHolders();
    }
}

/**
 * Create a clone of the __activeComponent being dragged to use as a placeholder 
 * and to keep it in position as the __activeComponent will be pulled out of context
 * 
 * @returns { DOMElement }  
 */
const getPlaceholderElement = () => {
    const cloneElement = __activeComponent.cloneNode(true);
    if (isExistingComponent(__activeComponent)) { //only existing components have to worry about removing these elements
        cloneElement.querySelector('[data-collapsable]').remove(); //just use the top bar as reference, so remove all component controls that can be expanded
        cloneElement.querySelector('[data-component-panel="settings"]').remove(); 
        cloneElement.querySelector('[data-component-panel="delete"]').remove();
    } else {
        cloneElement.setAttribute('data-component', ''); //so it can be considered when sorting
    }
    cloneElement.style.opacity = 0.5; //displayed as reference where the __activeComponent is currently placed 
    insertElementBefore(__activeComponent, cloneElement);
    return cloneElement;
}

/**
 * To track drag movement
 * 
 * @returns { left, top } 
 */
const updateDragPoint = () => {
    return { 
        left: __activeComponent.getBoundingClientRect().left,
        top: __activeComponent.getBoundingClientRect().top + window.scrollY
    }
}

/**
 * Prep the document to handle dragging
 */
const updateDocumentForDrag = () => {
    document.body.style.overflowX = 'hidden'; //avoid horizontal scroll bars when a sortable gets moved beyond the window edges
    document.documentElement.style.scrollBehavior = 'auto'; //disable smooth scroll as it can jar while moving elements around 
    document.querySelectorAll('[data-collapsable]').forEach((element) => element.style.pointerEvents = 'none') //prevent any mouse interactions interferring while dragging
    document.addEventListener('scroll', onScroll); //prevent sorting check while moving up/down window
}

/**
 * Don't do sorting while moving up or down the window due to dragging at the window edges 
 */
const onScroll = () => {
    __isScrolling = true;
    window.clearTimeout(__isScrollingTimeout); // Clear our timeout throughout the scroll
    __isScrollingTimeout = setTimeout(() => { // Set a timeout to run after scrolling ends
        __isScrolling = false; 
    }, 66); 
}

/**
 * Disable all others until current drag is finished to avoid rapid fire / double click issues
 */
const disableAllOtherDraggables = () => {
    __draggablesList.forEach((draggable) => { 
        if (draggable.target.id !== __activeComponent.id) { //to avoid disabling the current
            draggable.disable();
        }
    }); 
}

/**
 * With the __placeHolderComponent in place, the __activeComponent is pulled out
 * so it doesn't impact our ability to sort through the items and can go into any container
 */
const extractActiveComponent = () => {
    document.body.appendChild(__activeComponent);
    __activeComponent.removeAttribute('data-component'); //so there's no position checks when dragging, as the placeholder is there instead
    setStylesOnElement(__activeComponent, { //Update position and styles to match now that the __activeComponent has been moved out of context
        position: 'absolute',
        top: `${__placeHolderComponent.getBoundingClientRect().top + window.scrollY}px`,
        left: `${__placeHolderComponent.getBoundingClientRect().left - 16 }px`, //subtracting 16 to deal with 1rem padding-left that's applied
        width: `${__placeHolderComponent.getBoundingClientRect().width + 32 }px`, //and adding 32 to also deal with 1rem padding-left, padding-right that's applied each side
    });
    if (isExistingComponent(__activeComponent)) {
        updateExistingComponentComputedStyles(__activeComponent);
    }
}

/**
 * Ensure custom set colour vars are respected and set when moving between parent containers
 */
const updateExistingComponentComputedStyles = () => {

    const containerPosition = __placeHolderComponent.parentElement.dataset.position; //to ensure the correct colours are used relative to which parent container the element belongs to

    const headBg = getComputedStyle(__placeHolderComponent).getPropertyValue(`--${containerPosition}-head-bg`); //use __placeHolderComponent as it still exists in the correct context
    __activeComponent.querySelectorAll('.component-titlebar').forEach((item) => item.style.backgroundColor = headBg );

    const headFont = getComputedStyle(__placeHolderComponent).getPropertyValue(`--${containerPosition}-head-font`);
    __activeComponent.querySelectorAll('.component-titlebar h2').forEach((item) => item.style.color = headFont );
    __activeComponent.querySelectorAll('.component-titlebar a').forEach((item) => item.style.color = headFont );

}

/**
 * Shrink down the element to the top bar, which is used to sort to 
 * avoid trying to sort tall height elements
 */
const collapseExistingComponentContent = () => {
    
    __isChangingSize = true;
    
    const existingComponentSettingsPanel = __activeComponent.querySelector('[data-component-panel="settings"]');
    gsap.to(existingComponentSettingsPanel, { 
        onStart: () => existingComponentSettingsPanel.style.overflow = 'hidden',
        height: 0, 
        padding: 0,
        duration: ANIMATE_SPEED,
    } );

    const existingComponentDeletePanel = __activeComponent.querySelector('[data-component-panel="delete"]');
    gsap.to(existingComponentDeletePanel, { 
        onStart: () => existingComponentDeletePanel.style.overflow = 'hidden',
        height: 0, 
        padding: 0,
        duration: ANIMATE_SPEED,
    } );
    
    const existingComponentContent = __activeComponent.querySelector('[data-collapsable]');
    gsap.to(existingComponentContent, { 
        onStart: () => existingComponentContent.style.overflow = 'hidden',
        height: 0, 
        duration: ANIMATE_SPEED,
        onComplete: () => {
            __isChangingSize = false;
            addPlaceHolderComponents();
            hideExistingMasonryPlaceHolders();
        }
    } );

}

/**
 * need to run through and check each parent container for children
 * if there's no children except the .masonry-placeholder need to show that items can be dragged into this container
 */
const addPlaceHolderComponents = () => {
    document.querySelectorAll(COMPONENET_CONTAINERS_SELECTOR).forEach((parentContainer, index) => {
        if (canAddPlaceHolderComponent(parentContainer)) { //something going on in here.. with FLIP
            const placeHolderComponent = __placeHolderComponentsList[index];
            parentContainer.append(placeHolderComponent);
        }
    });
}

/**
 * there's a bit of an issue where we can FLIP on sort change, and then also remove the placeHolderComponent
 * that is within the FLIP. This causes an issue we need to check for
 * 
 * @param { DOMElement }
 * 
 * @returns { Boolean }
 */
const canAddPlaceHolderComponent = (parentContainer) => {
    return (parentContainer.children.length === 1) || //only the existing .masonry-placeholder is in the container
           (parentContainer.children.length === 2 && !parentContainer.children[1].id); //the existing .masonry-placeholder and also a div that's left over from FLIP, which won't contain an ID
}

/**
 * When the add components tab is open, there would be a double up of place holders in parent containers
 * so need to hide them while dragging, and they are shown again when drag is released
 * Refer to: www/static/js/masonry/jquery.masonry.js for reference
 */
const hideExistingMasonryPlaceHolders = () => {
    const addComponentTabs = document.getElementById('tabs-1');
    if (addComponentTabs.classList.contains('active')) {
        document.querySelectorAll('.masonry-placeholder').forEach((placeHolder) => { 
            if (placeHolder.dataset.placeHolderComponent !== 'true') { //make sure they are not the placeHolderComponents we need to allow proper sorting 
                placeHolder.style.position = 'absolute';
                placeHolder.style.visibility = 'hidden';
            }
        });
    }
}

/**
 * To be able to sort elements and snap back to correct position
 */
const updateSortableElements = () => {
    document.querySelectorAll(COMPONENT_SELECTOR).forEach((sortableItem) => {
        sortableItem.dataset.originalLeft = sortableItem.getBoundingClientRect().left;
        sortableItem.dataset.originalTop = sortableItem.getBoundingClientRect().top + window.scrollY;
        sortableItem.dataset.leftDifference = 0;
        sortableItem.dataset.topDifference = 0;
    });
}


/*****************************************************************
 * 
 * DRAGGING
 * 
 ****************************************************************/

/**
 * Constantly check for the nearest other sortable and update position 
 * based on whether a move is valid
 */
const onDrag = () => {
    if (canCheckForChange()) { 
        const sortablesToCheckList = Array.from(document.querySelectorAll(SORTABLE_SELECTOR)); //grab all sortable elements 
        const closestElement = getClosestElement(__activeComponent, sortablesToCheckList); //the element nearest to the dragged element
        if (closestElement && closestElement.id !== __activeComponent.id) { //no need to FLIP if the closest element is still the __activeComponent
            if (isElementAboveOtherElement(__activeComponent, closestElement)) { //otherwise determine movement direction, up or down into the container
                flipState(closestElement, true);
            } else if (isElementBelowOtherElement(__activeComponent, closestElement)) {
                flipState(closestElement, false);
            }
        }
    }
}

/**
 *  Don't be checking while have hardly dragged, while the window is scrolling or while the __activeComponent is still shrinking in collapseExistingComponentContent()
 * 
 * @returns { Boolean } 
 */
const canCheckForChange = () => {
    const currentDragPoint = { 
        left: __activeComponent.getBoundingClientRect().left,
        top: __activeComponent.getBoundingClientRect().top + window.scrollY
    }
    const distance = Math.sqrt(Math.pow(__startDragPoint.left - currentDragPoint.left, 2) + Math.pow(__startDragPoint.top - currentDragPoint.top, 2));
    return distance > MIN_MOVEMENT && !__isScrolling && !__isChangingSize;
}

/**
 * Use Flip to shift the elements around, recording the difference in shift 
 * for when the dragged element is released
 * Refer to https://greensock.com/docs/v3/Plugins/Flip for more info on Flip
 * 
 * @param { DOMElement }  
 * @param { Boolean }  
 */
const flipState = (closestElement, isShiftingDown) => {
    
    __hasSortChange = true;

    if (!__isFlipping) { //don't allow multiple FLIPs to prevent quick shifts / weird jarring behaviour

        __isFlipping = true;
        __startDragPoint = updateDragPoint();

        const state = Flip.getState(document.querySelectorAll(SORTABLE_SELECTOR));
        
        if (isShiftingDown) {
            insertElementAfter(closestElement, __placeHolderComponent);
        } else {
            insertElementBefore(closestElement, __placeHolderComponent);
        }

        if (isNewComponent(__activeComponent)) {
            __placeHolderComponent.style.marginBottom = '2rem'; //existing components all have a margin
            __activeComponent.style.marginBottom = '2rem'; //existing components all have a margin
        }

        removePlaceHolderComponents(); //remove prior to recording the new co-ordinates so can snap back on release to correct location
        
        __activeComponent.dataset.leftDifference = __placeHolderComponent.getBoundingClientRect().left - parseInt(__placeHolderComponent.dataset.originalLeft); //so can animate the dragged element to this new position on mouse release
        __activeComponent.dataset.topDifference = (__placeHolderComponent.getBoundingClientRect().top + window.scrollY) - parseInt(__placeHolderComponent.dataset.originalTop);
        gsap.to(__activeComponent, { width: __placeHolderComponent.parentElement.offsetWidth, duration: ANIMATE_SPEED } ); //parentElement as the placeHolder may not have fully shifted into place before mouse release})

        addPlaceHolderComponents(); //now check if need to add them back in

        Flip.from(state, {
            duration: ANIMATE_SPEED,
            onComplete: () => {
                __isFlipping = false;
                onDrag(); //check after Flip to see if another is required
            }
        });
        
    }

}

/**
 * Remove and re-add as parent containers have changed
 */
const removePlaceHolderComponents = () => {
    document.querySelectorAll('[data-place-holder-component]').forEach((placeHolderComponent) => {
        placeHolderComponent.remove();
    });
}


/*****************************************************************
 * 
 * END DRAG
 * 
 ****************************************************************/

/**
 * Just snap back to where it now belongs
 */
const onDragEnd = () => {
    removePlaceHolderComponents();
    showExistingMasonryPlaceHolders();
    updateDraggablesStatus(false); //prevent quick reclicks on elements
    snapBackActiveComponent();
}

/**
 * When the add components tab is open, there would be a double up of place holders in parent containers
 * so need to hide them while dragging, and they are shown again when drag is released
 */
const showExistingMasonryPlaceHolders = () => {
    const addComponentTabs = document.getElementById('tabs-1');
    if (addComponentTabs.classList.contains('active')) { 
        document.querySelectorAll('.masonry-placeholder').forEach((placeHolder) => {
            if (placeHolder.dataset.placeHolderComponent !== 'true') { //make sure they are not the placeHolderComponents we need to allow proper sorting 
                placeHolder.style.position = 'static'; 
                placeHolder.style.visibility = 'visible';
            }
        });
    }
}

/**
 * Move the component back to its original spot or its new 
 */
const snapBackActiveComponent = () => {
    gsap.to(__activeComponent, {
        x: 0 + parseInt(__activeComponent.dataset.leftDifference), //to accommodate for potential position changes that have occured
        y: 0 + parseInt(__activeComponent.dataset.topDifference),
        width: __placeHolderComponent.parentElement.offsetWidth, //parentElement as the placeHolder may not have fully shifted into place before mouse release
        duration: ANIMATE_SPEED,
        overwrite: true,
        onComplete: () => onSnapBackActiveComponentComplete()
    });
}

/**
 * Tidy up once the element has snapped back 
 * setting up for another drag, returning various variables back to there pre dragging state etc
 * 
 * @param { DOMElement }  
 */
const onSnapBackActiveComponentComplete = () => {

    gsap.set(__activeComponent, { x: 0, y: 0, width: '100%' }); //reset the animation properties for another animate
    __activeComponent.style.position = 'static'; //was absolute, now sitting as it should
    __activeComponent.setAttribute('data-component', ''); //so it can be dragged again
    updateExistingComponentComputedStyles(); //ensure the defined colours are respected

    insertElementAfter(__placeHolderComponent, __activeComponent); //the element was taken out, so put it back where it now belongs
    __placeHolderComponent.remove(); //and now the placeholder can be pulled out

    if (isExistingComponent(__activeComponent)) {
        expandExistingComponent();
    } else {
        onActiveComponentExpanded();
    }

}

/**
 * Expand out the existingComponent again now that's its' back in place
 * The panels will animate regardless of whether they are opened or closed
 */
const expandExistingComponent = () => {

    const existingComponentSettingsPanel = __activeComponent.querySelector('[data-component-panel="settings"]');
    gsap.to(existingComponentSettingsPanel, { 
        height: 'auto', 
        padding: '1rem',
        duration: ANIMATE_SPEED,
        onComplete: () => existingComponentSettingsPanel.style.overflow = 'visible'
    } );

    const existingComponentDeletePanel = __activeComponent.querySelector('[data-component-panel="delete"]');
    gsap.to(existingComponentDeletePanel, { 
        height: 'auto', 
        padding: '1rem',
        duration: ANIMATE_SPEED,
        onComplete: () => existingComponentDeletePanel.style.overflow = 'visible'
    } );

    const existingComponentContent = __activeComponent.querySelector('[data-collapsable]');
    gsap.to(existingComponentContent, { 
        height: 'auto', 
        duration: ANIMATE_SPEED, 
        onComplete: () => {
            existingComponentContent.style.overflow = 'visible';
            onActiveComponentExpanded();
        }
    } ); 

 }

/**
 * To set up for another possible drag of elements
 */
const onActiveComponentExpanded = async () => {

    document.body.style.overflowX = 'auto'; //was hidden to avoid horizontal scroll bars
    document.documentElement.style.scrollBehavior = 'smooth'; //was auto to avoid jarring behaviour 
    document.querySelectorAll('[data-collapsable]').forEach((element) => element.style.pointerEvents = 'auto'); //re-enable mouse interactions
    document.removeEventListener('scroll', onScroll); //no need to listen for scrolling until next drag
    window.dispatchEvent(new Event('resize')); //for components to respond to - for example swiper.js image gallerys to update to display correctly
    
    updateSortableElements();

    if (__hasSortChange) {
        await submitNewComponentOrder(__activeComponent);
        __hasSortChange = false;
    }
    updateDraggablesStatus(true); //ensure all are enabled again once animation and form change is complete

}


/**
 * Fire everything up
 */
export const init = () => {
    initDraggables();
    initListeners();
    initPlaceHolderComponents();
}

document.addEventListener('DOMContentLoaded', init);
