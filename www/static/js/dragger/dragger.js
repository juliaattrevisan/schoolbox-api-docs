
import { gsap, Draggable, InertiaPlugin, Flip, CSSPlugin } from 'gsap/all';

gsap.registerPlugin(Draggable, InertiaPlugin, Flip, CSSPlugin);

let draggableElement; //the element that's currently being dragged around
let placeholderElement; //the element that subs in for the draggable element
let closestElement; //the element that subs in for the draggable element
let debug = true;


/**
 * Fire up Draggable, adding sorting functionality while dragging
 */
export const initDraggable = () => {
    Draggable.create(document.querySelectorAll('[data-draggable-item]'), {
        type: 'x, y',
        // trigger: '#topBar',
        // onDragStart: (event) => {
        //     draggableElement = event.target.closest('[data-draggable-item]');
        //     placeholderElement = getPlaceholderElement(draggableElement);
        //     pullDraggedElementOut(draggableElement);
        // },
        // onDrag: (event) => {
        //     if (debug && closestElement) closestElement.classList.remove('highlight');
        //     closestElement = getClosestElement(getElementsMidPoint(draggableElement), document.querySelectorAll('[data-draggable-item]'));
        //     if (debug) closestElement.classList.add('highlight');
        //     if (closestElement.dataset.currentIndex !== draggableElement.dataset.currentIndex) { //no point when nothing to shift to
        //         if (canShiftDown(draggableElement, closestElement)) {
        //             flipState(draggableElement, closestElement, true);
        //         } else if (canShiftUp(draggableElement, closestElement)) {
        //             flipState(draggableElement, closestElement, false);
        //         }
        //     }
        // },
        // onDragEnd: (event) => {
        //     snapElementBack(draggableElement);
        // },
    });
    // updateDraggableItems();
}

/**
 * To be able to sort elements and snap back to correct position
 */
const updateDraggableItems = () => {
    document.querySelectorAll('[data-draggable-item]').forEach((item, index) => {
        item.dataset.currentIndex = index;
        item.dataset.originalX = item.getBoundingClientRect().x;
        item.dataset.xDifference = 0;
        item.dataset.originalY = item.getBoundingClientRect().y;
        item.dataset.yDifference = 0;
    });
}

/**
 * Create a clone of the element being dragged to use as a placeholder 
 * to keep it in position as the actually dragged element will be pulled out
 * 
 * @param { Element }  
 * 
 * @returns { Element }  
 */
const getPlaceholderElement = (element) => {
    const clone = element.cloneNode(true)
    if (debug) clone.style.opacity = 0.5;
    else clone.style.opacity = 0;
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
    // document.body.appendChild(element);
    // debugger;
    element.style.position = 'absolute';
    element.style.width = `${placeholderElement.getBoundingClientRect().width}px`; //use the placeholder to maintain the correct width
    // element.style.top = `${placeholderElement.getBoundingClientRect().top}px`;
    // element.style.left = `${placeholderElement.getBoundingClientRect().left}px`;
    // element.style.boxShadow = '0px 0px 5px #888888';
    element.removeAttribute('data-draggable-item'); //so there's no position checks when dragging, as the placeholder is there instead
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
        const distance = Math.sqrt((elementsMidPoint.x - targetPoint.x) * (elementsMidPoint.x - targetPoint.x) + (elementsMidPoint.y - targetPoint.y) * (elementsMidPoint.y - targetPoint.y));
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
        x: element.getBoundingClientRect().x + (element.offsetWidth / 2),
        y: element.getBoundingClientRect().y + (element.offsetHeight / 2)
    };
}

/**
 * If the midpoint of the dragged element is below the midpoint of the element below
 * 
 * @param { Element }  
 * @param { Element }  
 * 
 * @returns { Boolean }  
 */
const canShiftDown = (draggedElement, closestElement) => {
    return (getElementsMidPoint(draggedElement).y > getElementsMidPoint(closestElement).y);
}

/**
 * If the midpoint of the dragged element is above the midpoint of the element above
 * 
 * @param { Element }  
 * @param { Element }  
 * 
 * @returns { Boolean }  
 */
const canShiftUp = (draggedElement, closestElement) => {
    return (getElementsMidPoint(draggedElement).y < getElementsMidPoint(closestElement).y);
}

/**
 * Use Flip to shift the elements around, recording the difference in shift 
 * for when the dragged element is released
 * 
 * @param { Element }  
 * @param { Element }  
 * @param { Boolean }  
 * 
 */
const flipState = (draggedElement, closestElement, isShiftingDown) => {
    const state = Flip.getState(document.querySelectorAll('[data-draggable-item]'));
    if (isShiftingDown) {
        insertAfter(closestElement, placeholderElement);
    } else {
        insertBefore(closestElement, placeholderElement);
    }
    draggedElement.dataset.xDifference = placeholderElement.getBoundingClientRect().x - parseInt(placeholderElement.dataset.originalX);
    draggedElement.dataset.yDifference = placeholderElement.getBoundingClientRect().y - parseInt(placeholderElement.dataset.originalY);
    document.querySelectorAll('[data-draggable-item]').forEach((item, index) => {
        item.dataset.currentIndex = index;
    });
    Flip.from(state, {
        duration: 0.2,
        ease: 'power1.Out'
    });
}

/**
 * Insert the target node before the reference
 * 
 * @param { Element }  
 * @param { Element }  
 * 
 */
const insertBefore = (referenceNode, targetNode) => {
  referenceNode.parentNode.insertBefore(targetNode, referenceNode);
}

/**
 * Insert the target node after the reference
 * 
 * @param { Element }  
 * @param { Element }  
 * 
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
 * 
 */
const snapElementBack = (element) => {
    gsap.to(element, {
        x: 0 + parseInt(element.dataset.xDifference), //to accommodate for potential position changes
        y: 0 + parseInt(element.dataset.yDifference),
        // width: placeholderElement.parentElement.offsetWidth, //parentElement as the placeHolder may not have fully shifted into place before mouse release
        width: placeholderElement.offsetWidth, //parentElement as the placeHolder may not have fully shifted into place before mouse release
        duration: 0.3,
        overwrite: true,
        onComplete: () => {
            insertAfter(placeholderElement, element);
            placeholderElement.remove();
            // gsap.set(element, { x: 0, y: 0, width: '100%' });
            gsap.set(element, { x: 0, y: 0, width: 'auto' });
            element.style.position = 'relative';
            // element.style.boxShadow = 'none';
            element.setAttribute('data-draggable-item', '');
            updateDraggableItems();
    } });
}

