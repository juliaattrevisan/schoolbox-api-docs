<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * A single search result that be selected / unselected with a checkbox status
 */

import ShowMatches from "@schoolboxVue/components/search/show-matches.vue";
import { useSearchStore } from "../store/mgSearchStore";

const searchStore = useSearchStore();

const props = defineProps({
    result: {
        type: Object,
        required: true,
        default: () => {
            return {
                id: 0,
                name: "",
            };
        },
    },
});

/**
 * To determine whether the checkbox is currently selected
 * based on whether it's in the selected list
 *
 * @returns { Boolean }
 */
const isSelected = window.Vue.computed(() => {
    return searchStore.selectedResults.some((selectedItem) => props.result.id === selectedItem.id);
});

/**
 * To determine the display of the checkbox based on whether
 * the result is currently selected
 */
function onSelected() {
    isSelected.value ? searchStore.removeResult(props.result) : searchStore.addResult(props.result);
}
</script>

<template>
    <button class="search-result" @click.prevent="onSelected">
        <div class="checklist">
            <input :id="`checkbox-${result.id}`" type="checkbox" :checked="isSelected" />
            <label :for="`checkbox-${result.id}`" :data-test="`manage-group-search-result-${result.id}`"></label>
        </div>
        <div>
            <show-matches tag="span" :text="props.result.name" :query="searchStore.query" />
        </div>
    </button>
</template>

<style lang="scss" scoped>
.search-result {
    background: white;
    display: flex;
    font-size: 0.825rem;
    margin: 0;
    padding: 0.5rem 0.75rem;
    text-align: left;
    transition: background 0.2s;
    width: 100%;
    &:hover {
        background: var(--content-ui-hover);
    }
}

.checklist {
    flex: 2rem 0 0; //ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it
}

input[type="checkbox"]:not(.plain) + label {
    margin: 0;
}

aside {
    font-size: 0.725rem;
    font-style: italic;
}
</style>
