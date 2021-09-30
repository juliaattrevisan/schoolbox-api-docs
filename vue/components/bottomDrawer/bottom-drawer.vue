<script>

/**
 * To display content that appears when requested from the bottom of the window
 * Usually contains actions in response to selections on the page, for example choosing some users to edit
 * Once +1 users is selected the drawer will slide in from the bottom of the window
 */

import { ResizeObserver } from 'vue-resize';
import 'vue-resize/dist/vue-resize.css';


export default {

    components: {
        ResizeObserver //to track any size changes to the parent container
    },

    props: {
        showDrawer: { //when true will slide in the drawer from the bottom of the window
            type: Boolean,
            required: true,
        }
    },

    data () {
        return { 
            width: '', //as the drawer is position: fixed, need to use ResizeObserver to track the parent containers width
            left: '' //as the drawer is position: fixed, need to use ResizeObserver to track the parent containers left position
        }
    },

    mounted () {
        this.updatePosition();
    },

    methods: {

        /**
         * Any changes to the element size are tracked by ResizeObserver so can update dimensions
         */
        onContainerResize () {
            this.updatePosition();
        },

        /**
         * To keep the width and left position of the bottom-drawer the same as the parent container
         * which will change depending on whether the side menu is opened/closed, browser resized etc
         */
        updatePosition () {
            this.width = `${ this.$el.getBoundingClientRect().width }px`;
            this.left = `${ this.$el.getBoundingClientRect().x }px`;
        }

    }

}

</script>

<template>
    <div>
        <resize-observer @notify="onContainerResize"/>
        <transition name="slide">
            <div v-show="showDrawer" class="bottom-drawer-container" :style="{ width: width, left: left }">
                <slot></slot>
            </div>
        </transition>
    </div>
</template>

<style lang="scss">

.bottom-drawer-container {
    position: fixed;
    width: 100%; //will be updated by the width data prop
    height: auto;
    left: 0px; //will be updated by the left data prop
    bottom: 0px;
    padding: 1rem;
    background: white;
    box-shadow: 0px -2px 6px 0px rgba(0, 0, 0, 0.1);
}

.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease-out;
}
.slide-enter-active {
    transform: translateY(100%);
}
.slide-enter-to {
    transform: translateY(0%);
}
.slide-leave-to {
    transform: translateY(100%);
}

</style>
