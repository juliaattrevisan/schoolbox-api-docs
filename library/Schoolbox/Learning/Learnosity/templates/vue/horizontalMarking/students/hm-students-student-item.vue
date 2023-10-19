<script setup lang="ts">
/**
 * Contains a students response to an item, (only one item is shown at a time)
 */

import type { Item } from "../types/Item";
import { useItemsStore } from "../store/hmItemsStore";

interface Props {
    item: Item; //
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const itemsStore = useItemsStore();
const itemEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into

/**
 * To display the current item
 */
const display = window.Vue.computed(() => {
    return props.item.reference === itemsStore.activeItem.reference ? "block" : "none";
});

/**
 * Add the response to the item to our element
 */
window.Vue.onMounted(() => {
    const response = props.container.querySelector(`[data-reference="${props.item.reference}"]`) as HTMLDivElement;
    itemEl.value?.appendChild(response);
});
</script>

<template>
    <div ref="itemEl" class="item"></div>
</template>

<style scoped>
.item {
    display: v-bind(display);
}
</style>
