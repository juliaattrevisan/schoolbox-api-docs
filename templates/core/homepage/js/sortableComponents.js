
import { gsap, Draggable, InertiaPlugin, Flip, CSSPlugin } from 'gsap/all';

gsap.registerPlugin(Draggable, InertiaPlugin, Flip, CSSPlugin);

let draggables = []; //to contain the Draggable instance, to enable/disable depending on the #component-lock-switch status 
let sortableElement; //the element that's currently being dragged/sorted around
let placeholderElement; //the element that subs in for the element being sorted
let sortablesContainersSelector = '[data-components-container]';
let sortablesSelector = '[data-component]';
let sortablesTriggerSelector = '[data-component-titlebar-container]';
let isScrollingTimeout = false;
let isScrolling = false;


/**
 * Fire up Draggable, adding sorting functionality while dragging
 * Refer to https://greensock.com/docs/v3/Plugins/Draggable for further info
 */
const initSortables = () => {
    document.querySelectorAll(sortablesSelector).forEach((sortableItem) => {
        draggables.push(Draggable.create(sortableItem, {
            type: 'x, y',
            trigger: sortableItem.querySelector(sortablesTriggerSelector),
            autoScroll: 1,
            onDragStart: (event) => onDragStart(event),
            onDrag: () => onDrag(),
            onDragEnd: () => onDragEnd(),
        })[0]);
    });
    draggables.forEach((draggable) => draggable.disable()); //disabled at start until the #component-lock-switch is toggled
}

