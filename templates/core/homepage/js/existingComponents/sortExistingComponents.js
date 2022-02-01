/** 
 * Sort existing components, moving them around the page to the 
 * desired position then updating the DB of the new position
 *
 */

import { gsap, Draggable, Flip } from 'gsap/all';
import { insertElementBefore, insertElementAfter, getClosestElement, isElementAboveOtherElement, 
    isElementBelowOtherElement, setStylesOnElement } from '@js/util/domUtils';
import { saveComponentOrder } from './saveComponentOrder';
import { ON_COMPONENT_ADDED, ON_COMPONENT_REMOVED, ADD_PLACE_HOLDERS, REMOVE_PLACE_HOLDERS } from '../updateHomepage';

gsap.registerPlugin(Draggable, Flip);

const COMPONENT_CONTAINERS_SELECTOR = '[data-components-container]'; //the top/left/right parent containers
const COMPONENT_SELECTOR = '[data-component]'; //any existing component that will be sorted
const COMPONENT_TRIGGER_SELECTOR = '[data-component-handle]'; //the top bar of the component that will be used as the drag trigger
const ANIMATE_SPEED = 0.3; //the speed of animations
const MIN_MOVEMENT = 50; //minimum amount of movement before we can FLIP again

let __draggablesList = []; //to contain the Draggable instances, to enable/disable depending on the #component-lock-switch status and add/remove when components added/removed
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
 * Fire everything up
 */
export function initSortExistingComponents () {
    initDraggables();
    initListeners();
}

/**
 * Fire up Draggable instances, looping through each sortable to assign a Draggable instance 
 */
function initDraggables () {
    document.querySelectorAll(COMPONENT_SELECTOR).forEach((component) => {
        __draggablesList = [...__draggablesList, addNewDraggable(component)];
    });
}

/**
 * To enable Dragging functionality on a component
 * Refer to https://greensock.com/docs/v3/Plugins/Draggable for further info
 * 
 * @returns { Draggable }
 */
function addNewDraggable (component) {
    const componentTrigger = component.querySelector(COMPONENT_TRIGGER_SELECTOR);
    componentTrigger.addEventListener('click', (event) => event.preventDefault());
    componentTrigger.addEventListener('dblclick', (event) => event.preventDefault());
    return Draggable.create(component, {
        type: 'x, y',
        trigger: componentTrigger,
        autoScroll: 1,
        onDragStart: (event) => onDragStart(event),
        onDrag: () => onDrag(),
        onDragEnd: () => onDragEnd(),
    })[0];
}

/**
 * To respond to any events that require action
 */
function initListeners () {
    initOnComponentAddedListener();
    initOnComponentRemovedListener();
}

/**
 * To add a newly created component to the sortable list
 */
function initOnComponentAddedListener () {
    document.addEventListener(ON_COMPONENT_ADDED, (event) => {
        const newDraggable = addNewDraggable(event.detail.component);
        const componentLockSwitchStatus = document.getElementById('component-lock-switch').querySelector('input').checked; //check the status of the input to know whether the new sortable should be active
        newDraggable.enabled(componentLockSwitchStatus); //check 
        __draggablesList = [...__draggablesList, newDraggable]; //add the component into the Draggable instances list
        updateSortableElements(); //update the position of all elements due to the new element that's been added
    });
}

/**
 * To remove a component from the sortable list
 */
