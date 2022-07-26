<script setup>

/**
 * A curriculum code to select
 */

import { computed } from 'vue';
import { useModalActiveCodesStore } from '../store/csModalActiveCodesStore';
import { useModalSelectedCodesStore } from '../store/csModalSelectedCodesStore';
import { escapeRegExp } from '@js/util/jsUtils';


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
    /**
     * to gradually and more paddingLeft to indicate node level depth
     */
    depth: {
        type: Number, 
        required: true,
    },
});

const modalActiveCodesStore = useModalActiveCodesStore();
const modalSelectedCodesStore = useModalSelectedCodesStore();

/**
 * to gradually and more paddingLeft to indicate node level depth
 *
 * @returns { Number }
 */
const paddingLeft = computed(() => `calc(${props.depth * 2}rem + 0.5rem)`);

/**
 * To determine whether the checkbox is currently selected based on whether it's 
 * in the selected list
 *
 * @returns { Boolean }
 */
const isCodeSelected = computed(() => { 
    return modalSelectedCodesStore.codes.some((code) => props.code.id === code.id);
});

/**
 * To determine the display of the checkbox based on whether
 * the code is currently selected
 */
function onSelected () {
    if (isCodeSelected.value) {
        modalSelectedCodesStore.remove(props.code);
    } else {
        modalSelectedCodesStore.add(props.code);
    }
}

/**
 * To determine whether we need to display further child nodes within for this code
 *
 * @returns { Boolean }
 */
const hasChildren = computed(() => props.code.children && props.code.children.length > 0);

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const codeTitle = computed(() => {
    if (props.code.code && props.code.code !== '') {
        return `[${props.code.code}] ${props.code.name}`;
    } else {
        return props.code.name;
    }
});

/**
 * To determine whether we need to display further child nodes within for this code
 *
 * @returns { HTML }
 */
const highlightTitleSearch = computed(() => {
    if (modalActiveCodesStore.isSearchActive) {
        return highlightSearchInElement(codeTitle.value);
    } else {
        return codeTitle.value;
    }
});

/**
 * To highlight where the search string matches within the settings result
 * (?![^>]*>) is used to check we are not inside an element node 
 *
 * @params { String } the string to find all instanced of the current search string in
 *
 * @returns { String } string for v-html with <mark></mark> wrapped around each found result
 */
function highlightSearchInElement (toHighlight) {
    let highlighted = toHighlight;
    modalActiveCodesStore.queryWordList.forEach((wordInSearch) => {
        highlighted = highlighted.replace(RegExp(`${escapeRegExp(wordInSearch)}(?![^>]*>)`, 'ig'), (match) => {
            return `<mark>${match}</mark>`;
        });
    });
    return highlighted;
}

/**
 * Add some styling to help visually clarify the tree structure
 *
 * @returns { String }
 */
const containerClass = computed(() => {
    if (modalActiveCodesStore.isOnFirstNode && props.depth === 0 || modalActiveCodesStore.isOnSecondNode && props.depth === 0) {
        return 'parent-container';
    } else if (modalActiveCodesStore.isOnFirstNode && props.depth === 1 || modalActiveCodesStore.isOnSecondNode && props.depth === 1) {
        return 'first-child-container-with-border';
    } else if (modalActiveCodesStore.isOnThirdNode && props.depth === 0) {
        return 'first-child-container';
    } else {
        '';
    }
});

</script>

<template>
   <li :class="containerClass">
        <button 
            class="code-button" 
            :style="{ paddingLeft: paddingLeft }" 
            @click.prevent="onSelected"
            @keyup.enter="onSelected"
        >
            <div class="checklist">
                <input 
                    :id="`checkbox-${props.code.id}`"
                    type="checkbox"
                    :checked="isCodeSelected"
                />
                <label :for="`checkbox-${props.code.id}`"></label>
            </div>
            <span v-html="highlightTitleSearch"></span>
        </button>
        <ul v-if="hasChildren">
            <cs-modal-code
                v-for="code in props.code.children"
                :key="code.id"
                :code="code"
                :depth="props.depth+1"
            />
       </ul>
   </li>
</template>

<style lang="scss" scoped>

.parent-container {
    > button span { 
        font-size: 1.25rem;
        font-weight: bold;
    }
}
.first-child-container {
    > button span { 
        font-size: 1.0625rem;
        font-weight: bold;
    }
}

.first-child-container-with-border {
    @extend .first-child-container;
    border-top: 2px solid #aaa;
    > button {
        padding-top: 0.75rem;
    }
}

.code-button {
    background: none;
    display: flex;
    font-size: 1rem;
    margin: 0;
    padding: 0.5rem 0.75rem;
    transition: background 0.2s;
    width: 100%;
    &:hover {
        background: var(--content-ui-hover);
    } 
}

ul {
    list-style: none; //don't use any dots
    margin: 0; //and spacing is controlled by props.depth
}

</style>