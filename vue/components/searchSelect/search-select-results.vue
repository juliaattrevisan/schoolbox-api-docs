<script setup lang="ts">
/**
 *
 */

import gsap from "gsap";

interface Props {
    results: Array<any>; // the results
}

const props = defineProps<Props>();

/**
 * to slide up and down the search results
 */
window.Vue.watch(
    () => searchStore.filteredResults,
    () => {
        window.Vue.nextTick(() => {
            const targetHeight = searchResultsEl.value.offsetHeight;
            gsap.to(searchResultsContainerEl.value, {
                duration: 0.3,
                height: targetHeight,
            });
        });
    }
);

/**
 * Only start searching once the keyword length is greater than the minimum
 */
window.Vue.watch(props.results, () => {
    window.Vue.nextTick(() => {
        const targetHeight = searchResultsEl.value.offsetHeight;
        gsap.to(searchResultsContainerEl.value, {
            duration: 0.3,
            height: targetHeight,
        });
    });
});
</script>

<template>
    <div></div>
</template>

<style scoped></style>
