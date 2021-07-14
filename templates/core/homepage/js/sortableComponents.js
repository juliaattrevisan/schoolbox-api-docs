/** 
 * To be able to drag/sort existing homepage components around 
 *
 */

import { gsap, Draggable, Flip } from 'gsap/all';
import axios from 'axios';
import { insertElementBefore, insertElementAfter, getClosestElement, getElementsMidPoint, 
         isElementAboveOtherElement, isElementBelowOtherElement, setStylesOnElement } from '@js/util/domUtils';

gsap.registerPlugin(Draggable, Flip);

let draggablesList = []; //to contain the Draggable instances, to enable/disable depending on the #component-lock-switch status and add/remove when components added/removed
let activeElement; //the element that's currently being dragged/sorted around
let placeholderElement; //the element that subs in for the activeElement being sorted
let sortablesContainersSelector = '[data-components-container]'; //the top/left/right parent containers
let sortablesSelector = '[data-component]'; //any component that will be sorted
let sortablesTriggerSelector = '[data-component-titlebar-container]'; //the top bar of the component that will be used as the drag trigger
let isScrollingTimeout = false; //to prevent sorting when moving a sortable up/down the window
let isScrolling = false;


/**
 * Fire up Draggable instances, looping through each sortable to assign a Draggable instance 
 */
