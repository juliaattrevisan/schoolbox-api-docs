<script setup>

/**
 * A curriculum code to select
 */

import { computed } from 'vue';
import { useActiveCodesStore } from '../store/mcActiveCodesStore';
// import { useModalSelectedCodesStore } from '../store/mcSelectedCodesStore';
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

const activeCodesStore = useActiveCodesStore();
// const modalSelectedCodesStore = useModalSelectedCodesStore();

/**
 * to gradually and more paddingLeft to indicate node level depth
 *
 * @returns { Number }
 */
const paddingLeft = computed(() => `calc(${props.depth * 2}rem + 0.5rem)`);

/**
 * If there's a code available, which means it can be selected
 *
 * @returns { Boolean }
 */
const hasCode = computed(() => props.code.code && props.code.code !== '');

/**
 * To help distinguish those codes that aren't selectable,
 * we disable and remove the default button colour
 *
 * @returns { Boolean }
 */
// const isDisabled = computed(() => {
//     if (!hasCode.value && props.depth === 0 && !activeCodesStore.isSearchActive) {
//         return true;
//     } else if (!hasCode.value && activeCodesStore.isOnFourthNode) { //cant select any further down from the fourth level
//         return true;
//     } else {
//         return false;
//     }
// });

/**
 * Only show the selected node and its direct children when we are on the fourth node
 * This is so we don't flood the DOM with very large Curriculum renders
 *
 * @returns { Boolean }
 */
const isVisible = computed(() => props.depth <= 1 || activeCodesStore.isOnFourthNode);

/**
 * To determine whether the checkbox is currently selected based
 * on whether it's in the selected list
 *
 * @returns { Boolean }
 */
// const isCodeSelected = computed(() => {
//     return modalSelectedCodesStore.codes.some((code) => props.code.id === code.id);
// });

/**
 * Add or remove the code from the current modal selection,
 * or if there's no code, use the code to traverse to the next nodel level
 * as an alternative to using the selects in mc-header-select
 */
// function onSelected () {
//     if (hasCode.value) {
//         isCodeSelected.value ? modalSelectedCodesStore.remove(props.code) : modalSelectedCodesStore.add(props.code);
//     } else {
//         activeCodesStore.setNextSelectedNode(props.code.id);
//     }
// }

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
    if (hasCode.value) {
        return `[${props.code.code}] ${props.code.name}`;
    } else {
        return `${props.code.name}`;
    }
});

/**
 * To highlight any search selections in the code / title
 *
 * @returns { HTML }
 */
const highlightTitleSearch = computed(() => {
    if (activeCodesStore.isSearchActive) {
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
    if (activeCodesStore.isSearchActive && props.code.breadcrumb) {
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
    activeCodesStore.queryWordList.forEach((wordInSearch) => {
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
    if (activeCodesStore.isSearchActive) { //don't style when using search, as all the codes are on one level
        return '';
    } else if (props.depth === 0) {
        return 'parent-container';
    } else {
        '';
    }
});

</script>

<template>
    <li v-if="isVisible" :class="containerClass">
        <div>
            <span v-html="highlightTitleSearch"></span>
            <aside v-if="activeCodesStore.isSearchActive" v-html="highlightBreadcrumbSearch"></aside>
        </div>
        <ul v-if="hasChildren">
            <mc-code
                v-for="code in props.code.children"
                :key="code.id"
                :code="code"
                :depth="props.depth+1"
            />
        </ul>
        <!-- <button
            :class="`code-button`"
            :disabled="isDisabled"
            @click.prevent="onSelected"
            @keyup.enter="onSelected"
        >
            <div class="checklist" v-if="hasCode">
                <input
                    :id="`checkbox-${props.code.id}`"
                    type="checkbox"
                    :checked="isCodeSelected"
                />
                <label :for="`checkbox-${props.code.id}`"></label>
            </div>
            <div>
                <span v-html="highlightTitleSearch"></span>
                <aside v-if="activeCodesStore.isSearchActive" v-html="highlightBreadcrumbSearch"></aside>
            </div>
        </button>
        <ul v-if="hasChildren">
            <mc-code
                v-for="code in props.code.children"
                :key="code.id"
                :code="code"
                :depth="props.depth+1"
            />
       </ul> -->
   </li>
</template>

<style lang="scss" scoped>

.parent-container {
    > button span {
        font-size: 1.0625rem;
        font-weight: bold;
    }
}

.code-button {
    background: none;
    display: flex;
    font-size: 1rem;
    margin: 0;
    padding: 0.5rem 0.75rem 0.5rem v-bind(paddingLeft); //increment the padding left based on the node depth
    text-align: left;
    transition: background 0.2s;
    width: 100%;
    &:hover {
        background: var(--content-ui-hover);
    }
    &:disabled {
        color: #222222;
        opacity: 1;
    }
    @media (max-width: 700px) {
        font-size: 0.8rem;
        padding: 0.25rem;
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