<script setup lang="ts">
/**
 * A single search result that can be selected / unselected with a checkbox/radio status
 */

import ShowMatches from "@schoolboxVue/components/search/show-matches.vue";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";

interface Props {
    query: string; // the search query
    allowPartialMatches?: boolean; // whether to allow partial matches, so can match part of a word
    selected: SearchSelectItem[]; // the selected items
    result: SearchSelectItem; // the result to display
    isSingleSelect: boolean; // whether only one result can be selected, to vary the input type
}
const props = defineProps<Props>();

const emit = defineEmits<{
    add: [items: SearchSelectItem[]];
    remove: [items: SearchSelectItem[]];
}>();

/**
 * To determine whether the radio/checkbox is currently selected
 * based on whether it's in the selected list
 * @returns - whether the result is selected
 */
const isSelected = window.Vue.computed(() => {
    return props.selected.some((result) => result.id === props.result.id);
});

/**
 * To toggle the selected state of the result
 */
function toggle(): void {
    if (isSelected.value && !props.isSingleSelect) {
        emit("remove", [props.result]);
    } else {
        emit("add", [props.result]);
    }
}

/**
 * To toggle the selected state of the result
 * @param event - the click event
 */
function onClick(event?: MouseEvent): void {
    if (!event || !event.target) {
        return;
    }
    // ignore input label and mark clicks, which get picked up due to the click listener on the parent div
    if ((event.target as HTMLElement).nodeName === "LABEL" || (event.target as HTMLElement).nodeName === "MARK") {
        return;
    }
    toggle();
}
</script>

<template>
    <li ref="resultEl" class="ss-results-item" :data-test="`ss-results-item-container-${props.result.id}`">
        <div class="checklist sbx-checkbox-radio-container">
            <input
                v-if="props.isSingleSelect"
                :id="`search-select-result-input-${result.id}`"
                class="f-no-margins"
                type="radio"
                :checked="isSelected"
                :data-test="`ss-results-item-radio-${props.result.id}`"
                @click.stop="onClick"
            />
            <input
                v-else
                :id="`search-select-result-input-${result.id}`"
                class="f-no-margins"
                type="checkbox"
                :checked="isSelected"
                :data-test="`ss-results-item-checkbox-${props.result.id}`"
                @click.stop="onClick"
            />
            <show-matches
                tag="label"
                :for="`search-select-result-input-${result.id}`"
                :text="props.result.name"
                :query="props.query"
                :allow-partial-matches="props.allowPartialMatches"
                :data-test="`ss-results-item-label-${props.result.id}`"
                class="f-no-margins"
            />
        </div>
    </li>
</template>
