<script setup lang="ts">
/**
 * Displayed when show only gaps is selected, and there are no nodes to display.
 */

import { useReportStore } from "./store/ccReportStore";
import { useFiltersStore } from "./store/ccFiltersStore";

interface Props {
    tbodyEl: HTMLTableSectionElement | null;
}
const props = defineProps<Props>();

const reportStore = useReportStore();
const filtersStore = useFiltersStore();
const isEmpty = window.Vue.ref(false);

/**
 * When the component is mounted, observe the table body for changes to determine if it is empty.
 * We use this to avoid any more expensive checks, avoiding needing to loop through the nodes.
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
                console.warn(props.tbodyEl.children.length);
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
        <td :colspan="reportStore.totalColumnCount" class="t-align-center">
            <em>No curriculum has a total usage count of 0.</em>
        </td>
    </tr>
</template>

<style scoped></style>
