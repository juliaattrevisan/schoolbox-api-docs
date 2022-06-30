<script>

/**
 * To scroll a list of items horizontally
 */

// import HorizontalScroller from '@js/scroll/HorizontalScroller';
import { gsap, Draggable, InertiaPlugin } from 'gsap/all';
import { ResizeObserver } from 'vue3-resize';
import 'vue3-resize/dist/vue3-resize.css';

gsap.registerPlugin(Draggable, InertiaPlugin);

const BADGE_ANIMATE_DURATION = 0.3;
const SCROLL_PADDING = 10;
const MIN_SCROLL_BAR_WIDTH = 100;

export default {
    
    components: {
        ResizeObserver //to track any size changes to the parent container
    },

    props: {
        hasButtons: { //whether to show prev/next buttons
            type: Boolean,
            required: true
        },
        height: { //to set how high the container will be
            type: Number,
            required: true
        },
        hasContentChange: { //to trigger a resize when the content within changes
            type: Boolean, 
            required: true
        }
    },

    data () {
        return {
            minX: 0, //to restrict movement to the left
            maxX: 0, //to restrict movement to the right
            currentXPos: 0, //to track the scroll progress
            isEnabled: false, //don't always need to scroll horizontally if there's not enough in the container
            scrollBarWidth: 0, //to adjust the width of the scroll progress indicator based on the amount of content to scroll
        }
    },

    watch: {

        /**
         * Used to notify that the parent has changed the content within, 
         * so we need to evaluate the scroller again
         * 
         * @params { Boolean } 
         */
        hasContentChange (hasChange) {
            if (hasChange) {
                this.refresh();
            }
        }

    },

    mounted () {
        this.initElements();
        this.initDraggable();
        this.initScrollBar();
        this.refresh();
    },

    methods: {

        /**
        * Save any dom elements for reference
        */
        initElements () {
            this.outerContainer = this.$el.querySelector('[data-scroller-outer-container]');
            this.innerContainer = this.$el.querySelector('[data-scroller-inner-container]');
            this.scrollBar = this.$el.querySelector('[data-scroller-scroll-bar]');
        },

        /**
        * Init the draggable instance, which is the core functionality 
        * https://greensock.com/docs/v2/Utilities/Draggable
        */
        initDraggable () {
            this.draggable = Draggable.create(this.innerContainer, {
                type: 'x',
                edgeResistance: 0.75,
                inertia: true,
                zIndexBoost: false,
                onDragStart: () => this.onStart(),
                onDrag: () => this.onUpdate(),
                onThrowUpdate: () => this.onUpdate(),
                onThrowComplete: () => this.onComplete()
            })[0];
        },

        /**
         * The visual indication of scroll progress, moves as you scroll
         */
        initScrollBar () {
            gsap.set(this.scrollBar, { alpha: 0 } ); //only show when sliding active
        },

        /**
         * Only need to provide scrolling functionality when we actually need to scroll
         */
        refresh () {
            gsap.to(this.innerContainer, { x: 0, duration: 0.5 } ); //don't use moveInnerContainer as we don't want to trigger the scrollBar
            this.refreshDraggableBounds();
            this.minX < 0 ? this.enable() : this.disable(); //only provide scrolling when needed
        },

        /**
         * Ensure can't scroll beyond the bounds of what's in the container
         */
        refreshDraggableBounds () { 
            this.innerContainer.style.width = 'auto'; //reset for accurate re-display and bound detection
            this.innerContainer.style.width = `${this.innerContainer.scrollWidth}px`; //and ensure can grab the whole container to drag
            this.minX = -(this.innerContainer.scrollWidth - this.outerContainer.clientWidth);
            this.maxX = 0;
            this.draggable.applyBounds( { minX: this.minX, maxX: this.maxX } );
        },

        /**
         * enable the functionality of scroller, the contents of the container goes beyond the bounds set
         */
        enable () {
            this.isEnabled = true;
            this.draggable.enable();
            this.refreshScrollBarWidth();
        },

        /**
         * Ensure the size of the scroll bar accurately reflects the amount of scroll required
         */
        refreshScrollBarWidth () {
            const unconstrainedWidth = (this.outerContainer.clientWidth - SCROLL_PADDING) * (this.outerContainer.clientWidth / this.innerContainer.scrollWidth);
            this.scrollBarWidth = (unconstrainedWidth < MIN_SCROLL_BAR_WIDTH) ? MIN_SCROLL_BAR_WIDTH : unconstrainedWidth;  //don't make the scroll bar to small
            this.$nextTick(() => { //to ensure the scrollBarWidth change has occured
                this.scrollBarXFactor = (this.outerContainer.clientWidth - this.scrollBar.clientWidth - SCROLL_PADDING) / (this.innerContainer.scrollWidth - this.outerContainer.clientWidth);
            });
        },

        /**
         * disable all the functionality of scroller, not enough in the container to be needed
         */
        disable () {
            this.isEnabled = false;
            this.draggable.disable();
        },

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
        },

        /**
         * Scroller has been activated, so show the scroll bar to track scroll progress
         */
        onStart () { 
            gsap.to(this.scrollBar, { alpha: 1, duration: 0.3 } );
        },

        /**
         * Keep the scroll bar accurate with the current scroll position
         * And disable the previous / next buttons when at then scroll bounds
         */
        onUpdate () {
            gsap.set(this.scrollBar, { x: -(gsap.getProperty(this.innerContainer, 'x') * this.scrollBarXFactor) } );
            this.currentXPos = gsap.getProperty(this.innerContainer, 'x');
        },
        
        /**
         * Fade out the scroll bar, when no longer moving
         */
        onComplete () {
            gsap.to(this.scrollBar, { alpha: 0, duration: 0.3 } );
        }, 

        /**
         * Move the scroller to the left
         */
        onPreviousButtonSelected () {
            const xTarget = gsap.getProperty(this.innerContainer, 'x') + this.outerContainer.clientWidth;
            const constrainedXTarget = (xTarget > this.maxX) ? this.maxX : xTarget; //ensure doesn't move beyond the container bounds
            this.moveInnerContainer(constrainedXTarget);
        },

        /**
         * Move the scroller to the right
         */
        onNextButtonSelected () {
            const xTarget = gsap.getProperty(this.innerContainer, 'x') - this.outerContainer.clientWidth;
            const constrainedXTarget = (xTarget < this.minX) ? this.minX : xTarget; //ensure doesn't move beyond the container bounds
            this.moveInnerContainer(constrainedXTarget);
        },

        /**
         * Any changes to the element size are tracked by ResizeObserver so can update dimensions
         */
        onContainerResize () {
            this.refresh();
        },

    }

}
</script>

