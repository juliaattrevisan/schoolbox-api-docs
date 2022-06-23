import { gsap, Draggable, Flip } from 'gsap/all';
import { insertElementBefore, insertElementAfter, getClosestElement, isElementAboveOtherElement, 
         isElementBelowOtherElement, setStylesOnElement } from '@js/util/domUtils';
import { getNewComponent } from './getNewComponent';
import { saveComponentOrder } from '../existingComponents/saveComponentOrder';
import UpdateHomepageEvents from '../updateHomepageEvents';

gsap.registerPlugin(Draggable, Flip);

const COMPONENT_CONTAINERS_SELECTOR = '[data-components-container]'; //the top/left/right parent containers and the tabs container
const COMPONENT_SELECTOR = '[data-component-palette]'; //any component that will be dragged/added/sorted
const SORTABLE_SELECTOR = '[data-component]'; //existing components that can be sorted
const ANIMATE_SPEED = 0.15; //the speed of animations
const MIN_MOVEMENT = 50; //minimum amount of movement before we can FLIP again

let __draggablesList = []; //to contain the Draggable instances, to enable/disable depending on the #component-lock-switch status and add/remove when components added/removed
let __newComponent; //the component that's currently being dragged/sorted around
let __referenceElement; //the element that subs in for the __newComponent being sorted, so we know where it's going to be placed
let __isProcessing = false; //the onDrag can still fire if moving quickly even though the drag has been released, so need to add an extra check
let __hasNewComponent = false; //to know whether to update the DB with sort change
let __isScrollingTimeout = false; //to prevent sorting when moving a sortable up/down the window
let __isScrolling = false;
let __isFlipping = false; //prevent quick shifts / odd flickers while using FLIP
let __startDragPoint = { left: 0, top: 0 }; //to prevent rapid fire position changes, have to move a minimum amount prior to another change
let __newComponentClone; //to later insert back into the palette 
let __newComponentTabsContainer; //to reinsert the newComponentClone into the new component tabs to replace the one that was taken out


/*****************************************************************
 * 
 * INITIATION
 * 
 ****************************************************************/

/**
 * Fire everything up
 */
export function initAddNewComponents () {
    initListeners();
    initDraggables();
    initNewComponentPositions();
}

/**
 * To respond to any events that require action
 */
function initListeners () {
    initOnProcessingStartListener();
    initOnProcessingEndListener();
}

/**
 * Prevent rapid fire dragging while a data save is already taking place
 */
function initOnProcessingStartListener () {
    document.addEventListener(UpdateHomepageEvents.PROCESSING_START, (event) => {
        updateDraggablesStatus(false); //ensure all are disabled while process is active
    });
}

/**
 * Re-enable dragging once any processes (like sort saving) have taken place
 */
function initOnProcessingEndListener () {
    document.addEventListener(UpdateHomepageEvents.PROCESSING_END, (event) => {
        updateDraggablesStatus(true); //ensure all are enabled again once animation and form change is complete
    });
}

/**
 * Run through each Draggable instance and enable/disable to prevent rapid fire clicks
 * 
 * @param { Boolean }  
 */
function updateDraggablesStatus (isEnabled) {
    __draggablesList.forEach((draggable) => {
        isEnabled ? draggable.enable() : draggable.disable();
    }); 
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
        autoScroll: 1,
        onClick: function () {
            onNewComponentClicked(this);
        }, 
        onDragStart: function () {
            onDragStart(this);
        }, 
        onDrag: () => onDrag(),
        onDragEnd: () => onDragEnd(),
    })[0];
}

/**
 * To be able to sort elements and snap back to correct position
 * When sorting and snapping back, these points are used to animate the components movements
 */
function initNewComponentPositions () {
    document.querySelectorAll(COMPONENT_SELECTOR).forEach((component) => {
        component.dataset.originalLeft = component.getBoundingClientRect().left;
        component.dataset.originalTop = component.getBoundingClientRect().top + window.scrollY;
        component.dataset.leftDifference = 0;
        component.dataset.topDifference = 0;
        component.removeAttribute('data-flip-id'); //sometimes FLIP currently leaves this behind 
    });
}


/*****************************************************************
 * 
 * COMPONENT CLICKED
 * 
 ****************************************************************/

/**
 * Add in the new component to the top of the page
 * It animates from the new components tabs to the top of the 'top' container
 * Once it's in place we then move onto loading the new component
 * 
 * @param { Draggable }  
 */