function initOnComponentRemovedListener () {
    document.addEventListener(ON_COMPONENT_REMOVED, (event) => {
        const draggableToRemove = __draggablesList.find((draggable) => draggable.target.isEqualNode(event.detail.component));
        draggableToRemove.kill(); //disables the Draggable instance
        __draggablesList = __draggablesList.filter((draggable) => !draggable.target.isEqualNode(event.detail.component)); //update the __draggablesList list, pullling out the removed component
        updateSortableElements(); //update the position of all elements due to the new element being added
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
function onDragStart (event) {
    __activeComponent = event.target.closest(COMPONENT_SELECTOR); //use the parent container regardless of selection to ensure correct dimensions etc
    __placeHolderComponent = getPlaceholderElement();
    __startDragPoint = updateDragPoint(); //to track when we can allow sorting check based on MIN_MOVEMENT
    updateDocumentOnDragStart();
    disableAllOtherDraggables();
    extractActiveComponent();
    updateSortableElements(); //ensure we have the most up to date positions
    collapseExistingComponentContent();
}

/**
 * Create a clone of the __activeComponent being dragged to use as a placeholder 
 * and to keep it in position as the __activeComponent will be pulled out of context
 * 
 * @returns { Element }  
 */
function getPlaceholderElement () {
    const cloneElement = __activeComponent.cloneNode(true);
    cloneElement.querySelector('[data-collapsable]').remove(); //just use the top bar as reference, so remove all component controls that can be expanded
    cloneElement.querySelector('[data-component-panel="settings"]').remove(); 
    cloneElement.querySelector('[data-component-panel="delete"]').remove();
    cloneElement.style.opacity = 0.5; //displayed as reference where the __activeComponent is currently placed 
    insertElementBefore(__activeComponent, cloneElement);
    return cloneElement;
}

/**
 * To track drag movement
 * 
 * @returns { left, top } 
 */
function updateDragPoint () {
    return { 
        left: __activeComponent.getBoundingClientRect().left,
        top: __activeComponent.getBoundingClientRect().top + window.scrollY
    }
}

/**
 * Prep the document to handle dragging
 */
function updateDocumentOnDragStart () {
    document.body.style.overflowX = 'hidden'; //avoid horizontal scroll bars when a sortable gets moved beyond the window edges
    document.documentElement.style.scrollBehavior = 'auto'; //disable smooth scroll as it can jar while moving elements around 
    document.querySelectorAll('[data-collapsable]').forEach((element) => element.style.pointerEvents = 'none') //prevent any mouse interactions interferring while dragging
    document.addEventListener('scroll', onScroll); //prevent sorting check while moving up/down window
}

/**
 * Don't do sorting while moving up or down the window due to dragging at the window edges 
 */
function onScroll () {
    __isScrolling = true;
    window.clearTimeout(__isScrollingTimeout); // Clear our timeout throughout the scroll
    __isScrollingTimeout = setTimeout(() => { // Set a timeout to run after scrolling ends
        __isScrolling = false; 
    }, 66); 
}

/**
 * Disable all others until current drag is finished to avoid rapid fire / double click issues
 */
function disableAllOtherDraggables () {
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
function extractActiveComponent () {
    document.body.appendChild(__activeComponent);
    __activeComponent.removeAttribute('data-component'); //so there's no position checks when dragging, as the placeholder is there instead
    setStylesOnElement(__activeComponent, { //Update position and styles to match now that the __activeComponent has been moved out of context
        position: 'absolute',
        top: `${__placeHolderComponent.getBoundingClientRect().top + window.scrollY}px`,
        left: `${__placeHolderComponent.getBoundingClientRect().left - 16 }px`, //subtracting 16 to deal with 1rem padding-left that's applied
        width: `${__placeHolderComponent.getBoundingClientRect().width + 32 }px`, //and adding 32 to also deal with 1rem padding-left, padding-right that's applied each side
    });
    updateExistingComponentComputedStyles(__activeComponent);
}

/**
 * Ensure custom set colour vars are respected and set when moving between parent containers
 */
function updateExistingComponentComputedStyles () {

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
function collapseExistingComponentContent () {
    
    __isChangingSize = true;
    
    const existingComponentSettingsPanel = __activeComponent.querySelector('[data-component-panel="settings"]'); //just assume it may be open
    gsap.to(existingComponentSettingsPanel, { 
        onStart: () => existingComponentSettingsPanel.style.overflow = 'hidden',
        height: 0, 
        padding: 0,
        duration: ANIMATE_SPEED,
    } );

    const existingComponentDeletePanel = __activeComponent.querySelector('[data-component-panel="delete"]'); //just assume it may be open
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
            // addPlaceHolderComponents();
            document.dispatchEvent(new Event(ADD_PLACE_HOLDERS));
        }
    } );

}

/**
 * Need to run through and check each parent container for children
 * if there's no children except the .masonry-placeholder need to show that items can be dragged into this container
 */
// function addPlaceHolderComponents () {
//     document.querySelectorAll(COMPONENT_CONTAINERS_SELECTOR).forEach((parentContainer, index) => {
//         if (canAddPlaceHolderComponent(parentContainer)) { //something going on in here.. with FLIP
//             const placeHolderComponent = __placeHolderComponentsList[index];
//             parentContainer.append(placeHolderComponent);
//         }
//     });
// }

/**
 * There's a bit of an issue where we can FLIP on sort change, and then also remove the placeHolderComponent
 * that is within the FLIP. This causes an issue we need to check for
 * 
 * @param { Element }
 * 
 * @returns { Boolean }
 */
// function canAddPlaceHolderComponent (parentContainer) {
//     return (parentContainer.children.length === 0) || //only the existing .masonry-placeholder is in the container
//            (parentContainer.children.length === 2 && !parentContainer.children[1].id); //the existing .masonry-placeholder and also a div that's left over from FLIP, which won't contain an ID
// }

/**
 * To be able to sort elements and snap back to correct position
 */
function updateSortableElements () {
    document.querySelectorAll(COMPONENT_SELECTOR).forEach((component) => {
        component.dataset.originalLeft = component.getBoundingClientRect().left;
        component.dataset.originalTop = component.getBoundingClientRect().top + window.scrollY;
        component.dataset.leftDifference = 0;
        component.dataset.topDifference = 0;
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
function onDrag () {
    if (canCheckForChange()) { 
        const sortablesToCheckList = Array.from(document.querySelectorAll(COMPONENT_SELECTOR)); //grab all sortable elements 
        const closestElement = getClosestElement(__activeComponent, sortablesToCheckList); //the element nearest to the dragged element
        if (closestElement.id !== __activeComponent.id) { //no need to FLIP if the closest element is still the __activeComponent
            if (isElementAboveOtherElement(__activeComponent, closestElement)) { //otherwise determine movement direction, up or down into the container
                flipState(closestElement, true);
            } else if (isElementBelowOtherElement(__activeComponent, closestElement)) {
                flipState(closestElement, false);
            }
        }
    }
}

/**
 * Don't be checking while have hardly dragged, while the window is scrolling or while the __activeComponent is still shrinking in collapseExistingComponentContent()
 * 
 * @returns { Boolean } 
 */
function canCheckForChange () {
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
 * @param { Element }  
 * @param { Boolean }  
 */
function flipState (closestElement, isShiftingDown) {
    
    __hasSortChange = true;

    if (!__isFlipping) { //don't allow multiple FLIPs to prevent quick shifts / weird jarring behaviour

        __isFlipping = true;
        __startDragPoint = updateDragPoint();

        const state = Flip.getState(document.querySelectorAll(COMPONENT_SELECTOR));
        
        if (isShiftingDown) {
            insertElementAfter(closestElement, __placeHolderComponent);
        } else {
            insertElementBefore(closestElement, __placeHolderComponent);
        }

        clearFlipElements(); //check for flip bug
        document.dispatchEvent(new Event(REMOVE_PLACE_HOLDERS)); //remove prior to recording the new co-ordinates so can snap back on release to correct location

        __activeComponent.dataset.leftDifference = __placeHolderComponent.getBoundingClientRect().left - parseInt(__placeHolderComponent.dataset.originalLeft); //so can animate the dragged element to this new position on mouse release
        __activeComponent.dataset.topDifference = (__placeHolderComponent.getBoundingClientRect().top + window.scrollY) - parseInt(__placeHolderComponent.dataset.originalTop);
        gsap.to(__activeComponent, { width: __placeHolderComponent.parentElement.offsetWidth, duration: ANIMATE_SPEED } ); //parentElement as the placeHolder may not have fully shifted into place before mouse release})

        document.dispatchEvent(new Event(ADD_PLACE_HOLDERS)); //now check if need to add them back in

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
 * Flip currently sometimes leaves a left over element while dragging between elements. It's cleared on mouse release, but we need 
 * it to be cleared sooner as we need to check for whether we need to add place holders after a component shift
 */
function clearFlipElements () {
    document.querySelectorAll(COMPONENT_CONTAINERS_SELECTOR).forEach((parentContainer, index) => {
        if (parentContainer.children.length === 1 && !parentContainer.children[0].id) { //there's the flip element here, so we should clear it out
            parentContainer.removeChild(parentContainer.children[0]);
        }
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
function onDragEnd () {
    updateDraggablesStatus(false); //prevent quick reclicks on elements
    document.dispatchEvent(new Event(REMOVE_PLACE_HOLDERS));
    showExistingMasonryPlaceHolders();
    snapBackActiveComponent();
}

/**
 * Run through each Draggable instance and enable/disable
 * 
 * @param { Boolean }  
 */
function updateDraggablesStatus (isEnabled) {
    __draggablesList.forEach((draggable) => isEnabled ? draggable.enable() : draggable.disable()); 
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
 * Move the component back to its original spot or its new updated by sort spot
 */
function snapBackActiveComponent () {
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
 * @param { Element }  
 */
function onSnapBackActiveComponentComplete () {

    gsap.set(__activeComponent, { x: 0, y: 0, width: '100%' }); //reset the animation properties for another animate
    __activeComponent.style.position = 'static'; //was absolute, now sitting as it should
    __activeComponent.setAttribute('data-component', ''); //so it can be dragged again
    updateExistingComponentComputedStyles(); //ensure the defined colours are respected

    insertElementAfter(__placeHolderComponent, __activeComponent); //the element was taken out, so put it back where it now belongs
    __placeHolderComponent.remove(); //and now the placeholder can be pulled out

    expandExistingComponent();
    
}

/**
 * Expand out the existingComponent again now that's its back in place
 * The panels will animate regardless of whether they are opened or closed
 */
function expandExistingComponent () {

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
async function onActiveComponentExpanded () {
    if (__hasSortChange) {
        await saveComponentOrder(__activeComponent);
        __hasSortChange = false;
    }
    updateDocumentOnDragEnd();
    updateSortableElements();
    updateDraggablesStatus(true); //ensure all are enabled again once animation and form change is complete
}

/**
 * Return the document back to its pre onDragStart behaviour 
 */
function updateDocumentOnDragEnd () {
    document.body.style.overflowX = 'auto'; //was hidden to avoid horizontal scroll bars
    document.documentElement.style.scrollBehavior = 'smooth'; //was auto to avoid jarring behaviour 
    document.querySelectorAll('[data-collapsable]').forEach((element) => element.style.pointerEvents = 'auto'); //re-enable mouse interactions
    document.removeEventListener('scroll', onScroll); //no need to listen for scrolling until next drag
    window.dispatchEvent(new Event('resize')); //for components to respond to - for example swiper.js image gallerys to update to display correctly
}