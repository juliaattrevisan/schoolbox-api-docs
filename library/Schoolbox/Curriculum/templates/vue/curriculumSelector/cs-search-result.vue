<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * A single search result that be selected / unselected with a checkbox status
 */

import ShowMatches from "@schoolboxVue/components/search/show-matches.vue";
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
    /**
     * so can only select a single node
     */
    isSingleSelect: {
        type: Boolean,
        required: false,
        default: false,
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
    if (props.isSingleSelect) {
        selectedNodesStore.addSingle(props.result);
    } else {
        isNodeSelected.value ? selectedNodesStore.remove(props.result) : selectedNodesStore.add(props.result);
    }
}
</script>

<template>
    <button class="search-result" :title="title" @click.prevent="onSelected">
        <div class="checklist">
            <input v-if="props.isSingleSelect" :id="`checkbox-${result.id}`" type="radio" :checked="isNodeSelected" />
            <input v-else :id="`checkbox-${result.id}`" type="checkbox" :checked="isNodeSelected" />
            <label :for="`checkbox-${result.id}`"></label>
        </div>
        <div>
            <show-matches tag="span" :text="title" :query="searchStore.query" />
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
