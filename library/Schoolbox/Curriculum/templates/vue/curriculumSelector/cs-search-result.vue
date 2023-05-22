<script setup>
/**
 * A single search result that be selected / unselected with a checkbox status
 */

import { computed } from "vue";
import { escapeRegExp } from "@js/util/jsUtils";
import { useSearchStore } from "./store/csSearchStore";
import { useSelectedNodesStore } from "./store/csSelectedNodesStore";

const searchStore = useSearchStore();
const selectedNodesStore = useSelectedNodesStore();

const props = defineProps({
    result: {
        type: Object,
        required: true,
        default: () => {
            return {
                id: 0,
                code: "",
                name: "",
            };
        },
    },
});

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = window.Vue.computed(() => {
    if (props.result.code && props.result.code !== "") {
        return `[${props.result.code}] ${props.result.name}`;
    } else {
        return props.result.name;
    }
});

/**
 * To highlight any search selections in the code / title
 *
 * @returns { HTML }
 */
const highlightTitleSearch = window.Vue.computed(() => highlightSearchInElement(title.value));

/**
 * To highlight where the search string matches within the settings result
 * (?![^mark>]*mark>) is used to check we are not inside an element node
 *
 * @params { String } the string to find all instanced of the current search string in
 *
 * @returns { String } string for v-html with <mark></mark> wrapped around each found result
 */
function highlightSearchInElement(toHighlight) {
    let highlighted = toHighlight;
    searchStore.queryWordList.forEach((wordInSearch) => {
        highlighted = highlighted.replace(RegExp(`${escapeRegExp(wordInSearch)}(?![^mark>]*mark>)`, "ig"), (match) => {
            return `<mark>${match}</mark>`;
        });
    });
    return highlighted;
}

/**
 * To determine whether the checkbox is currently selected
 * based on whether it's in the selected list
 *
 * @returns { Boolean }
 */
const isNodeSelected = window.Vue.computed(() => {
    return selectedNodesStore.nodes.some((node) => props.result.id === node.id);
});

/**
 * To determine the display of the checkbox based on whether
 * the node is currently selected
 */
function onSelected() {
    isNodeSelected.value ? selectedNodesStore.remove(props.result) : selectedNodesStore.add(props.result);
}
</script>

<template>
    <button class="search-result" :title="title" @click.prevent="onSelected">
        <div class="checklist">
            <input :id="`checkbox-${result.id}`" type="checkbox" :checked="isNodeSelected" />
            <label :for="`checkbox-${result.id}`"></label>
        </div>
        <div>
            <span v-html="highlightTitleSearch"></span>
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
    margin: 0;
}

input[type="checkbox"]:not(.plain) + label {
    margin: 0;
}

aside {
    font-size: 0.725rem;
    font-style: italic;
}
</style>
