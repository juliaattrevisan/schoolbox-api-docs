import { gsap, Draggable, Flip } from 'gsap/all';
import { insertElementBefore, insertElementAfter, getClosestElement, isElementAboveOtherElement, 
         isElementBelowOtherElement, setStylesOnElement } from '@js/util/domUtils';
import { getNewComponent } from './getNewComponent';
import { saveComponentOrder } from '../existingComponents/saveComponentOrder';
import { ADD_PLACE_HOLDERS, REMOVE_PLACE_HOLDERS } from '../updateHomepage';
import { animate } from 'highcharts';

gsap.registerPlugin(Draggable, Flip);

const COMPONENT_CONTAINERS_SELECTOR = '[data-components-container]'; //the top/left/right parent containers and the tabs container
const COMPONENT_SELECTOR = '[data-component-palette]'; //any component that will be dragged/added/sorted
const SORTABLE_SELECTOR = '[data-component]'; //existing components that can be sorted
const ANIMATE_SPEED = 0.3; //the speed of animations
const MIN_MOVEMENT = 50; //minimum amount of movement before we can FLIP again

let __draggablesList = []; //to contain the Draggable instances, to enable/disable depending on the #component-lock-switch status and add/remove when components added/removed
let __newComponent; //the component that's currently being dragged/sorted around
let __referenceElement; //the element that subs in for the __newComponent being sorted, so we know where it's going to be placed
let __hasNewComponent = false; //to know whether to update the DB with sort change
let __isScrollingTimeout = false; //to prevent sorting when moving a sortable up/down the window
let __isScrolling = false;
let __isFlipping = false; //prevent quick shifts / odd flickers while using FLIP
let __startDragPoint = { left: 0, top: 0 }; //to prevent rapid fire position changes, have to move a minimum amount prior to another change
let __newComponentClone; //to later insert back into the palette 
let __newComponentPaletteContainer; //to reset the newComponent back to its base


/*****************************************************************
 * 
 * INITIATION
 * 
 ****************************************************************/

/**
 * Fire everything up
 */
export function initAddNewComponents () {
    initDraggables();
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
 * To enable Dragging functionality to the chosen item
 * Refer to https://greensock.com/docs/v3/Plugins/Draggable for further info
 * 
 * @returns { Draggable }
 */
function addNewDraggable (component) {
    component.addEventListener('click', (event) => event.preventDefault());
    component.addEventListener('dblclick', (event) => event.preventDefault());
    return Draggable.create(component, {
        type: 'x, y',
        trigger: component,
        autoScroll: 1,
        onDragStart: (event) => onDragStart(event),
        onDrag: () => onDrag(),
        onDragEnd: () => onDragEnd(),
    })[0];
}

/**
 * Run through each Draggable instance and enable/disable
 * 
 * @param { Boolean }  
 */
function updateDraggablesStatus (isEnabled) {
    __draggablesList.forEach((draggable) => isEnabled ? draggable.enable() : draggable.disable()); 
}



/*****************************************************************
 * 
 * START DRAG
 * 
 ****************************************************************/

/**
 * Sub in a referenceElement to use in place of the element being dragged, 
 * which gets pulled out so it can go into any other parent container 
 * that contains the COMPONENT_SELECTOR
 */
function onDragStart (event) {
    __newComponent = event.target.closest(COMPONENT_SELECTOR); //use the parent container regardless of selection to ensure correct dimensions etc
    __newComponentClone = __newComponent.cloneNode(true);
    __referenceElement = getReferenceElement();
    __startDragPoint = updateDragPoint(); //to track when we can allow sorting check based on MIN_MOVEMENT
    updateDocumentOnDragStart();
    disableAllOtherDraggables();
    extractNewComponent();
    updateSortableElements(); //ensure we have the most up to date positions
    document.dispatchEvent(new Event(ADD_PLACE_HOLDERS));
}

/**
 * Create a clone of the __newComponent being dragged to use as a placeholder 
 * and to keep it in position as the __newComponent will be pulled out of context
 * 
 * @returns { Element }  
 */
function getReferenceElement () {
    const referenceElement = __newComponent.cloneNode(true);
    referenceElement.setAttribute('data-component', ''); //so it can be considered when sorting
    referenceElement.style.opacity = 0.5; //displayed as reference where the __newComponent is currently placed 
    insertElementBefore(__newComponent, referenceElement);
    return referenceElement;
}

/**
 * To track drag movement
 * 
 * @returns { left, top } 
 */
function updateDragPoint () {
    return { 
        left: __newComponent.getBoundingClientRect().left,
        top: __newComponent.getBoundingClientRect().top + window.scrollY
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
        if (draggable.target.id !== __newComponent.id) { //to avoid disabling the current
            draggable.disable();
        }
    }); 
}