function onNewComponentClicked (draggableInstance) {

    if (!__isProcessing) {

        __isProcessing = true;
        __hasNewComponent = true;
        __isFlipping = true;

        document.dispatchEvent(new Event(UpdateHomepageEvents.PROCESSING_START));
        
        updateNewComponentVars(draggableInstance.target); //use the parent container regardless of selection to ensure correct dimensions etc
        __referenceElement = getReferenceElement(); //to use a reference where the new component is / where it will be dragged to
        __referenceElement.removeAttribute('data-component'); //in this instance we don't want the reference element to animate
        disableAllOtherDraggables();
        extractNewComponent();

        const state = Flip.getState([document.querySelectorAll(COMPONENT_CONTAINERS_SELECTOR), document.querySelectorAll(SORTABLE_SELECTOR), __newComponent]);
        
        const topContainer = document.querySelector('[data-position="top"]');
        insertElementBefore(topContainer.firstChild, __newComponent); //add to the top of the container
        __newComponent.style.float = 'none'; //needs to be factored into the document flow, so take float off
        __newComponent.style.marginBottom = '2rem'; //add the appropriate margin in
        __newComponent.style.zIndex = 9999;
        __newComponent.style.position = 'static';
        __newComponent.style.width = '100%';

        Flip.from(state, {
            absolute: true, //to accurately animate elements
            duration: ANIMATE_SPEED * 2,
            ease: "power1.inOut",
            nested: true, //as Flip.getState() contains parents (COMPONENT_CONTAINERS_SELECTOR) and children (SORTABLE_SELECTOR)
            zIndex: 9999, //ensure it stays on top
            onComplete: () => {
                __isFlipping = false;
                reinsertNewComponentButton();
                __referenceElement.remove(); //and now the placeholder can be pulled out
                addNewComponent();
            },
        });

    }

}

