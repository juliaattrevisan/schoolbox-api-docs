<script setup lang="ts">
/**
 * To display content that appears when requested from the bottom of the window.
 * Usually contains actions in response to selections on the page, for example choosing some users to edit.
 * Once +1 users is selected the drawer will slide in from the bottom of the window.
 */
interface Props {
    showDrawer?: boolean;
    isAlwaysAnchored?: boolean;
    unanchorWidth?: number;
    hasPadding?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    /**
     * When true will slide in the drawer from the bottom of the window
     */
    showDrawer: false,
    /**
     * To unanchor the drawer from the bottom of the browser, usually on smaller widths.
     * This is usually when there's multiple columns of content in the bottom-drawer, so it
     * takes up too much height on smaller devices to still be position: fixed
     */
    isAlwaysAnchored: true,
    /**
     * Width that the drawer will unanchor and sit position: static in the page instead
     */
    unanchorWidth: 1000,
    /**
     * Whether there should be padding around the content in the drawer (default true)
     */
    hasPadding: true,
});

const width = window.Vue.ref<string>(""); // as the drawer is position: fixed, need to use ResizeObserver to track the parent containers width
const left = window.Vue.ref<string>(""); // as the drawer is position: fixed, need to use ResizeObserver to track the parent containers left position
const position = window.Vue.ref<"" | "fixed" | "static">(""); // can be fixed/static when isAlwaysAnchored is set to false
const resizeObserver = window.Vue.ref<ResizeObserver | null>(null);
const rootContainer = window.Vue.ref<HTMLDivElement | null>(null);
const drawerContainer = window.Vue.ref<HTMLDivElement | null>(null);

/**
 * Return the drawer element when it is available in the DOM.
 * @returns The drawer element when mounted, otherwise null.
 */
function getDrawerElement(): HTMLDivElement | null {
    return drawerContainer.value instanceof HTMLDivElement ? drawerContainer.value : null;
}

/**
 * Remove any page-level offset previously applied for the drawer.
 */
function clearContainerOffset(): void {
    const containerEl = document.getElementById("container");
    if (containerEl === null) {
        return;
    }

    containerEl.style.removeProperty("--bottom-drawer-offset");
    containerEl.removeAttribute("data-has-bottom-drawer");
}

/**
 * Keep the main container bottom spacing aligned with the visible fixed drawer height.
 */
function updateContainerOffset(): void {
    const containerEl = document.getElementById("container");
    const drawerElement = getDrawerElement();

    if (containerEl === null || drawerElement === null) {
        return;
    }

    if (!props.showDrawer || position.value !== "fixed") {
        clearContainerOffset();
        return;
    }

    const drawerHeight = Math.ceil(drawerElement.getBoundingClientRect().height);
    containerEl.style.setProperty("--bottom-drawer-offset", `${drawerHeight}px`);
    containerEl.setAttribute("data-has-bottom-drawer", "true");
}

/**
 * Observe the drawer itself so content-height changes also refresh the container offset.
 */
function observeDrawerElement(): void {
    if (resizeObserver.value === null) {
        return;
    }

    const drawerElement = getDrawerElement();
    if (drawerElement !== null) {
        resizeObserver.value.observe(drawerElement);
    }
}

/**
 * Start observing both the root container and the drawer for layout changes.
 */
function initResizeObserver(): void {
    resizeObserver.value = new ResizeObserver(() => updatePosition());

    if (rootContainer.value instanceof HTMLElement) {
        resizeObserver.value.observe(rootContainer.value);
    }

    observeDrawerElement();
}

/**
 * Disconnect observers and clear any container spacing side effects.
 */
function destroyResizeObserver(): void {
    if (resizeObserver.value !== null) {
        resizeObserver.value.disconnect();
        resizeObserver.value = null;
    }

    clearContainerOffset();
}

/**
 * To keep the width and left position of the bottom-drawer the same as the parent container
 * which will change depending on whether the side menu is opened/closed, browser resized etc
 * And to also 'unanchor' the drawer from the bottom of the browser window when required
 */
function updatePosition(): void {
    if (!(rootContainer.value instanceof HTMLElement)) {
        return;
    }

    const elementRect = rootContainer.value.getBoundingClientRect();
    width.value = `${elementRect.width}px`;
    left.value = `${elementRect.x}px`;
    position.value = elementRect.width <= props.unanchorWidth && !props.isAlwaysAnchored ? `static` : `fixed`;
    updateContainerOffset();
}

window.Vue.watch(
    () => props.showDrawer,
    (): void => {
        void window.Vue.nextTick(() => {
            updatePosition();

            if (!props.showDrawer) {
                clearContainerOffset();
            }
        });
    },
);

window.Vue.watch(
    () => props.isAlwaysAnchored,
    (): void => {
        updatePosition();
    },
);

window.Vue.watch(
    () => props.unanchorWidth,
    (): void => {
        updatePosition();
    },
);

window.Vue.onMounted((): void => {
    updatePosition();
    initResizeObserver();
});

window.Vue.onBeforeUnmount((): void => {
    destroyResizeObserver();
});
</script>

<template>
    <div ref="rootContainer">
        <transition name="slide">
            <div
                v-show="props.showDrawer"
                ref="drawerContainer"
                class="bottom-drawer-container"
                :class="{ 'bottom-drawer-container--no-pad': !props.hasPadding }"
            >
                <!-- @slot Default slot, put whatever HTML markup you like in here -->
                <slot></slot>
            </div>
        </transition>
    </div>
</template>

<style lang="scss">
.bottom-drawer-container {
    background: var(--sbx-color-foreground-primary, #fff);
    border-radius: var(--sbx-config-container-roundness, 0) var(--sbx-config-container-roundness, 0) 0 0;
    bottom: 0px;
    box-shadow: 0px -2px 6px 0px rgba(0, 0, 0, 0.1);
    height: auto;
    left: v-bind(left);
    position: v-bind(position);
    width: v-bind(width);
    z-index: var(--sbx-depth-ui-overlay);

    &:not(.bottom-drawer-container--no-pad) {
        padding: 1rem;
    }
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