const initDraggables = () => {
    document.querySelectorAll(sortablesSelector).forEach((sortableItem) => {
        draggablesList.push(getDraggable(sortableItem));
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
 * To be able to sort elements and snap back to correct position
 */
 const updateSortableElements = () => {
    document.querySelectorAll(sortablesSelector).forEach((sortableItem, index) => {
        sortableItem.dataset.originalLeft = sortableItem.getBoundingClientRect().left;
        sortableItem.dataset.leftDifference = 0;
        sortableItem.dataset.originalTop = sortableItem.getBoundingClientRect().top + window.scrollY;
        sortableItem.dataset.topDifference = 0;
    });
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
 * Enable/disable the sorting based on the status of #component-lock-switch
 */
const initComponentLockSwitchListener = () => {
    const componentLockSwitch = document.getElementById('component-lock-switch').querySelector('input');
    componentLockSwitch.addEventListener('change', (event) => {
        if (event.target.checked) {
            draggablesList.forEach((draggable) => draggable.enable()); //run through each Draggable instance and enable
        } else {
            draggablesList.forEach((draggable) => draggable.disable());
        }
    });
}

/**
 * To add a newly added component to the sortable list
 */
 const initOnComponentAddedListener = () => {
    document.addEventListener('jquery.masonry__onComponentAdded', (event) => {
        const newDraggable = getDraggable(document.getElementById(event.detail.componentID));
        newDraggable.enabled(draggablesList[0].enabled()); //just base the active status on the first Draggable instance
        draggablesList.push(newDraggable); //add the component into the Draggable instances list
        updateSortableElements(); //update the position of all elements due to the new element being added
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
    
    window.addEventListener('scroll', onScroll); //prevent sorting check while moving up/down window
    
    document.body.style.overflowX = 'hidden'; //avoid horizontal scroll bars when a sortable gets moved beyond the window edges
    document.documentElement.style.scrollBehavior = 'auto'; //disable smooth scroll as it can jar while moving elements around 
    
    activeElement = event.target.closest(sortablesSelector); //use the parent container regardless of selection to ensure correct dimensions etc

    placeholderElement = getPlaceholderElement();
    placeholderElement.style.opacity = 0.25; //displayed as reference where the activeElement is currently placed 
    pullOutActiveElement();

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
 * Create a clone of the activeElement being dragged to use as a placeholder 
 * and to keep it in position as the activeElement will be pulled out of context
 * 
 * @param { DOMElement }  
 * 
 * @returns { DOMElement }  
 */
 const getPlaceholderElement = () => {
    const cloneElement = activeElement.cloneNode(true);
    insertElementBefore(activeElement, cloneElement);
    return cloneElement;
}

/**
 * With the placeholderElement in place, the activeElement is pulled 
 * out so it doesn't impact our ability to sort through the items and can go into any container
 * 
 * @param { DOMElement }  
 */
 const pullOutActiveElement = () => {
    document.body.appendChild(activeElement);
    activeElement.removeAttribute('data-component'); //so there's no position checks when dragging, as the placeholder is there instead
    setStylesOnElement(activeElement, { //Update position and styles to match now that the activeElement has been moved out of context
        position: 'absolute',
        top: `${placeholderElement.getBoundingClientRect().top + window.scrollY}px`,
        left: `${placeholderElement.getBoundingClientRect().left - 16 }px`, //subtracting 16 to deal with 1rem padding-left that's applied
        width: `${placeholderElement.getBoundingClientRect().width + 32 }px`, //and adding 32 to also deal with 1rem padding-left, padding-right that's applied each side
    });
    updateActiveElementComputedStyles();
}

/**
 * Ensure custom set colour vars are respected and set when moving parent
 */
const updateActiveElementComputedStyles = () => {

    const containerPosition = placeholderElement.parentElement.dataset.position; //to ensure the correct colours are used relative to which parent container the element belongs to

    const headBg = getComputedStyle(placeholderElement).getPropertyValue(`--${containerPosition}-head-bg`); //use placeholderElement as it still exists in the correct context
    activeElement.querySelectorAll('.component-titlebar').forEach((item) => item.style.backgroundColor = headBg );

    const headFont = getComputedStyle(placeholderElement).getPropertyValue(`--${containerPosition}-head-font`);
    activeElement.querySelectorAll('.component-titlebar h2').forEach((item) => item.style.color = headFont );
    activeElement.querySelectorAll('.component-titlebar a').forEach((item) => item.style.color = headFont );

}

/**
 * Constantly check for the nearest other sortable and update position 
 * based on whether a move is valid
 */
const onDrag = () => {
    
    if (!isScrolling) { //don't be checking while scrolling, as can jar the window and produce weird behaviour
        
        let sortablesToCheckList = Array.from(document.querySelectorAll(sortablesSelector)); //grab all sortable elements 
        
        if (willChangeContainer().hasHit) { //if there's a container change required, only check against those elements in the new container
            const newContainer = willChangeContainer().newContainer;
            sortablesToCheckList = sortablesToCheckList.filter((item) => newContainer.contains(item)); //filter sortables check to those in the new parent container
        }

        const closestElement = getClosestElement(getElementsMidPoint(activeElement), sortablesToCheckList); //the element nearest to the dragged element
        
        if (isElementAboveOtherElement(activeElement, closestElement)) {
            flipState(closestElement, true);
        } else if (isElementBelowOtherElement(activeElement, closestElement)) {
            flipState(closestElement, false);
        }

    }

}

/**
 *  To see whether the sortable element is being dragged into the range of a new parent container,
 *  and if so pass the new container back so it can be used to shift the activeElement into
 * 
 * @retruns { Boolean, Element } 
 */
 const willChangeContainer = () => {
    let containerChange = { hasHit: false, newContainer: null };
    document.querySelectorAll(sortablesContainersSelector).forEach((container) => {
        if (Draggable.hitTest(container, activeElement, '51%') && !container.contains(placeholderElement) && container.children.length > 0) { //more than 51% is being dragged in and it's not the current parent
            containerChange = { hasHit: true, newContainer: container }
        }
    });
    return containerChange;
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
    
    const state = Flip.getState(document.querySelectorAll(sortablesSelector));
    
    if (isShiftingDown) {
        insertElementAfter(closestElement, placeholderElement);
    } else {
        insertElementBefore(closestElement, placeholderElement);
    }

    activeElement.dataset.leftDifference = placeholderElement.getBoundingClientRect().left - parseInt(placeholderElement.dataset.originalLeft);
    activeElement.dataset.topDifference = (placeholderElement.getBoundingClientRect().top + window.scrollY) - parseInt(placeholderElement.dataset.originalTop);

    Flip.from(state, {
        duration: 0.3,
        ease: 'power1.InOut'
    });

}

/**
 * Just snap back to where it now belongs
 */
 const onDragEnd = () => {
    gsap.to(activeElement, {
        x: 0 + parseInt(activeElement.dataset.leftDifference), //to accommodate for potential position changes that have occured
        y: 0 + parseInt(activeElement.dataset.topDifference),
        width: placeholderElement.parentElement.offsetWidth, //parentElement as the placeHolder may not have fully shifted into place before mouse release
        duration: 0.3,
        overwrite: true,
        onComplete: () => onSnapActiveElementBackComplete()
    });
}

/**
 * Tidy up once the element has snapped back 
 * setting up for another drag, returning various variables back to there pre dragging state
 * 
 * @param { DOMElement }  
 */
 const onSnapActiveElementBackComplete = () => {

    gsap.set(activeElement, { x: 0, y: 0, width: '100%' }); //reset the animation properties for another animate
    activeElement.style.position = 'static'; //was absolute, now sitting as it should
    updateActiveElementComputedStyles();
    activeElement.setAttribute('data-component', ''); //so it can be dragged again
    
    insertElementAfter(placeholderElement, activeElement); //the element was taken out, so put it back where it now belongs
    placeholderElement.remove(); //and now the placeholder can be pulled out

    document.body.style.overflowX = 'auto'; //was hidden to avoid horizontal scroll bars
    document.documentElement.style.scrollBehavior = 'smooth'; //was auto to avoid jarring behaviour 
    
    window.removeEventListener('scroll', onScroll); //no need to listen for scrolling until next drag
    window.dispatchEvent(new Event('resize')); //for components to respond to - for example swiper.js image gallerys to update to display correctly
    
    updateSortableElements();
    submitNewComponentOrder();

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
        if (sortable.id) { //the <div class="masonry-placeholder" data-component /> exists in each parent container
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
    updateSortableElements();
    initListeners();
}

document.addEventListener('DOMContentLoaded', init);
