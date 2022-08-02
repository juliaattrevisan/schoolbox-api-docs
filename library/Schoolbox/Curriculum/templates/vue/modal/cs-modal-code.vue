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
     * to gradually add more paddingLeft to indicate node level depth
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
 * The top level node can't be selected, so disable it
 *
 * @returns { String }
 */
const pointerEvents = computed(() => props.code.isFirstLevel ? 'none' : 'auto');

/**
 * To determine whether the checkbox is currently selected based 
 * on whether it's in the selected list
 *
 * @returns { Boolean }
 */
const isCodeSelected = computed(() => { 
    return modalSelectedCodesStore.codes.some((code) => props.code.id === code.id);
});

/**
 * Add or remove the code from the current modal selection
 */
function onSelected () {
    isCodeSelected.value ? modalSelectedCodesStore.remove(props.code) : modalSelectedCodesStore.add(props.code);
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
const title = computed(() => {
    if (props.code.code && props.code.code !== '') {
        return `[${props.code.code}] ${props.code.name}`;
    } else {
        return props.code.name;
    }
});

/**
 * To highlight any search selections in the code / title
 *
 * @returns { HTML }
 */
const highlightTitleSearch = computed(() => {
    if (modalActiveCodesStore.isSearchActive) {
        return highlightSearchInElement(title.value);
    } else {
        return title.value;
    }
});

/**
 * To highlight any search selections in the breadcrumbs
 *
 * @returns { HTML }
 */
const highlightBreadcrumbSearch = computed(() => {
    if (modalActiveCodesStore.isSearchActive && props.code.breadcrumb) {
        return highlightSearchInElement(props.code.breadcrumb);
    } else {
        return '';
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
        highlighted = highlighted.replace(RegExp(`${escapeRegExp(wordInSearch)}(?![^mark>]*mark>)`, 'ig'), (match) => {
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
    if (modalActiveCodesStore.isSearchActive) { //don't style when using search, as all the codes are on one level
        return '';
    } else if (modalActiveCodesStore.isOnFirstNode && props.depth === 0 || modalActiveCodesStore.isOnSecondNode && props.depth === 0) { 
        return 'parent-container';
    } else if (modalActiveCodesStore.isOnFirstNode && props.depth === 1 || modalActiveCodesStore.isOnSecondNode && props.depth === 1 && props.code.children.length > 0) {
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
            :style="{ paddingLeft: paddingLeft, pointerEvents: pointerEvents }" 
            @click.prevent="onSelected"
            @keyup.enter="onSelected"
        >
            <div class="checklist" v-if="!props.code.isFirstLevel">
                <input 
                    :id="`checkbox-${props.code.id}`"
                    type="checkbox"
                    :checked="isCodeSelected"
                />
                <label :for="`checkbox-${props.code.id}`"></label>
            </div>
            <div>
                <span v-html="highlightTitleSearch"></span>
                <aside v-if="modalActiveCodesStore.isSearchActive" v-html="highlightBreadcrumbSearch"></aside>
            </div>
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

ul {
    list-style: none; //don't use any dots
    margin: 0; //and spacing is controlled by props.depth
}

aside {
    font-size: 0.725rem;
    font-style: italic;
}

</style>