/**
 * With the __referenceElement in place, the __newComponent is pulled out
 * so it doesn't impact our ability to sort through the items and can go into any container
 */
function extractNewComponent () {
    __newComponentPaletteContainer = __newComponent.parentElement; //so can put a copy of the __newComponent back into the palette once the addition is complete
    document.body.appendChild(__newComponent);
    __newComponent.removeAttribute('data-component'); //so there's no position checks when dragging, as the placeholder is there instead
    setStylesOnElement(__newComponent, { //Update position and styles to match now that the __newComponent has been moved out of context
        position: 'absolute',
        top: `${__referenceElement.getBoundingClientRect().top + window.scrollY}px`,
        left: `${__referenceElement.getBoundingClientRect().left}px`, //subtracting 16 to deal with 1rem padding-left that's applied
        width: `${__referenceElement.getBoundingClientRect().width}px`, //and adding 32 to also deal with 1rem padding-left, padding-right that's applied each side
    });
}

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
    if (canCheckForChange() && isNewComponentOverPage()) { //add the component to the page
        showNewComponentOnPage();
    } else if (canCheckForChange() && __hasNewComponent) { //if the new component has been added to page already, but it back into the tabs
        putComponentBackIntoTabs();
    }
}

/**
 *  Don't be checking while have hardly dragged or while the window is scrolling
 * 
 * @returns { Boolean } 
 */
function canCheckForChange () {
    const currentDragPoint = { 
        left: __newComponent.getBoundingClientRect().left,
        top: __newComponent.getBoundingClientRect().top + window.scrollY
    }
    const distance = Math.sqrt(Math.pow(__startDragPoint.left - currentDragPoint.left, 2) + Math.pow(__startDragPoint.top - currentDragPoint.top, 2));
    return distance > MIN_MOVEMENT && !__isScrolling && !__isFlipping;
}

/**
 *  Has the new component been dragged into the new page area?
 *  Or is it in around the tabs area - so it wouldn't be added on mouse release
 * 
 * @returns { Boolean } 
 */
function isNewComponentOverPage () {
    const topContainer = document.querySelector('[data-position="top"]'); //the container thew new component is dragged from
    const topContainerTopPoint = topContainer.getBoundingClientRect().top + window.scrollY;
    const newComponentTopPoint = __newComponent.getBoundingClientRect().top + window.scrollY;
    return newComponentTopPoint > topContainerTopPoint; //the new component has been dragged below the tabs container into the page
}

/**
 * The component has been dragged over the page, so show where it will be current placed 
 * relative to where it has been dragged
 */
function showNewComponentOnPage () {
    const sortablesToCheckList = Array.from(document.querySelectorAll(SORTABLE_SELECTOR)); //grab all sortable elements 
    const closestElement = getClosestElement(__newComponent, sortablesToCheckList); //the element nearest to the dragged element
    if (closestElement.id !== __newComponent.id) { //no need to FLIP if the closest element is still the __newComponent
        if (isElementAboveOtherElement(__newComponent, closestElement)) { //otherwise determine movement direction, up or down into the container
            addNewComponentToPage(closestElement, true);
        } else if (isElementBelowOtherElement(__newComponent, closestElement)) {
            addNewComponentToPage(closestElement, false);
        }
    }
}

/**
 * Was on the page, but has been dragged back into the tabs area, so put it back in
 */
function putComponentBackIntoTabs () {

    __hasNewComponent = false;
    __isFlipping = true;
    __startDragPoint = updateDragPoint();

    const state = Flip.getState(document.querySelectorAll(SORTABLE_SELECTOR));
    
    __newComponentPaletteContainer.appendChild(__referenceElement);
    __referenceElement.style.marginBottom = 0; //a margin may have been placed if has been moved onto the page already
    __newComponent.style.marginBottom = 0; //a margin may have been placed if has been moved onto the page already
    
    animateFlip(state);

}