/**
 * To be able to sort elements and snap back to correct position
 */
 const updateSortables = () => {
    document.querySelectorAll(sortablesSelector).forEach((sortableItem, index) => {
        sortableItem.dataset.currentIndex = index; //used to compare items
        sortableItem.dataset.originalLeft = sortableItem.getBoundingClientRect().left;
        sortableItem.dataset.leftDifference = 0;
        sortableItem.dataset.originalTop = sortableItem.getBoundingClientRect().top + window.scrollY;
        sortableItem.dataset.topDifference = 0;
    });
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
    window.removeEventListener('scroll', onScroll);
    document.body.style.overflowX = 'auto'; 
    document.documentElement.style.scrollBehavior = 'smooth'; 
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
 * and to keep it in position as the actually dragged element will be pulled out
 * 
 * @param { Element }  
 * 
 * @returns { Element }  
 */
const getPlaceholderElement = (element) => {
    const clone = element.cloneNode(true);
    element.parentNode.insertBefore(clone, element);
    return clone;
}

/**
 * With the placeholder element in place, the dragged element is pulled 
 * out so it doesn't impact our ability to sort through the items
 * 
 * @param { Element }  
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
 * @param { Element } 
 */
 const updateDraggedElementStyles = (element) => {

    element.style.position = 'absolute';
    element.style.top = `${placeholderElement.getBoundingClientRect().top + window.scrollY}px`;
    element.style.left = `${placeholderElement.getBoundingClientRect().left - 16 }px`;
    element.style.width = `${placeholderElement.getBoundingClientRect().width + 32 }px`;

    const headBg = getComputedStyle(placeholderElement).getPropertyValue('--top-head-bg');
    element.querySelectorAll('.component-titlebar').forEach( (item) => item.style.backgroundColor = headBg );

    const headFont = getComputedStyle(placeholderElement).getPropertyValue('--top-head-font');
    element.querySelectorAll('.component-titlebar h2').forEach( (item) => item.style.color = headFont );
    element.querySelectorAll('.component-titlebar a').forEach( (item) => item.style.color = headFont );

 }

/**
 *  To see whether the sortable element is being dragged into the range of a new parent container
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
 * Find the closest element to the element being dragged, which can be the dragged element
 * itself if haven't dragged very far (or the items are very tall)
 * 
 * @param { x, y }  
 * @param { NodeList }  
 * 
 * @returns { Element }   
 */
const getClosestElement = (targetPoint, elements) => {
    let closestElement = elements[0];
    let minDistance = 99999; //if this doesn't get overwritten we got issues...
    elements.forEach((element) => { //loop through each element and find the closest element using pythagoras theorem
        const elementsMidPoint = getElementsMidPoint(element);
        const distance = Math.sqrt((elementsMidPoint.left - targetPoint.left) * (elementsMidPoint.left - targetPoint.left) + (elementsMidPoint.top - targetPoint.top) * (elementsMidPoint.top - targetPoint.top));
        if (distance < minDistance) {
            minDistance = distance;
            closestElement = element;
        }
    });
    return closestElement;
};

/**
 * To get an elements mid point relative to the document
 * 
 * @param { Element }  
 * 
 * @returns { x, y }   
 */
const getElementsMidPoint = (element) => {
    return {
        left: element.getBoundingClientRect().left + (element.offsetWidth / 2),
        top: (element.getBoundingClientRect().top + window.scrollY) + (element.offsetHeight / 2)
    };
}

/**
 * Is the midpoint of an element above the midpoint of another element
 * 
 * @param { Element }  
 * @param { Element }  
 * 
 * @returns { Boolean }  
 */
const isElementAboveOtherElement = (firstElement, secondElement) => {
    return (getElementsMidPoint(firstElement).top > getElementsMidPoint(secondElement).top);
}

/**
 * Is the midpoint of an element below the midpoint of another element
 * 
 * @param { Element }  
 * @param { Element }  
 * 
 * @returns { Boolean }  
 */
const isElementBelowOtherElement = (firstElement, secondElement) => {
    return (getElementsMidPoint(firstElement).top < getElementsMidPoint(secondElement).top);
}

/**
 * Use Flip to shift the elements around, recording the difference in shift 
 * for when the dragged element is released
 * Refer to https://greensock.com/docs/v3/Plugins/Flip for more info on Flip
 * 
 * @param { Element }  
 * @param { Element }  
 * @param { Boolean }  
 */
const flipState = (draggedElement, closestElement, isShiftingDown) => {
    const state = Flip.getState(document.querySelectorAll(sortablesSelector));
    if (isShiftingDown) {
        insertAfter(closestElement, placeholderElement);
    } else {
        insertBefore(closestElement, placeholderElement);
    }
    draggedElement.dataset.leftDifference = placeholderElement.getBoundingClientRect().left - parseInt(placeholderElement.dataset.originalLeft);
    draggedElement.dataset.topDifference = (placeholderElement.getBoundingClientRect().top + window.scrollY) - parseInt(placeholderElement.dataset.originalTop);
    document.querySelectorAll(sortablesSelector).forEach((item, index) => {
        item.dataset.currentIndex = index;
    });
    Flip.from(state, {
        duration: 0.3,
        ease: 'power1.Out'
    });
}

/**
 * Insert the target node before the reference
 * 
 * @param { Element }  
 * @param { Element }  
 */
const insertBefore = (referenceNode, targetNode) => {
  referenceNode.parentNode.insertBefore(targetNode, referenceNode);
}

/**
 * Insert the target node after the reference
 * 
 * @param { Element }  
 * @param { Element }  
 */
const insertAfter = (referenceNode, targetNode) => {
  referenceNode.parentNode.insertBefore(targetNode, referenceNode.nextSibling);
}

/**
 * Snap the dragged element back to its original OR new position
 * and then remove the placeholder element once complete, 
 * setting up for another drag
 * 
 * @param { Element }  
 */
const snapElementBack = (element) => {
    gsap.to(element, {
        x: 0 + parseInt(element.dataset.leftDifference), //to accommodate for potential position changes
        y: 0 + parseInt(element.dataset.topDifference),
        width: placeholderElement.parentElement.offsetWidth, //parentElement as the placeHolder may not have fully shifted into place before mouse release
        duration: 0.3,
        overwrite: true,
        onComplete: () => {
            insertAfter(placeholderElement, element);
            gsap.set(element, { x: 0, y: 0, width: '100%' });
            element.style.position = 'static';
            element.style.boxShadow = 'none';
            element.setAttribute('data-component', '');
            document.body.style.overflowX = 'auto';
            placeholderElement.remove();
            updateSortables();
    } });
}

/**
 * Fire everything up
 */
 const init = () => {
    initSortables();
    updateSortables();
    initComponentLockSwitchListener();
}

document.addEventListener('DOMContentLoaded', init);
