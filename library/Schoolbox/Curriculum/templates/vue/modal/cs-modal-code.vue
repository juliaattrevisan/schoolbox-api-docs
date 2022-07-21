<script setup>

/**
 * A curriculum code to select
 */

import { computed } from 'vue';

const props = defineProps({
    /**
     * the assigned code to display
     */
    code: {
        type: Object,
        required: true,
        default() {
            return {
                id: '0',
                name: '-',
                children: [],
            }
        }
    },
});

/**
 * To determine whether we need to display further child nodes within for this code
 *
 * @returns { Boolean }
 */
const hasChildren = computed(() => props.code.children && props.code.children.length > 0);

/**
 * To determine whether we need to display further child nodes within for this code
 *
 * @returns { HTML }
 */
const highlightTitleSearch = computed(() => {
    // return highlightSearchInElement(props.result.name);
    return props.code.name;
});

/**
 * To highlight where the search string matches within the settings result
 * (?![^>]*>) is used to check we are not inside an element node 
 *
 * @params { String } the string to find all instanced of the current search string in
 *
 * @returns { String } string for v-html with <mark></mark> wrapped around each found result
 */
// function highlightSearchInElement (toHighlight) {
//     let highlighted = toHighlight;
//     props.queryWordList.forEach((wordInSearch) => {
//         highlighted = highlighted.replace(RegExp(`${escapeRegExp(wordInSearch)}(?![^>]*>)`, 'ig'), (match) => {
//             return `<mark>${match}</mark>`;
//         });
//     });
//     return highlighted;
// }

</script>

<template>
   <li>
        <div class="code-container">
            <div class="checklist">
                <input 
                    :id="`checkbox-${props.code.id}`"
                    type="checkbox"
                    :checked="false"
                />
                <label :for="`checkbox-${props.code.id}`"></label>
            </div>
            <span v-html="highlightTitleSearch"></span>
        </div>
        <ul v-if="hasChildren">
            <cs-modal-code
                v-for="code in props.code.children"
                :key="props.code.id"
                :code="code"
            />
       </ul>
   </li>
</template>

<style scoped>

.code-container {
    /* background: white; */
    display: flex;
    /* font-size: 0.825rem;
    margin: 0;
    padding: 0.5rem 0.75rem;
    text-align: left;
    transition: background 0.2s;
    width: 100%;
    &:hover {
        background: var(--content-ui-hover);
    } */
}

ul {
    list-style: none;
}

</style>