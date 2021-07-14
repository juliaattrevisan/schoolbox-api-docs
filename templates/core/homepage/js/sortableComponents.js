
import { gsap, Draggable, Flip } from 'gsap/all';
import axios from 'axios';
import { insertElementBefore, insertElementAfter, getClosestElement, getElementsMidPoint, isElementAboveOtherElement, isElementBelowOtherElement, setStylesOnElement } from '@js/util/domUtils';

gsap.registerPlugin(Draggable, Flip);

let draggables = []; //to contain the Draggable instance, to enable/disable depending on the #component-lock-switch status 
let sortableElement; //the element that's currently being dragged/sorted around
let placeholderElement; //the element that subs in for the element being sorted
let sortablesContainersSelector = '[data-components-container]';
let sortablesSelector = '[data-component]';
let sortablesTriggerSelector = '[data-component-titlebar-container]';
let isScrollingTimeout = false;
let isScrolling = false;


/**
 * Fire up Draggable, looping through each sortable to assign a Draggable instance 
 */
const initSortables = () => {
    document.querySelectorAll(sortablesSelector).forEach((sortableItem) => {
        draggables.push(getDraggable(sortableItem));
    });
    draggables.forEach((draggable) => draggable.disable()); //disabled at start until the #component-lock-switch is toggled
    draggables.forEach((draggable) => {
        console.error(draggable.target.id);
    }); //disabled at start until the #component-lock-switch is toggled
    updateSortables();
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
 const updateSortables = () => {
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
    const componentLockSwidth = document.getElementById('component-lock-switch').querySelector('input');
    componentLockSwidth.addEventListener('change', (event) => {
        if (event.target.checked) {
            draggables.forEach((draggable) => draggable.enable());
        } else {
            draggables.forEach((draggable) => draggable.disable());
        }
    });
}

/**
 * To add a newly added component to the sortable list
 */
 const initOnComponentAddedListener = () => {
    document.addEventListener('jquery.masonry__onComponentAdded', (event) => {
        const newDraggable = getDraggable(document.getElementById(event.detail.componentID));
        newDraggable.enabled(draggables[0].enabled()); //just base the active status on the first Draggable instance
        draggables.push(newDraggable); //add the component into the sortable list
        updateSortables(); //update the position of all elements due to the new element being added
    });
}

/**
 * To remove a component to the sortable list
 */
 const initOnComponentRemovedListener = () => {
    document.addEventListener('jquery.masonry__onComponentRemoved', (event) => {
        const draggableToRemove = draggables.find((draggable) => getComponentID(draggable.target) === event.detail.componentID);
        draggableToRemove.kill(); //disables the Draggable instance
        draggables = draggables.filter((draggable) => getComponentID(draggable.target) !== event.detail.componentID); //update the draggables list, pullling out the removed component
        updateSortables(); //update the position of all elements due to the new element being added
    });
}

/**
 * Sub in a placeholder to use in place of the element being dragged, 
 * which gets pulled out so it can go into any other parent container 
 * that contains the sortablesSelector
 */
const onDragStart = (event) => {
    sortableElement = event.target.closest(sortablesSelector); //use the parent container regardless of selection
    window.addEventListener('scroll', onScroll);
    document.body.style.overflowX = 'hidden'; //avoid horizontal scroll bars
    document.documentElement.style.scrollBehavior = 'auto'; //disable smooth scroll as it can jar while moving elements around 
    sortableElement = event.target.closest(sortablesSelector); //use the parent container regardless of selection
    placeholderElement = getPlaceholderElement(sortableElement);
    placeholderElement.style.opacity = 0.25; //displayed as reference where the sortable is currently placed 
    pullDraggedElementOut(sortableElement);

}

/**
 * Constantly check for the nearest other sortable and update position 
 * based on whether a move is valid
 */
const onDrag = () => {
    if (!isScrolling) { //don't be checking while scrolling, as can jar the window and produce weird behaviour
        let sortablesToCheckClosestElement = Array.from(document.querySelectorAll(sortablesSelector)); //grab all elements 
        if (willChangeContainer().hasHit) { //if there's a container change required, only check against those elements in the new container
            const newContainer = willChangeContainer().newContainer;
            sortablesToCheckClosestElement = sortablesToCheckClosestElement.filter((item) => newContainer.contains(item));
        }
        const closestElement = getClosestElement(getElementsMidPoint(sortableElement), sortablesToCheckClosestElement); //the element nearest to the dragged element
        if (isElementAboveOtherElement(sortableElement, closestElement)) {
            flipState(sortableElement, closestElement, true);
        } else if (isElementBelowOtherElement(sortableElement, closestElement)) {
            flipState(sortableElement, closestElement, false);
        }
    }
}

/**
 * Just snap back to where it now belongs
 */
const onDragEnd = () => {
    snapElementBack(sortableElement);
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
 * Create a clone of the element being dragged to use as a placeholder 
 * and to keep it in position as the actually dragged element will be pulled out of context
 * 
 * @param { DOMElement }  
 * 
 * @returns { DOMElement }  
 */
const getPlaceholderElement = (elementToClone) => {
    const cloneElement = elementToClone.cloneNode(true);
    insertElementBefore(elementToClone, cloneElement);
    return cloneElement;
}

/**
 * With the placeholder element in place, the dragged element is pulled 
 * out so it doesn't impact our ability to sort through the items
 * 
 * @param { DOMElement }  
 */
const pullDraggedElementOut = (element) => {
    document.body.appendChild(element);
    element.removeAttribute('data-component'); //so there's no position checks when dragging, as the placeholder is there instead
    updateDraggedElementStyles(element);
}

/**
 *  Update position and styles to match as the dragged element
 *  has now been moved out of its context
 * 
 * @param { DOMElement } 
 */
const updateDraggedElementStyles = (element) => {

    setStylesOnElement(element, {
        position: 'absolute',
        top: `${placeholderElement.getBoundingClientRect().top + window.scrollY}px`,
        left: `${placeholderElement.getBoundingClientRect().left - 16 }px`,
        width: `${placeholderElement.getBoundingClientRect().width + 32 }px`,
    });

    const headBg = getComputedStyle(placeholderElement).getPropertyValue('--top-head-bg');
    element.querySelectorAll('.component-titlebar').forEach( (item) => item.style.backgroundColor = headBg );

    const headFont = getComputedStyle(placeholderElement).getPropertyValue('--top-head-font');
    element.querySelectorAll('.component-titlebar h2').forEach( (item) => item.style.color = headFont );
    element.querySelectorAll('.component-titlebar a').forEach( (item) => item.style.color = headFont );

 }

/**
 *  To see whether the sortable element is being dragged into the range of a new parent container,
 *  and if so pass the new container back so it can be used to shift the sortableElement into
 * 
 * @retruns { Boolean, Element } 
 */
const willChangeContainer = () => {
    let containerChange = { hasHit: false, newContainer: null };
    document.querySelectorAll(sortablesContainersSelector).forEach((container) => {
        if (Draggable.hitTest(container, sortableElement, '51%') && !container.contains(placeholderElement) && container.children.length > 0) {
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
 * @param { DOMElement }  
 * @param { Boolean }  
 */
const flipState = (draggedElement, closestElement, isShiftingDown) => {
    
    const state = Flip.getState(document.querySelectorAll(sortablesSelector));
    
    if (isShiftingDown) {
        insertElementAfter(closestElement, placeholderElement);
    } else {
        insertElementBefore(closestElement, placeholderElement);
    }

    draggedElement.dataset.leftDifference = placeholderElement.getBoundingClientRect().left - parseInt(placeholderElement.dataset.originalLeft);
    draggedElement.dataset.topDifference = (placeholderElement.getBoundingClientRect().top + window.scrollY) - parseInt(placeholderElement.dataset.originalTop);

    Flip.from(state, {
        duration: 0.3,
        ease: 'power1.Out'
    });

}

/**
 * Snap the dragged element back to its original OR new position
 * 
 * @param { DOMElement }  
 */
const snapElementBack = (element) => {
    gsap.to(element, {
        x: 0 + parseInt(element.dataset.leftDifference), //to accommodate for potential position changes that have occured
        y: 0 + parseInt(element.dataset.topDifference),
        width: placeholderElement.parentElement.offsetWidth, //parentElement as the placeHolder may not have fully shifted into place before mouse release
        duration: 0.3,
        overwrite: true,
        onComplete: () => onSnapElementBackComplete(element)
    });
}

/**
 * Tidy up once the element has snapped back 
 * setting up for another drag, returning various variables back to there pre dragging state
 * 
 * @param { DOMElement }  
 */
 const onSnapElementBackComplete =  (element) => {
    insertElementAfter(placeholderElement, element); //the element was taken out, so put it back where it belongs
    placeholderElement.remove(); //and now the placeholder can be pulled out
    gsap.set(element, { x: 0, y: 0, width: '100%' }); //reset the animation properties for another animate
    element.style.position = 'static'; //was absolute, now sitting as it should
    element.setAttribute('data-component', ''); //so it can be dragged again
    document.body.style.overflowX = 'auto'; //was hidden to avoid horizontal scroll bars
    document.documentElement.style.scrollBehavior = 'smooth'; //was auto to avoid jarring behaviour 
    window.removeEventListener('scroll', onScroll); //no need to listen for scrolling until next drag
    window.dispatchEvent(new Event('resize')); //for components to respond to - for example swiper.js image gallerys to update to display correctly
    updateSortables();
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
    formData.append('movedComponentId', getComponentID(sortableElement));
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
        if (sortable.id) { //the <div class="masonry-placeholder" data-component /> exists in each container
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
    initSortables();
    initListeners();
}

document.addEventListener('DOMContentLoaded', init);