/**
 * A new component has been clicked or started drag, so update the newComponent variables for 
 * use through new component creation / dragging around
 * 
 * @param { Element }  
 */
 function updateNewComponentVars (newComponent) {
    __newComponent = newComponent; //use the parent container regardless of selection to ensure correct dimensions etc
    __newComponentClone = __newComponent.cloneNode(true); //to replace the new component in the tabs when/if the __newComponent has been added in
    __newComponentClone.style.opacity = 0.5; //so the __newComponent stands out when animating from the component tabs
    __newComponentTabsContainer = __newComponent.parentElement; //so can put a copy of the __newComponent back into the palette once the addition is complete
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
 * 
 * @param { Draggable }
 */
function onDragStart (draggableInstance) {
    if (!__isProcessing) {
        __isProcessing = true;
        updateNewComponentVars(draggableInstance.target); //use the parent container regardless of selection to ensure correct dimensions etc
        __startDragPoint = updateDragPoint(); //to track when we can allow sorting check based on MIN_MOVEMENT
        __referenceElement = getReferenceElement(); //to use a reference where the new component is / where it will be dragged to
        updateDocumentOnDragStart();
        disableAllOtherDraggables();
        extractNewComponent();
    }
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
    document.body.appendChild(__newComponent);
    __newComponent.removeAttribute('data-component'); //so there's no position checks when dragging, as the placeholder is there instead
    setStylesOnElement(__newComponent, { //Update position and styles to match now that the __newComponent has been moved out of context
        position: 'absolute',
        top: `${__referenceElement.getBoundingClientRect().top + window.scrollY}px`,
        left: `${__referenceElement.getBoundingClientRect().left}px`, //subtracting 16 to deal with 1rem padding-left that's applied
        width: `${__referenceElement.getBoundingClientRect().width}px`, //and adding 32 to also deal with 1rem padding-left, padding-right that's applied each side
        transform: 'translate3d(0, 0, 0)',
    });
}



/*****************************************************************
 * 
 * DRAGGING
 * 
 ****************************************************************/

/**
 * Constantly check the new components position while dragging
 * Moving it onto the page or back into the tabs based on the drag position
 */
function onDrag () {
    if (canCheckForChange() && isNewComponentOverPage()) { //add the component to the page
        checkNewDraggedComponentOnPage();
    } else if (canCheckForChange() && __hasNewComponent) { //if the new component has been added to page already, but it back into the tabs
        putDraggedComponentBackIntoTabs();
    }
}

/**
 * Don't be checking while another drag is already occuring, have hardly dragged, 
 * while the window is scrolling or a FLIP is taking place
 * 
 * @returns { Boolean } 
 */
function canCheckForChange () {
    const currentDragPoint = { 
        left: __newComponent.getBoundingClientRect().left,
        top: __newComponent.getBoundingClientRect().top + window.scrollY
    }
    const distance = Math.sqrt(Math.pow(__startDragPoint.left - currentDragPoint.left, 2) + Math.pow(__startDragPoint.top - currentDragPoint.top, 2));
    return distance > MIN_MOVEMENT && __isProcessing && !__isScrolling && !__isFlipping;
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
 *  The component is being dragged over the page, so show where it will be currently placed 
 *  relative to where it has been dragged. And if it's been moved closer to a new element 
 *  animate the position change.
 */
function checkNewDraggedComponentOnPage () {
    const sortablesToCheckList = Array.from(document.querySelectorAll(SORTABLE_SELECTOR)); //grab all sortable elements 
    const closestElement = getClosestElement(__newComponent, sortablesToCheckList); //the element nearest to the dragged element
    if (closestElement.id !== __newComponent.id && isElementAboveOtherElement(__newComponent, closestElement)) { //no need to FLIP if the closest element is still the __newComponent
        updateDraggedComponentOnPage(closestElement, true);
    } else if (closestElement.id !== __newComponent.id && isElementBelowOtherElement(__newComponent, closestElement)) {
        updateDraggedComponentOnPage(closestElement, false);
    }
}

/**
 * Was on the page, but has been dragged back into the tabs area, so put it back in
 */
function putDraggedComponentBackIntoTabs () {

    __hasNewComponent = false;
    __isFlipping = true;
    __startDragPoint = updateDragPoint();

    const state = Flip.getState(document.querySelectorAll(SORTABLE_SELECTOR));
    
    __newComponentTabsContainer.appendChild(__referenceElement);
    
    __referenceElement.style.marginBottom = 0; //a margin may have been placed if has been moved onto the page already
    __newComponent.style.marginBottom = 0; //a margin may have been placed if has been moved onto the page already

    document.dispatchEvent(new Event(UpdateHomepageEvents.NEW_COMPONENT_SORT_UPDATE)); 

    __newComponent.dataset.leftDifference = __referenceElement.getBoundingClientRect().left - parseInt(__referenceElement.dataset.originalLeft); //so can animate the dragged element to this new position on mouse release
    __newComponent.dataset.topDifference = (__referenceElement.getBoundingClientRect().top + window.scrollY) - parseInt(__referenceElement.dataset.originalTop);
    gsap.to(__newComponent, { width: __referenceElement.parentElement.offsetWidth, duration: ANIMATE_SPEED } ); //parentElement as the placeHolder may not have fully shifted into place before mouse release})

    animateFlip(state);

}

/**
 * Use Flip to add the new compoenent to the page or move it to a different spot
 * 
 * @param { Element }  
 * @param { Boolean }  
 */
function updateDraggedComponentOnPage (closestElement, isShiftingDown) {
    
    __hasNewComponent = true;
    __isFlipping = true;
    __startDragPoint = updateDragPoint();

    const state = Flip.getState(document.querySelectorAll(SORTABLE_SELECTOR));
    
    isShiftingDown ? insertElementAfter(closestElement, __referenceElement) : insertElementBefore(closestElement, __referenceElement); //insert into the appropriate spot depending on drag position

    __referenceElement.style.marginBottom = '2rem'; //existing components all have a margin
    __newComponent.style.marginBottom = '2rem'; //existing components all have a margin

    clearFlipElements(); //check for flip bug

    document.dispatchEvent(new Event(UpdateHomepageEvents.NEW_COMPONENT_SORT_UPDATE)); 

    __newComponent.dataset.leftDifference = __referenceElement.getBoundingClientRect().left - parseInt(__referenceElement.dataset.originalLeft); //so can animate the dragged element to this new position on mouse release
    __newComponent.dataset.topDifference = (__referenceElement.getBoundingClientRect().top + window.scrollY) - parseInt(__referenceElement.dataset.originalTop);
    
    gsap.to(__newComponent, { width: __referenceElement.parentElement.offsetWidth, duration: ANIMATE_SPEED } ); //parentElement as the placeHolder may not have fully shifted into place before mouse release})

    animateFlip(state);
    
}

/**
 * Flip currently sometimes leaves a left over element while dragging between elements. It's cleared on mouse release, but we need 
 * it to be cleared sooner as we need to check for whether we need to add place holders after a component shift
 */
function clearFlipElements () {
    document.querySelectorAll(COMPONENT_CONTAINERS_SELECTOR).forEach((parentContainer) => {
        if (parentContainer.children.length === 1 && !parentContainer.children[0].id) { //there's the flip element here, so we should clear it out
            parentContainer.removeChild(parentContainer.children[0]);
        }
    });
}

/**
 * Show either the new component being added to the page, or being put back into the tabs
 * 
 * @param { FlipState }
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


/*****************************************************************
 * 
 * END DRAG
 * 
 ****************************************************************/

/**
 * Just snap back to where it now belongs
 */
function onDragEnd () {
    document.dispatchEvent(new Event(UpdateHomepageEvents.PROCESSING_START));
    if (isNewComponentOverPage()) { //add the component to the page
        checkNewDraggedComponentOnPage();
    } else if (__hasNewComponent) { //if the new component has been added to page already, but it back into the tabs
        putDraggedComponentBackIntoTabs();
    }
    if (__hasNewComponent) {
        reinsertNewComponentButton(); //ensure there's no shifts in the tabs with the new component taken out, adding the clone in place straight away
    }
    alignNewComponent();
}

/**
 * Move the component back to its original spot or its new position on the page
 */
function alignNewComponent () {
    gsap.to(__newComponent, {
        x: 0 + parseInt(__newComponent.dataset.leftDifference), //to accommodate for potential position changes that have occured
        y: 0 + parseInt(__newComponent.dataset.topDifference),
        width: __referenceElement.parentElement.offsetWidth, //parentElement as the placeHolder may not have fully shifted into place before mouse release
        duration: ANIMATE_SPEED,
        overwrite: true,
        onComplete: () => onAlignNewComponentComplete()
    });
}

/**
 * Tidy up once the element has snapped back 
 * setting up for another drag, returning various variables back to there pre dragging state etc
 */
function onAlignNewComponentComplete () {
    resetNewComponent();
    updateDocumentOnDragEnd();
    // updateSortableElements();
    if (__hasNewComponent) {
        addNewComponent();
    } else {
        document.dispatchEvent(new Event(UpdateHomepageEvents.PROCESSING_END));
        __isProcessing = false;
    }
}

/**
 * Return the new component element back to its pre dragging state
 */
function resetNewComponent () {
    gsap.set(__newComponent, { x: 0, y: 0, width: '100%' }); //reset the animation properties for another animate
    __newComponent.style.position = ''; //was absolute, now sitting as it should
    __newComponent.style.transform = ''; //ensure the dragging movement is reset
    __newComponent.style.left = ''; //ensure the dragging movement is reset
    __newComponent.style.top = ''; //ensure the dragging movement is reset
    insertElementAfter(__referenceElement, __newComponent); //the element was taken out, so put it back where it now belongs
    __referenceElement.remove(); //and now the placeholder can be pulled out
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

/**
 * To set up for another possible drag of elements
 */
async function addNewComponent () {
    updateDraggablesWithNewComponentButton();
    const newComponent = await getNewComponent(__newComponent);
    document.dispatchEvent(new CustomEvent(UpdateHomepageEvents.COMPONENT_ADDED, { detail: { component: newComponent } } ) );
    await gsap.to(newComponent, { //fade in the updated component
        duration: ANIMATE_SPEED,
        opacity: 1, 
        startAt: { opacity: 0 },
    } );
    await saveComponentOrder(newComponent);
    document.dispatchEvent(new Event(UpdateHomepageEvents.PROCESSING_END));
    __hasNewComponent = false;
    __isProcessing = false;
}

/**
 * Reinsert the cloned new component button into the new component tabs 
 * 
 * @todo - this should be the reference element not this __newComponentClone
 */
function reinsertNewComponentButton () {
    __newComponentTabsContainer.appendChild(__newComponentClone); 
    __newComponentClone.style.zIndex = null; //ensure there's no z-index issues, as it was boosted when dragging took place
}

/**
 * The Draggable instance was on the element that's now been inserted into the page
 * so we need to tidy that up and then add a new Draggable instance to the new component button thats back in the tabs
 */
function updateDraggablesWithNewComponentButton () {
    const draggableToRemove = __draggablesList.find((draggable) => draggable.target !== __newComponent);
    draggableToRemove.kill();
    __draggablesList = __draggablesList.filter((draggable) => draggable.target !== __newComponent);
    const newDraggable = addNewDraggable(__newComponentClone);
    __draggablesList = [...__draggablesList, newDraggable];
}