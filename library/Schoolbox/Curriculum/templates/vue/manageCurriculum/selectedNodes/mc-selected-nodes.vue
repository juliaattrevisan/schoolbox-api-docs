<script setup lang="ts">
/**
 * Manage member selection and the various actions that can occur based
 * on the members currently selected
 */

import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer.vue";
import { useSelectedNodesStore } from "./store/mcSelectedNodesStore";
import { useDeleteNodesStore } from "./store/mcDeleteNodesStore";
import { useEnableNodesStore } from "./store/mcEnableNodesStore";
import { useUpdateNodeStore } from "../activeNode/store/mcUpdateNodeStore";
import { useAddSubsectionStore } from "../activeNode/store/mcAddSubsectionStore";
import { useNodeUsageStore } from "../activeNode/store/mcNodeUsageStore";
import McSelectedNode from "./mc-selected-node.vue";

const selectedNodesStore = useSelectedNodesStore(); // to keep track of selected nodes
const deleteNodesStore = useDeleteNodesStore();
const enableNodesStore = useEnableNodesStore();
const updateNodeStore = useUpdateNodeStore(); // so the bottom-drawer is not visible while we are updating a node
const addSubsectionStore = useAddSubsectionStore(); // so the bottom-drawer is not visible while we are adding a node
const nodeUsageStore = useNodeUsageStore(); // so the bottom-drawer is not visible while we are viewing the details of a node

/**
 * For proper count display, eg: 'No nodes / 1 Member' / '4 nodes'
 * @returns { string }
 */
const selectedNodesSummary = window.Vue.computed(() => {
    if (selectedNodesStore.hasNoNodes) {
        return `No outcomes`;
    }
    return selectedNodesStore.singleOrPlural;
});

/**
 * So the selected nodes can be enabled / disabled
 * @param enabled - true to enable, false to disable
 */
function onEnableNodesSelected(enabled: boolean): void {
    enableNodesStore.prompt(enabled);
}

/**
 * So the selected nodes can be removed
 */
function onDeleteNodesSelected(): void {
    deleteNodesStore.prompt();
}
</script>

<template>
    <div class="small-12 island">
        <bottom-drawer
            :show-drawer="
                selectedNodesStore.hasNodes &&
                !updateNodeStore.isOpen &&
                !addSubsectionStore.isOpen &&
                !nodeUsageStore.isOpen
            "
        >
            <div v-if="selectedNodesStore.hasNodes" class="selected-nodes-container" data-test-nodes-container>
                <mc-selected-node v-for="node in selectedNodesStore.nodes" :key="node.id" :node="node" data-test-node />
            </div>
            <p v-if="selectedNodesStore.hasDisabledChildNodes && !selectedNodesStore.allNodesEnabled" class="meta">
                If you wish to re-enable disabled outcomes, please enable the disabled parent.
            </p>
            <ul class="flex-list">
                <li v-if="selectedNodesStore.allNodesEnabled">
                    <button
                        class="button icon-view-hollow"
                        data-test="disable-nodes"
                        @click.prevent="onEnableNodesSelected(false)"
                    >
                        Disable {{ selectedNodesSummary }}
                    </button>
                </li>
                <li v-else-if="selectedNodesStore.allNodesDisabled">
                    <button
                        class="button icon-view"
                        data-test-enable-nodes
                        @click.prevent="onEnableNodesSelected(true)"
                    >
                        Enable {{ selectedNodesSummary }}
                    </button>
                </li>
                <li>
                    <button
                        class="button icon-delete alert"
                        data-test-delete-nodes
                        @click.prevent="onDeleteNodesSelected"
                    >
                        Delete {{ selectedNodesSummary }}
                    </button>
                </li>
            </ul>
        </bottom-drawer>
    </div>
</template>

<style lang="scss" scoped>
.selected-nodes-container {
    border: 1px solid var(--form-border-color);
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    max-height: 133px;
    min-height: 50px;
    overflow: auto;
    padding: 0.5rem;
}

select,
button {
    margin-bottom: 0; //to bring the bottom-drawer down, we don't need any margin currently
}
</style>
