
/**
 * A horizontal scroll container that uses GSAP's Draggable 
 * Handy for when there's more to display that what horizontal space will allow
 */


import { gsap, Draggable, InertiaPlugin } from 'gsap/all';

gsap.registerPlugin(Draggable, InertiaPlugin);

const SCROLL_PADDING = 10;
const MIN_SCROLL_BAR_WIDTH = 100;

export default class HorizontalScroller {

    /**
     * Set everything up
     * 
     * @params { DOMElement } the container that contains the horizontal scroller
     */
    constructor (scrollerContainer) {

        this.scrollerContainer = scrollerContainer;
        
        this.outerContainer = this.scrollerContainer.querySelector('[data-scroller-outer-container]');
        this.innerContainer = this.scrollerContainer.querySelector('[data-scroller-inner-container]');
        
        this.initDraggable();
        this.initPreviousButton();
        this.initNextButton();
        this.initScrollBar();

        this.refresh();
        window.addEventListener('resize', () => this.refresh());

    }

    /**
     * init the draggable instance, which is the core functionality 
     */
    initDraggable () {
        this.draggable = Draggable.create(this.innerContainer, {
            type: 'x',
            edgeResistance: 0.75,
            inertia: true,
            onDragStart: () => this.onStart(),
            onDrag: () => this.onUpdate(),
            onThrowUpdate: () => this.onUpdate(),
            onThrowComplete: () => this.onComplete()
        })[0];
    }

    /**
     * Ensure can't scroll beyond the bounds of what's in the container
     */
    refreshDraggableBounds () { 
        this.innerContainer.style.width = 'auto'; //reset for accurate re-display and bound detection
        this.innerContainer.style.width = `${this.innerContainer.scrollWidth}px`; //ensure can grab the whole container to drag
        this.minX = -(this.innerContainer.scrollWidth - this.outerContainer.clientWidth);
        this.maxX = 0;
        this.draggable.applyBounds( { minX: this.minX, maxX: this.maxX } );
    }

    /**
     * Scroller has been activated, so show the scroll bar to track scroll progress
     */
    onStart () { 
        gsap.to(this.scrollBar, { alpha: 1, duration: 0.3 } );
    }

    /**
     * Keep the scroll bar accurate with the current scroll position
     * And disable the previous / next buttons when at then scroll bounds
     */
    onUpdate () {
        gsap.set(this.scrollBar, { x: -(gsap.getProperty(this.innerContainer, 'x') * this.scrollBarXFactor) } );
        this.checkPreviousButtonStatus();
        this.checkNextButtonStatus();
    } 

    /**
     * Disable when there's no more previous movement
     */
    checkPreviousButtonStatus () {
        if (gsap.getProperty(this.innerContainer, 'x') >= 0) {  //means scrolling is beyond the left edge
            this.previousButton.setAttribute('disabled', true);
        } else {
            this.previousButton.removeAttribute('disabled');
        }
    } 

    /**
     * Disable when there's no more previous movement
     */
    checkNextButtonStatus () {
        if (gsap.getProperty(this.innerContainer, 'x') <= this.minX) {  //means scrolling is beyond the right edge
            this.nextButton.setAttribute('disabled', true);
        } else {
            this.nextButton.removeAttribute('disabled');
        }
    } 
    
    /**
     * Fade out the scroll bar, when no longer moving
     */
    onComplete () {
        gsap.to(this.scrollBar, { alpha: 0, duration: 0.3 } );
    } 

    /**
     * Used by the next/previous buttons to move the scroller
     * 
     * @param {Number}
     */
    moveInnerContainer (xTarget) {
        gsap.to(this.innerContainer, { 
            x: xTarget, 
            duration: 0.5, 
            onStart: () => this.onStart(),
            onUpdate: () => this.onUpdate(),
            onComplete: () => this.onComplete()
        } );
    }

    /**
     * Move the scroller to the left
     */
    initPreviousButton () {
        this.previousButton = this.scrollerContainer.querySelector('[data-scroller-previous-button]');
        this.previousButton.addEventListener('click', () => {
            const xTarget = gsap.getProperty(this.innerContainer, 'x') + this.outerContainer.clientWidth;
            const constrainedXTarget = (xTarget > this.maxX) ? this.maxX : xTarget; //ensure doesn't move beyond the container bounds
            this.moveInnerContainer(constrainedXTarget);
        });
    }

    /**
     * Move the scroller to the right
     */
    initNextButton () {
        this.nextButton = this.scrollerContainer.querySelector('[data-scroller-next-button]');
        this.nextButton.addEventListener('click', () => {
            const xTarget = gsap.getProperty(this.innerContainer, 'x') - this.outerContainer.clientWidth;
            const constrainedXTarget = (xTarget < this.minX) ? this.minX : xTarget; //ensure doesn't move beyond the container bounds
            this.moveInnerContainer(constrainedXTarget);
        });
    }

    /**
     * The visual indication of scroll progress, moves as you scroll
     */
    initScrollBar () {
        this.scrollBar = this.scrollerContainer.querySelector('[data-scroller-scroll-bar]');
        gsap.set(this.scrollBar, { alpha: 0 } ); //only show when sliding active
        this.refreshScrollBarWidth();
    }

    /**
     * Ensure the size of the scroll bar accurately reflects the amount of scroll required
     */
    refreshScrollBarWidth () {
        const scrollBarWidth = (this.outerContainer.clientWidth - SCROLL_PADDING) * (this.outerContainer.clientWidth / this.innerContainer.scrollWidth);
        const constrainedScrollBarWidth = (scrollBarWidth < MIN_SCROLL_BAR_WIDTH) ? MIN_SCROLL_BAR_WIDTH : scrollBarWidth;  //don't go to small
        this.scrollBar.style.width = `${constrainedScrollBarWidth}px`; //provide an accurate indication of length of scroll 
        this.scrollBarXFactor = (this.outerContainer.clientWidth - this.scrollBar.clientWidth - SCROLL_PADDING) / (this.innerContainer.scrollWidth - this.outerContainer.clientWidth);
    }

    /**
     * Only need to provide scrolling functionality when we actually need to scroll!
     */
    refresh () {
        gsap.to(this.innerContainer, { x: 0, duration: 0.5 } ); //move back to the start
        this.refreshDraggableBounds();
        this.checkPreviousButtonStatus();
        this.checkNextButtonStatus();
        this.minX < 0 ? this.enable() : this.disable(); //only provide scrolling when needed
    }

    /**
     * enable the functionality of scroller, the contents of the container goes beyond the bounds set
     */
     enable () {
        this.nextButton.style.display = 'block';
        this.previousButton.style.display = 'block';
        this.draggable.enable();
        this.refreshScrollBarWidth();
    }

    /**
     * disable all the functionality of scroller, not enough in the container to be needed
     */
    disable () {
        this.nextButton.style.display = 'none';
        this.previousButton.style.display = 'none';
        this.draggable.disable();
    }
    
}