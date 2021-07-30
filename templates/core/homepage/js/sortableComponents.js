/** 
 * To be able to drag/sort existing homepage components around 
 *
 */

import { gsap, Draggable, Flip } from 'gsap/all';
import axios from 'axios';
import { insertElementBefore, insertElementAfter, getClosestElement, isElementAboveOtherElement, 
         isElementBelowOtherElement, setStylesOnElement } from '@js/util/domUtils';

gsap.registerPlugin(Draggable, Flip);

const sortablesContainersSelector = '[data-components-container]'; //the top/left/right parent containers
const sortablesSelector = '[data-component]'; //any component that will be sorted
const sortablesTriggerSelector = '[data-component-handle]'; //the top bar of the component that will be used as the drag trigger
const ANIMATE_SPEED = 0.3; //the speed of animations
const MIN_MOVEMENT = 50; //minimum amount of movement before we can FLIP again

let draggablesList = []; //to contain the Draggable instances, to enable/disable depending on the #component-lock-switch status and add/remove when components added/removed
let placeHolderComponentsList = [];
let activeElement; //the element that's currently being dragged/sorted around
let placeHolderElement; //the element that subs in for the activeElement being sorted
let isScrollingTimeout = false; //to prevent sorting when moving a sortable up/down the window
let isScrolling = false;
let isFlipping = false; //prevent quick shifts / odd flickers while using FLIP
let isChangingSize = false; //if required, wait for the element to shrink down to just the top bar before dragging as some elements are too long to move around effectively
let startDragPoint = { left: 0, top: 0 }; //to prevent rapid fire position changes, have to move a minimum amount prior to another change


/**
 * Fire up Draggable instances, looping through each sortable to assign a Draggable instance 
 */
const initDraggables = () => {
    document.querySelectorAll(sortablesSelector).forEach((sortableItem) => {
        draggablesList = [...draggablesList, getDraggable(sortableItem)];
    });
    draggablesList.forEach((draggable) => draggable.disable()); //disabled at start until the #component-lock-switch is toggled
}

/**
 * To enable Dragging functionality to the chosen item
 * Refer to https://greensock.com/docs/v3/Plugins/Draggable for further info
 */
 const getDraggable = (sortableItem) => {
    return Draggable.create(sortableItem, {
        type: 'x, y',
        trigger: sortableItem.querySelector(sortablesTriggerSelector),
        autoScroll: 1,
        onDragStart: (event) => onDragStart(event),
        onDrag: () => onDrag(),
        onDragEnd: () => onDragEnd(),
    })[0];
}

/**
 * To respond to any events that require action
 */
 const initListeners = () => {
    initComponentLockSwitchListener();
    initOnComponentAddedListener();
    initOnComponentRemovedListener();
}

/**
 * Listen to changes and enable/disable the sorting based on the status of #component-lock-switch
 */
const initComponentLockSwitchListener = () => {
    const componentLockSwitch = document.getElementById('component-lock-switch').querySelector('input');
    componentLockSwitch.addEventListener('change', (event) => updateDraggablesStatus(event.target.checked));
}

/**
 * Run through each Draggable instance and enable/disable
 * 
 * @param { Boolean }  
 */
 const updateDraggablesStatus = (enable) => {
    if (enable) {
        draggablesList.forEach((draggable) => draggable.enable()); 
    } else {
        draggablesList.forEach((draggable) => draggable.disable());
    }
}

/**
 * To add a newly created component to the sortable list
 */
 const initOnComponentAddedListener = () => {
    document.addEventListener('jquery.masonry__onComponentAdded', (event) => {
        const newDraggable = getDraggable(document.getElementById(event.detail.componentID));
        const componentLockSwitchStatus = document.getElementById('component-lock-switch').querySelector('input').checked; //check the status of the input to know whether the new sortable should be active
        newDraggable.enabled(componentLockSwitchStatus); //check 
        draggablesList = [...draggablesList, newDraggable]; //add the component into the Draggable instances list
        updateSortableElements(); //update the position of all elements due to the new element that's been added
    });
}

/**
 * To remove a component to the sortable list
 */
 const initOnComponentRemovedListener = () => {
    document.addEventListener('jquery.masonry__onComponentRemoved', (event) => {
        const draggableToRemove = draggablesList.find((draggable) => getComponentID(draggable.target) === event.detail.componentID);
        draggableToRemove.kill(); //disables the Draggable instance
        draggablesList = draggablesList.filter((draggable) => getComponentID(draggable.target) !== event.detail.componentID); //update the draggablesList list, pullling out the removed component
        updateSortableElements(); //update the position of all elements due to the new element being added
    });
}

