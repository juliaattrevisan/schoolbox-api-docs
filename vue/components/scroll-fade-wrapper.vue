<script setup lang="ts">
/**
 * Adds fading effects to the top and bottom of the scrollable content area
 * when there is more content available to scroll to
 */
interface Props {
    // The tag that will be used to wrap the content
    // Defaults to div, but exists to provide flexibility in case a different tag is desired
    wrapperTag?: string;
    // The selector for the scrollable content area (what is inserted to the slot)
    // This exists to allow you to use dynamic content in the slot that may not allow a ref to be placed on it
    slottedElementSelector?: string; // The selector for the scrollable content area
}

const props = withDefaults(defineProps<Props>(), {
    wrapperTag: "div",
    slottedElementSelector: "ul",
});

const scrollContainerRef = window.Vue.ref<HTMLElement | null>(null);

const showTopFade = window.Vue.ref(false);
const showBottomFade = window.Vue.ref(false);

window.Vue.onMounted(() => {
    if (!scrollContainerRef.value) return;
    // "as string" is for some reason required here... can't figure out why eslint think its [any]
    const scrollContainer = scrollContainerRef.value.querySelector(props.slottedElementSelector as string);
    if (!scrollContainer) return;

    const checkScroll = (): void => {
        if (!scrollContainer) return;
        showTopFade.value = scrollContainer.scrollTop > 0;
        showBottomFade.value = scrollContainer.scrollTop + scrollContainer.clientHeight < scrollContainer.scrollHeight;
    };

    // To respond to changes in the scroll position
    scrollContainer.addEventListener("scroll", checkScroll);

    // To respoond to changes in the scrollable content size
    const resizeObserver = new ResizeObserver(checkScroll);
    resizeObserver.observe(scrollContainer);

    checkScroll(); // Initial check

    window.Vue.onUnmounted(() => {
        scrollContainer.removeEventListener("scroll", checkScroll);
        resizeObserver.disconnect();
    });
});
</script>

<template>
    <component :is="props.wrapperTag" ref="scrollContainerRef" class="scroll-fade-container">
        <div v-show="showTopFade" class="scroll-fade-top"></div>
        <slot></slot>
        <div v-show="showBottomFade" class="scroll-fade-bottom"></div>
    </component>
</template>

<style scoped>
.scroll-fade-container {
    overflow: hidden;
    position: relative;
}

.scroll-fade-top,
.scroll-fade-bottom {
    background: linear-gradient(to bottom, rgba(0, 0, 0, 0.2), transparent);
    height: 40px;
    left: 0;
    pointer-events: none;
    position: absolute;
    right: 0;
    z-index: var(--sbx-depth-ui-overlay);
}

.scroll-fade-top {
    top: 0;
}

.scroll-fade-bottom {
    background: linear-gradient(to top, rgba(0, 0, 0, 0.2), transparent);
    bottom: 0;
}
</style>
