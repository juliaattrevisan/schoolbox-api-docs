<script setup lang="ts">
/**
 * A single search result that be selected / unselected with a checkbox status
 */

// import { escapeRegExp } from "@js/util/jsUtils";
import type { Result } from "./types/Result";

interface Props {
    selected: Result[]; // the selected results
    results: Result[]; // the results returned from the search
    result: Result; // the result to display
}

const props = defineProps<Props>();

const emit = defineEmits<{
    select: [result: Result];
}>();

/**
 * Add in the code if it's available otherwise just show the title
 * @returns { String }
 */
// const title = window.Vue.computed(() => {
//     if (props.result.code && props.result.code !== "") {
//         return `[${props.result.code}] ${props.result.name}`;
//     } else {
//         return props.result.name;
//     }
// });

/**
 * To highlight any search selections in the code / title
 * @returns { HTML }
 */
// const highlightTitleSearch = window.Vue.computed(() => highlightSearchInElement(title.value));

/**
 * To highlight where the search string matches within the settings result
 * (?![^mark>]*mark>) is used to check we are not inside an element node
 *
 * @params { String } the string to find all instanced of the current search string in
 *
 * @returns { String } string for v-html with <mark></mark> wrapped around each found result
 */
// function highlightSearchInElement(toHighlight) {
//     let highlighted = toHighlight;
//     searchStore.queryWordList.forEach((wordInSearch) => {
//         highlighted = highlighted.replace(RegExp(`${escapeRegExp(wordInSearch)}(?![^mark>]*mark>)`, "ig"), (match) => {
//             return `<mark>${match}</mark>`;
//         });
//     });
//     return highlighted;
// }

/**
 * To determine whether the checkbox is currently selected
 * based on whether it's in the selected list
 * @returns {boolean}
 */
const isSelected = window.Vue.computed(() => {
    return props.selected.some((result) => result.id === props.result.id);
});

/**
 * To toggle the selected state of the result
 */
function onSelected() {
    emit("select", props.result);
}
</script>

<template>
    <button class="search-result" :title="result.name" @click.prevent="onSelected">
        <div class="checklist">
            <input :id="`checkbox-${result.id}`" type="checkbox" :checked="isSelected" />
            <label :for="`checkbox-${result.id}`"></label>
        </div>
        <div>
            <!-- <span v-html="highlightTitleSearch"></span> -->
            <span>{{ result.name }}</span>
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