/**
 * Use Flip to add the new compoenent to the page or move it to a different spot
 * Refer to https://greensock.com/docs/v3/Plugins/Flip for more info on Flip
 * 
 * @param { Element }  
 * @param { Boolean }  
 */
function addNewComponentToPage (closestElement, isShiftingDown) {
    
    __hasNewComponent = true;
    __isFlipping = true;
    __startDragPoint = updateDragPoint();

    const state = Flip.getState(document.querySelectorAll(SORTABLE_SELECTOR));
    
    if (isShiftingDown) {
        insertElementAfter(closestElement, __referenceElement);
    } else {
        insertElementBefore(closestElement, __referenceElement);
    }

    __referenceElement.style.marginBottom = '2rem'; //existing components all have a margin
    __newComponent.style.marginBottom = '2rem'; //existing components all have a margin

    clearFlipElements(); //check for flip bug
    document.dispatchEvent(new Event(REMOVE_PLACE_HOLDERS)); //remove prior to recording the new co-ordinates so can snap back on release to correct location
    
    __newComponent.dataset.leftDifference = __referenceElement.getBoundingClientRect().left - parseInt(__referenceElement.dataset.originalLeft); //so can animate the dragged element to this new position on mouse release
    __newComponent.dataset.topDifference = (__referenceElement.getBoundingClientRect().top + window.scrollY) - parseInt(__referenceElement.dataset.originalTop);
    gsap.to(__newComponent, { width: __referenceElement.parentElement.offsetWidth, duration: ANIMATE_SPEED } ); //parentElement as the placeHolder may not have fully shifted into place before mouse release})

    document.dispatchEvent(new Event(ADD_PLACE_HOLDERS)); //now check if need to add them back in

    animateFlip(state);
    
}

/**
 * Show either the new component being added to the page, or being put back into the tabs
 */
function animateFlip (state) {
    Flip.from(state, {
        duration: ANIMATE_SPEED,
        onComplete: () => {
            __isFlipping = false;
            onDrag(); //check after Flip to see if another is required
        }
    });
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
    snapBackNewComponent();
}

/**
 * Move the component back to its original spot or its new position on the page
 */
function snapBackNewComponent () {
    gsap.to(__newComponent, {
        x: 0 + parseInt(__newComponent.dataset.leftDifference), //to accommodate for potential position changes that have occured
        y: 0 + parseInt(__newComponent.dataset.topDifference),
        width: __referenceElement.parentElement.offsetWidth, //parentElement as the placeHolder may not have fully shifted into place before mouse release
        duration: ANIMATE_SPEED,
        overwrite: true,
        onComplete: () => onSnapBackNewComponentComplete()
    });
}

/**
 * Tidy up once the element has snapped back 
 * setting up for another drag, returning various variables back to there pre dragging state etc
 * 
 * @param { Element }  
 */
function onSnapBackNewComponentComplete () {

    gsap.set(__newComponent, { x: 0, y: 0, width: '100%' }); //reset the animation properties for another animate
    __newComponent.style.position = 'static'; //was absolute, now sitting as it should

    insertElementAfter(__referenceElement, __newComponent); //the element was taken out, so put it back where it now belongs
    __referenceElement.remove(); //and now the placeholder can be pulled out

    updateDocumentOnDragEnd();
    updateSortableElements();
    addNewComponent();

}

/**
 * To set up for another possible drag of elements
 */
async function addNewComponent () {

    if (__hasNewComponent) {
        
        __newComponentPaletteContainer.appendChild(__newComponentClone);

        const newDraggable = addNewDraggable(__newComponentClone);
        const componentLockSwitchStatus = document.getElementById('component-lock-switch').querySelector('input').checked; //check the status of the input to know whether the new sortable should be active
        newDraggable.enabled(componentLockSwitchStatus); //check 
        __draggablesList = [...__draggablesList, newDraggable];
        //disable if only allowed one element

        const newComponent = await getNewComponent(__newComponent);
        await saveComponentOrder(newComponent);
        __hasNewComponent = false;
    } 

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
};