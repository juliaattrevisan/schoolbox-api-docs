
import { gsap, Draggable, InertiaPlugin, CSSPlugin } from 'gsap/all';

gsap.registerPlugin(Draggable, InertiaPlugin, CSSPlugin);

const SCROLL_PADDING = 10;

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
        this.innerContainer.style.width = `${this.innerContainer.scrollWidth}px`; //ensure can grab the whole container to drag
        
        this.initDraggable();
        this.initPreviousButton();
        this.initNextButton();
        this.initScrollStatus();

        this.updated();

    }

    /**
     * Set everything up
     */
    initDraggable () {

        this.minX = -(this.innerContainer.scrollWidth - this.outerContainer.clientWidth);
        this.maxX = 0;

        this.draggable = Draggable.create(this.innerContainer, {
            type: 'x',
            bounds: { minX: this.minX, maxX: this.maxX },
            edgeResistance: 0.75,
            inertia: true,
            onDragStart: () => this.showScrollStatus(),
            onDrag: () => this.updateScrollStatus(),
            onThrowUpdate: () => this.updateScrollStatus(),
            onThrowComplete: () => this.hideScrollStatus()
        })[0];

    }

    /**
     * Slider has been activated, so show the scroll status
     */
    showScrollStatus () { 
        gsap.to(this.scrollStatus, { alpha: 1, duration: 0.3 } );
    }

    /**
     * Keep the scroll status accurate with the current slide position
     */
    updateScrollStatus () {
        gsap.set(this.scrollStatus, { x: -(gsap.getProperty(this.innerContainer, 'x') * this.scrollStatusXFactor) } );
    } 
    
    /**
     * Fade out the scroll status, when no longer moving
     */
    hideScrollStatus () {
        gsap.to(this.scrollStatus, { alpha: 0, duration: 0.3 } );
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
            onStart: () => this.showScrollStatus(),
            onUpdate: () => this.updateScrollStatus(),
            onComplete: () => this.hideScrollStatus()
        } );
    }

    /**
     * Move the scroller to the left
     */
    initPreviousButton () {
        this.previousButton = this.scrollerContainer.querySelector('[data-scroller-previous-button]');
        this.previousButton.addEventListener('click', () => {
            let xTarget = gsap.getProperty(this.innerContainer, 'x') + this.outerContainer.clientWidth;
            if (xTarget > this.maxX) {
                xTarget = this.maxX;
            }
            this.moveInnerContainer(xTarget);
        });
    }

    /**
     * Move the scroller to the right
     */
    initNextButton () {
        this.nextButton = this.scrollerContainer.querySelector('[data-scroller-next-button]');
        this.nextButton.addEventListener('click', () => {
            let xTarget = gsap.getProperty(this.innerContainer, 'x') - this.outerContainer.clientWidth;
            if (xTarget < this.minX) {
                xTarget = this.minX;
            }
            this.moveInnerContainer(xTarget);
        });
    }

    /**
     * The visual indication of scroll progress, moves as you scroll
     */
    initScrollStatus () {
        let scrollStatusWidth = (this.outerContainer.clientWidth - SCROLL_PADDING) * (this.outerContainer.clientWidth / this.innerContainer.scrollWidth);
        if (scrollStatusWidth < 100) { //don't go to small
            scrollStatusWidth = 100;
        }
        this.scrollStatus = this.scrollerContainer.querySelector('[data-scroller-scroll-status]');
        this.scrollStatus.style.width = `${scrollStatusWidth}px`; //provide an accurate indication of length of scroll 
        gsap.set(this.scrollStatus, { alpha: 0 } ); //only show when sliding active
        this.scrollStatusXFactor = (this.outerContainer.clientWidth - this.scrollStatus.clientWidth - SCROLL_PADDING) / (this.innerContainer.scrollWidth - this.outerContainer.clientWidth);
    }

    /**
     * Only need to provide sliding functionality when we actually need to slide!
     */
    updated () {
        if (this.innerContainer.scrollWidth === this.outerContainer.clientWidth) { //no need to enable when can see the entire container content
            this.draggable.disable();
            this.nextButton.style.display = 'none';
            this.previousButton.style.display = 'none';
        } else {
            this.draggable.enable();
            this.nextButton.style.display = 'block';
            this.previousButton.style.display = 'block';
        }
    }

}