<template>
    <div class="scroller" :style="{ height: `${height}px` } ">
        <div v-if="hasButtons" class="scroller__button-container">
            <button 
                v-if="isEnabled"
                class="button" 
                :disabled="currentXPos >= 0"
                @click.prevent="onPreviousButtonSelected" 
                @keyup.enter="onPreviousButtonSelected" 
            >
                &lt;
            </button>
        </div>
        <div class="scroller__outer-container" data-scroller-outer-container>
            <resize-observer @notify="onContainerResize"/>
            <div class="scroller__inner-container" data-scroller-inner-container>
                <slot></slot>
            </div>
            <div class="scroller-bar" data-scroller-scroll-bar :style="{ width: `${scrollBarWidth}px` } "></div>
        </div>
        <div v-if="hasButtons" class="scroller__button-container">
            <button 
                v-if="isEnabled"
                class="button" 
                :disabled="currentXPos <= minX"
                @click.prevent="onNextButtonSelected" 
                @keyup.enter="onNextButtonSelected" 
            >
             &gt;
            </button>
        </div>
    </div>
</template>

<style lang="scss" scoped>

@import 'www/static/css/core/base/_variables.scss';

.scroller {
    display: flex;
    align-items: flex-start;
}

.scroller__button-container {
    flex: 0 0 40px;
    height: 100%;
}

.button {
    width: 100%;
    height: 100%;
    margin: 0px auto;
    background: none;
    font-weight: bold;
    font-size: 1.5rem;
    color: #4f4f4f;
}

.scroller__outer-container {
    flex: 1 1 auto;
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    margin: 0;
    padding: 0;
    white-space: nowrap;
}

.scroller__inner-container {
    position: relative;
    height: 100%;
    margin: 0;
    padding: 0;
}

.scroller-bar {
    position: absolute;
    left: 5px;
    bottom: 0px;
    width: 120px; //will be updated relative to scroll amount with this.scrollBarWidth
    height: 3px;
    background: $content-ui-foreground;
}

</style>