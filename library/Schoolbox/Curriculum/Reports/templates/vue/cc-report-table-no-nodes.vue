<script setup lang="ts">
/**
 * Displayed when show only gaps is selected, and there are no nodes to display.
 */

import { useCoursesStore } from "./store/ccCoursesStore";
import { useFiltersStore } from "./store/ccFiltersStore";

interface Props {
    tbodyEl: HTMLTableSectionElement | null;
}
const props = defineProps<Props>();

const coursesStore = useCoursesStore();
const filtersStore = useFiltersStore();
const isEmpty = window.Vue.ref(false);

/**
 * When the component is mounted, observe the table body for changes to determine if it is empty and display the message.
 * We use this to avoid any more expensive checks, avoiding needing to loop through the entire node on any change.
 */
window.Vue.onMounted(async () => {
    await window.Vue.nextTick(); // allow the tbodyEl to be rendered
    const lrnObserver = new MutationObserver((mutationsList) => {
        if (!filtersStore.onlyShowGaps) {
            isEmpty.value = false;
            return;
        }
        mutationsList.forEach(() => {
            if (!isEmpty.value && props.tbodyEl?.children && props.tbodyEl.children.length === 0) {
                isEmpty.value = true;
            } else if (isEmpty.value && props.tbodyEl?.children && props.tbodyEl.children.length > 1) {
                isEmpty.value = false;
            }
        });
    });
    lrnObserver.observe(props.tbodyEl as HTMLTableSectionElement, {
        attributes: false,
        childList: true,
        subtree: false,
    });
});
</script>

<template>
    <tr v-if="isEmpty">
        <td :colspan="coursesStore.totalColumnCount">
            <em>No curriculum has a total usage count of 0.</em>
        </td>
    </tr>
</template>

<style scoped></style>
