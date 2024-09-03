<script setup>
/**
 * A curriculum node to select to traverse further down the tree,
 * will also highlight any matches in the current search query when search is active
 */

import ShowMatches from "@schoolboxVue/components/search/show-matches.vue";
import { useNodesStore } from "../store/csNodesStore";
import { useModalViewNodesStore } from "../store/csModalViewNodesStore";
import { useModalSearchStore } from "../store/csModalSearchStore";

const props = defineProps({
    /**
     * the assigned node to display
     */
    node: {
        type: Object,
        required: true,
        default() {
            return {
                id: "0",
                name: "-",
                children: [],
            };
        },
    },
    /**
     * to gradually add more marginLeft to indicate node level depth
     */
    depth: {
        type: Number,
        required: true,
    },
    /**
     * the already computed node title
     */
    title: {
        type: String,
        required: true,
    },
    /**
     * to disable certain events when editing is taking place
     */
    isActive: {
        type: Boolean,
        required: true,
    },
});

const nodesStore = useNodesStore();
const modalViewNodesStore = useModalViewNodesStore();
const modalSearchStore = useModalSearchStore();

/**
 * To disable selection on certain sitatuions
 *
 * @returns { boolean }
 */
const isDisabled = window.Vue.computed(() => {
    if (props.isActive || nodesStore.isLoading) {
        // while the node is being updated
        return true;
    }
    return false;
});

/**
 * To distinguish the parent node
 *
 * @returns { Boolean }
 */
const isParentNode = window.Vue.computed(() => {
    if (props.depth === 0) {
        return true;
    } else {
        return false;
    }
});

/**
 * To help distinguish those nodes that aren't selectable,
 * we disable and remove the default button colour
 *
 * @returns { Boolean }
 */
const isButton = window.Vue.computed(() => {
    if (modalSearchStore.isActive) {
        //when search is active, we can jump to the selected node
        return true;
    } else if (isParentNode.value) {
        //is the parent node, which we don't
        return false;
    } else if (props.node.children === null) {
        //the children haven't been loaded
        return false;
    } else if (props.node.children?.length === 0) {
        //there's no children to display, so no point going further
        return false;
    } else {
        return true;
    }
});

/**
 * Only use bold on the title when
 *
 * @returns { string }
 */
const fontWeight = window.Vue.computed(() => (isParentNode.value && !modalSearchStore.isActive ? "bold" : "normal"));

/**
 * traverse to the next nodel level
 */
function onNameSelected() {
    if (modalSearchStore.isActive) {
        // reset the structure and move to the selected searched node
        modalViewNodesStore.toId(props.node.id);
    } else {
        // traverse to the next node level
        modalViewNodesStore.setNextSelected(props.node.id);
    }
}
</script>

<template>
    <show-matches
        v-if="isButton"
        tag="button"
        class="button-name"
        :title="`View ${props.title}`"
        :disabled="isDisabled"
        :text="props.title"
        :query="modalSearchStore.query"
        :is-searching="modalSearchStore.isActive"
        @click="onNameSelected"
    />
    <show-matches
        v-else
        tag="span"
        class="name"
        :text="props.title"
        :query="modalSearchStore.query"
        :is-searching="modalSearchStore.isActive"
    />
</template>

<style lang="scss">
.button-name {
    background: none;
    font-size: 1rem;
    margin: 0;
    min-height: 42px;
    min-width: 40px;
    padding-left: 0.25rem;
    text-align: left;
    transition: background 0.2s;
    width: 100%;
    &:hover {
        background: var(--content-ui-background);
    }
    &:disabled {
        opacity: 0.2;
    }
    @media (max-width: 700px) {
        font-size: 0.8rem;
        padding: 0.25rem;
    }
}

.name {
    color: var(--header-font-color);
    opacity: 1;
    padding: 0.5rem 0;
    font-size: 1rem;
    font-weight: v-bind(fontWeight);
    width: 100%;
}
</style>
