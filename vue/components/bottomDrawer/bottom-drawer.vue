<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To display content that appears when requested from the bottom of the window.
 * Usually contains actions in response to selections on the page, for example choosing some users to edit.
 * Once +1 users is selected the drawer will slide in from the bottom of the window.
 */
export default {
    props: {
        /**
         * When true will slide in the drawer from the bottom of the window
         */
        showDrawer: {
            type: Boolean,
            required: true,
            default: false,
        },
        /**
         * To unanchor the drawer from the bottom of the browser, usually on smaller widths.
         * This is usually when there's multiple columns of content in the bottom-drawer, so it
         * takes up too much height on smaller devices to still be position: fixed
         */
        isAlwaysAnchored: {
            type: Boolean,
            required: false,
            default: true,
        },
        /**
         * Width that the drawer will unanchor and sit position: static in the page instead
         */
        unanchorWidth: {
            type: Number,
            required: false,
            default: 1000,
        },
    },

    data() {
        return {
            width: "", // as the drawer is position: fixed, need to use ResizeObserver to track the parent containers width
            left: "", // as the drawer is position: fixed, need to use ResizeObserver to track the parent containers left position
            position: "", // can be fixed/static when isAlwaysAnchored is set to false
        };
    },

    mounted() {
        const resizeObserver = new ResizeObserver(() => this.updatePosition());
        resizeObserver.observe(this.$el);
    },

    methods: {
        /**
         * To keep the width and left position of the bottom-drawer the same as the parent container
         * which will change depending on whether the side menu is opened/closed, browser resized etc
         * And to also 'unanchor' the drawer from the bottom of the browser window when required
         */
        updatePosition() {
            const elementRect = this.$el.getBoundingClientRect();
            this.width = `${elementRect.width}px`;
            this.left = `${elementRect.x}px`;
            this.position = elementRect.width <= this.unanchorWidth && !this.isAlwaysAnchored ? `static` : `fixed`;
        },
    },
};
</script>

<template>
    <div>
        <transition name="slide">
            <div v-show="showDrawer" class="bottom-drawer-container">
                <!-- @slot Default slot, put whatever HTML markup you like in here -->
                <slot></slot>
            </div>
        </transition>
    </div>
</template>

<style>
.bottom-drawer-container {
    background: white;
    bottom: 0px;
    box-shadow: 0px -2px 6px 0px rgba(0, 0, 0, 0.1);
    height: auto;
    left: v-bind(left);
    padding: 1rem;
    position: v-bind(position);
    width: v-bind(width);
    z-index: var(--sbx-depth-ui-overlay);
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
