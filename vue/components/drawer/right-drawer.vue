<script>

/**
 * To display content that appears when requested from the right of the window.
 * Is currently used to bulk select Classes within the Import Course into Class page
 */
export default {

    components: {
        ResizeObserver //to track any size changes to the parent container
    },

    props: {
        /**
         * When true will slide in the drawer from the right of the window
         */
        showDrawer: {
            type: Boolean,
            required: true,
            default: false,
        },
    },

    emits: ['close'],

    methods: {

        /**
         * Close the drawer
         */
        onCloseSelected () {
            this.$emit('close');
        },

    }

}

</script>

<template>
    <div>
        <transition name="slide">
            <div v-show="showDrawer" class="right-drawer-container">
                <button class="close-button icon-close" @click.prevent="onCloseSelected" @keyup.enter="onCloseSelected" :disabled="saveRows_isProcessing" aria-label="Close pop out"></button>
                <!-- @slot Default slot, put whatever HTML markup you like in here -->
                <slot></slot>
            </div>
        </transition>
    </div>
</template>

<style lang="scss" scoped>

.right-drawer-container {
    background: white;
    box-shadow: 0px -2px 6px 0px rgba(0, 0, 0, 0.1);
    height: 100%;
    padding: 3rem 1rem 1rem 1rem;
    position: fixed;
    right: 0; 
    top: 0;
    /*width: v-bind(width); */
    width: auto; 
}

.close-button {
    color: var(--content-ui-foreground);
    font-size: 1.5rem;
    padding: 2px 2px 2px 10px;
    position: absolute;
    right: 1rem;
    top: 1rem;
}

.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease-out;
}
.slide-enter-active {
    transform: translateX(100%);
}
.slide-enter-to {
    transform: translateX(0%);
}
.slide-leave-to {
    transform: translateX(100%);
}

</style>
