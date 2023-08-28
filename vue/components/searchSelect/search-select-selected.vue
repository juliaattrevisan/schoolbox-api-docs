<script setup lang="ts">
/**
 * To list all the currently selected Curriculum codes
 */

import type { Result } from "./types/Result";
import SearchSelectSelectedItem from "./search-select-selected-item.vue";

interface Props {
    selected: Result[]; // the selected results
    formValueName: string; // the name of the form value
}
const props = defineProps<Props>();

const emit = defineEmits<{
    remove: [result: Result];
}>();

/**
 * to listen for any clears of the selected codes from outside the Vue app
 */
// window.Vue.onMounted(() => {
//     document.addEventListener("SEARCH_SELECT__UPDATE_SELECTION", (event) => {
//         if (selectEl.value.getAttribute("id") === event.detail.elementId) {
//             //just in case there are multiple on the page
//             const newSelection = JSON.parse(event.detail.selection);
//             selectedNodesStore.update(newSelection);
//         }
//     });
// });

/**
 * Add a comma separated string of the ids of the selected codes
 * for use in forms
 * @returns {string}
 */
const ids = window.Vue.computed(() => {
    return props.selected.join(",");
});

/**
 * To manually dispatch a <select> change event when there's been a change in the selected nodes
 * As this is being handled by Vue rather than user input, the change event won't fire otherwise
 * except when the <select> is first displayed in the DOM.
 * We listen for this in library/Schoolbox/Filter/templates/panel.twig
 */
// window.Vue.watch(
//     () => selectedNodesStore.nodesIds,
//     () => {
//         window.Vue.nextTick(() => {
//             //nextTick to ensure the dom has updated so we can access with the latest changes
//             const event = new Event("change");
//             selectEl.value.dispatchEvent(event);
//         });
//     }
// );

/**
 * To remove a selected search result
 * @param {Result} result - the result to remove
 */
function onRemoveItem(result: Result) {
    emit("remove", result);
}
</script>

<template>
    <div v-if="props.selected.length > 0" class="selected-nodes-container">
        <search-select-selected-item
            v-for="result in props.selected"
            :key="result.id"
            :result="result"
            @remove="onRemoveItem"
        />
    </div>
    <input :id="props.formValueName" :name="props.formValueName" type="hidden" tabindex="-1" :value="ids" />
</template>

<style lang="scss" scoped>
.selected-nodes-container {
    border: 1px solid var(--form-border-color);
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: -1px; //to butt up against the search input
    max-height: 133px;
    min-height: 50px;
    overflow: auto;
    padding: 0.5rem;
}

.no-codes-note {
    margin-top: 0.35rem;
}

select[data-search-select] {
    display: none;
}
</style>