/**
 * Sub in a placeholder to use in place of the element being dragged, 
 * which gets pulled out so it can go into any other parent container 
 * that contains the sortablesSelector
 */
const onDragStart = (event) => {
    activeElement = event.target.closest(sortablesSelector); //use the parent container regardless of selection to ensure correct dimensions etc
    placeHolderElement = getPlaceholderElement();
    startDragPoint = updateDragPoint(); //to track when we can allow sorting check based on MIN_MOVEMENT
    updateDocumentForDrag();
    disableAllOtherDraggables();
    extractActiveElement();
    updateSortableElements(); //ensure we have the most up to date positions
    collapseActiveElementContent();
}

/**
 * Create a clone of the activeElement being dragged to use as a placeholder 
 * and to keep it in position as the activeElement will be pulled out of context
 * 
 * @param { DOMElement }  
 * 
 * @returns { DOMElement }  
 */
 const getPlaceholderElement = () => {
    const cloneElement = activeElement.cloneNode(true);
    cloneElement.querySelector('[data-collapsable]').remove(); //just use the top bar as reference
    cloneElement.style.opacity = 0.5; //displayed as reference where the activeElement is currently placed 
    insertElementBefore(activeElement, cloneElement);
    return cloneElement;
}

/**
 * To track drag movement
 * 
 * @returns { left, top } 
 */
 const updateDragPoint = () => {
    return { 
        left: activeElement.getBoundingClientRect().left,
        top: activeElement.getBoundingClientRect().top + window.scrollY
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
    isScrolling = true;
    window.clearTimeout(isScrollingTimeout); // Clear our timeout throughout the scroll
    isScrollingTimeout = setTimeout(() => { // Set a timeout to run after scrolling ends
        isScrolling = false; 
    }, 66); 
}

/**
 * Disable all others until current drag is finished to avoid rapid fire / double click issues
 */
 const disableAllOtherDraggables = () => {
    draggablesList.forEach((draggable) => { 
        if (draggable.target.id !== activeElement.id) { //to avoid disabling the current
            draggable.disable();
        }
    }); 
}

/**
 * With the placeHolderElement in place, the activeElement is pulled out
 * so it doesn't impact our ability to sort through the items and can go into any container
 * 
 * @param { DOMElement }  
 */
 const extractActiveElement = () => {
    document.body.appendChild(activeElement);
    activeElement.removeAttribute('data-component'); //so there's no position checks when dragging, as the placeholder is there instead
    setStylesOnElement(activeElement, { //Update position and styles to match now that the activeElement has been moved out of context
        position: 'absolute',
        top: `${placeHolderElement.getBoundingClientRect().top + window.scrollY}px`,
        left: `${placeHolderElement.getBoundingClientRect().left - 16 }px`, //subtracting 16 to deal with 1rem padding-left that's applied
        width: `${placeHolderElement.getBoundingClientRect().width + 32 }px`, //and adding 32 to also deal with 1rem padding-left, padding-right that's applied each side
    });
    updateActiveElementComputedStyles();
}

/**
 * Ensure custom set colour vars are respected and set when moving between parent containers
 */
 const updateActiveElementComputedStyles = () => {

    const containerPosition = placeHolderElement.parentElement.dataset.position; //to ensure the correct colours are used relative to which parent container the element belongs to

    const headBg = getComputedStyle(placeHolderElement).getPropertyValue(`--${containerPosition}-head-bg`); //use placeHolderElement as it still exists in the correct context
    activeElement.querySelectorAll('.component-titlebar').forEach((item) => item.style.backgroundColor = headBg );

    const headFont = getComputedStyle(placeHolderElement).getPropertyValue(`--${containerPosition}-head-font`);
    activeElement.querySelectorAll('.component-titlebar h2').forEach((item) => item.style.color = headFont );
    activeElement.querySelectorAll('.component-titlebar a').forEach((item) => item.style.color = headFont );

}

/**
 * Shrink down the element to the top bar, which is used to sort to 
 * avoid trying to sort tall height elements
 */
 const collapseActiveElementContent = () => {
    isChangingSize = true;
    const activeElementContent = activeElement.querySelector('[data-collapsable]');
    gsap.to(activeElementContent, { 
        onStart: () => activeElementContent.style.overflow = 'hidden',
        height: 0, 
        duration: ANIMATE_SPEED,
        overwrite: true,
        onComplete: () => {
            isChangingSize = false;
            addPlaceHolderComponents();
            hideExistingMasonryPlaceHolders();
        }
    } );
}

/**
 * need to run through and check each parent container for children
 * if there's no children need to show that items can be dragged into this container
 */
 const initPlaceHolderComponents = () => {
    document.querySelectorAll(sortablesContainersSelector).forEach(() => {
        const placeHolderComponent = document.createElement('div');
        placeHolderComponent.classList.add('masonry-placeholder');
        placeHolderComponent.style.display = 'block';
        placeHolderComponent.style.height = '51px';
        placeHolderComponent.dataset.component = 'true';
        placeHolderComponent.dataset.placeHolderComponent = 'true';
        placeHolderComponent.innerText = 'You can drag components into this space';
        placeHolderComponentsList = [...placeHolderComponentsList, placeHolderComponent];
    });
}

/**
 * need to run through and check each parent container for children
 * if there's no children need to show that items can be dragged into this container
 */
 const addPlaceHolderComponents = () => {
    document.querySelectorAll(sortablesContainersSelector).forEach((parentContainer, index) => {
        if (canAddPlaceHolderComponent(parentContainer)) { //something going on in here.. with FLIP
            const placeHolderComponent = placeHolderComponentsList[index];
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
    document.querySelectorAll(sortablesSelector).forEach((sortableItem) => {
        sortableItem.dataset.originalLeft = sortableItem.getBoundingClientRect().left;
        sortableItem.dataset.originalTop = sortableItem.getBoundingClientRect().top + window.scrollY;
        sortableItem.dataset.leftDifference = 0;
        sortableItem.dataset.topDifference = 0;
    });
}

/**
 * Constantly check for the nearest other sortable and update position 
 * based on whether a move is valid
 */
const onDrag = () => {
    if (canCheckForChange()) { 
        const sortablesToCheckList = Array.from(document.querySelectorAll(sortablesSelector)); //grab all sortable elements 
        const closestElement = getClosestElement(activeElement, sortablesToCheckList); //the element nearest to the dragged element
        if (closestElement && closestElement.id !== activeElement.id) { //no need to FLIP if the closest element is still the activeElement
            if (isElementAboveOtherElement(activeElement, closestElement)) { //otherwise determine movement direction, up or down into the container
                flipState(closestElement, true);
            } else if (isElementBelowOtherElement(activeElement, closestElement)) {
                flipState(closestElement, false);
            }
        }
    }
}

/**
 *  Don't be checking while have hardly dragged, while the window is scrolling or while the activeElement is still shrinking in collapseActiveElementContent()
 * 
 * @retruns { Boolean } 
 */
const canCheckForChange = () => {
    const currentDragPoint = { 
        left: activeElement.getBoundingClientRect().left,
        top: activeElement.getBoundingClientRect().top + window.scrollY
    }
    const distance = Math.sqrt(Math.pow(startDragPoint.left - currentDragPoint.left, 2) + Math.pow(startDragPoint.top - currentDragPoint.top, 2));
    return distance > MIN_MOVEMENT && !isScrolling && !isChangingSize;
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
    
    if (!isFlipping) { //don't allow multiple FLIPs to prevent quick shifts / weird jarring behaviour

        isFlipping = true;
        startDragPoint = updateDragPoint();

        const state = Flip.getState(document.querySelectorAll(sortablesSelector));
        
        if (isShiftingDown) {
            insertElementAfter(closestElement, placeHolderElement);
        } else {
            insertElementBefore(closestElement, placeHolderElement);
        }

        removePlaceHolderComponents(); //remove prior to recording the new co-ordinates so can snap back on release to correct location
        
        activeElement.dataset.leftDifference = placeHolderElement.getBoundingClientRect().left - parseInt(placeHolderElement.dataset.originalLeft); //so can animate the dragged element to this new position on mouse release
        activeElement.dataset.topDifference = (placeHolderElement.getBoundingClientRect().top + window.scrollY) - parseInt(placeHolderElement.dataset.originalTop);
        gsap.to(activeElement, { width: placeHolderElement.parentElement.offsetWidth, duration: ANIMATE_SPEED } ); //parentElement as the placeHolder may not have fully shifted into place before mouse release})

        addPlaceHolderComponents(); //now check if need to add them back in

        Flip.from(state, {
            duration: ANIMATE_SPEED,
            onComplete: () => {
                isFlipping = false;
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

/**
 * Just snap back to where it now belongs
 */
 const onDragEnd = () => {
    removePlaceHolderComponents();
    showExistingMasonryPlaceHolders();
    updateDraggablesStatus(false); //prevent quick reclicks on elements
    gsap.to(activeElement, {
        x: 0 + parseInt(activeElement.dataset.leftDifference), //to accommodate for potential position changes that have occured
        y: 0 + parseInt(activeElement.dataset.topDifference),
        width: placeHolderElement.parentElement.offsetWidth, //parentElement as the placeHolder may not have fully shifted into place before mouse release
        duration: ANIMATE_SPEED,
        overwrite: true,
        onComplete: () => onSnapActiveElementBackComplete()
    });
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
 * Tidy up once the element has snapped back 
 * setting up for another drag, returning various variables back to there pre dragging state etc
 * 
 * @param { DOMElement }  
 */
 const onSnapActiveElementBackComplete = () => {

    gsap.set(activeElement, { x: 0, y: 0, width: '100%' }); //reset the animation properties for another animate
    activeElement.style.position = 'static'; //was absolute, now sitting as it should
    activeElement.setAttribute('data-component', ''); //so it can be dragged again
    updateActiveElementComputedStyles(); //ensure the defined colours are respected

    document.querySelectorAll('[data-collapsable]').forEach((element) => element.style.pointerEvents = 'auto'); //re-enable mouse interactions

    insertElementAfter(placeHolderElement, activeElement); //the element was taken out, so put it back where it now belongs
    placeHolderElement.remove(); //and now the placeholder can be pulled out

    const activeElementContent = activeElement.querySelector('[data-collapsable]');
    gsap.to(activeElementContent, { 
        height: 'auto', 
        duration: ANIMATE_SPEED, 
        overwrite: true, 
        onComplete: () => onActiveElementExpanded(activeElementContent)
    } ); 

}

/**
 * To set up for another possible drag of elements
 */
 const onActiveElementExpanded = (activeElementContent) => {

    activeElementContent.style.overflow = 'visible';

    document.body.style.overflowX = 'auto'; //was hidden to avoid horizontal scroll bars
    document.documentElement.style.scrollBehavior = 'smooth'; //was auto to avoid jarring behaviour 
    document.removeEventListener('scroll', onScroll); //no need to listen for scrolling until next drag
    window.dispatchEvent(new Event('resize')); //for components to respond to - for example swiper.js image gallerys to update to display correctly
    
    updateSortableElements();
    submitNewComponentOrder();
    updateDraggablesStatus(true); //ensure all are enabled again once animation is complete

}

/**
 * Submit the new component order to the DB
 */
const submitNewComponentOrder = async () => {
    try {
        await axios.post('/homepage/configForm.php', getNewComponentOrderFormData());
    } catch (error) {
        console.warn(error);
    } 
}

/**
 * To pass through the required FormData to save the new position in the DB
 * 
 * @returns { FormData }  
 */
const getNewComponentOrderFormData = () => {
    const formData = new FormData();
    formData.append('column1', getContainerComponentIDs(document.querySelector('[data-position="left"]'))); //left side panel
    formData.append('column2', getContainerComponentIDs(document.querySelector('[data-position="right"]'))); //right side panel
    formData.append('column3', getContainerComponentIDs(document.querySelector('[data-position="top"]'))); //top panel... I know right?!
    formData.append('movedComponentId', getComponentID(activeElement));
    formData.append('type', 'components');
    formData.append('cid', document.getElementById('categoryID').value);
    return formData;
}

/**
 * To retrieve all component ids within a container
 * 
 * @param { DOMElement } 
 * 
 * @returns { String } 
 */
const getContainerComponentIDs = (componentContainer) => {
    let containerComponentIDList = []; //add all ids into an array
    componentContainer.querySelectorAll(sortablesSelector).forEach((sortable) => {
        if (sortable.id) { //the <div class="masonry-placeholder" /> exists in each parent container
            containerComponentIDList.push(getComponentID(sortable));
        }
    });
    return containerComponentIDList.join(','); //return just a string from the array
}

/**
 * To retrieve the component id
 * 
 * @param { DOMElement } 
 * 
 * @returns { String } 
 */
const getComponentID = (componentElement) => {
    return componentElement.id.replace('component', ''); //strip the 'component' at the start to just get the id number
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
