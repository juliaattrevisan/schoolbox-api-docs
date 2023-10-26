<script setup lang="ts">
/**
 * Contains a students mark linked to an item, (only one item is shown at a time)
 */

import type { Item } from "../types/Item";
import { useItemsStore } from "../store/hmItemsStore";

interface Props {
    item: Item; // the item to display
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const itemsStore = useItemsStore();
const markEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into

/**
 * To display the current item
 */
const display = window.Vue.computed(() => {
    return props.item.reference === itemsStore.activeItem.reference ? "block" : "none";
});

/**
 * Move the item mark element from the entire quiz response into our own container
 */
window.Vue.onMounted(() => {
    const mark = props.container.querySelector(`[data-reference-title="${props.item.reference}"]`) as HTMLDivElement;
    if (mark) {
        (mark.firstChild as HTMLElement).nodeValue = ""; // clear the title, just leaving the mark design
        markEl.value?.appendChild(mark);
    }
});
</script>

<template>
    <div ref="markEl" class="lrn learnosity-report session-detail-by-item mark"></div>
</template>

<style scoped>
.mark {
    display: v-bind(display);
}
</style>
