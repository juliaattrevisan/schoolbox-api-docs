<script setup lang="ts">
/**
 * To view all details including breadcrumbs and description on a curriculum node
 */

import { getBreadcrumbs, UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import NodeUsageDetails from "@schoolboxCurriculum/vue/usage/node-usage-details.vue";
import { ActivityTypes } from "@schoolboxCurriculum/types/Node";
import type { Breadcrumb } from "@schoolboxCurriculum/types/Breadcrumb";
import type { Node, Usage } from "@schoolboxCurriculum/types/Node";

interface Props {
    /**
     * the ID of the node to load and display
     */
    nodeId: number;
    /**
     * Whether to load and show the usage information for the node
     * Defaults to false
     */
    includeUsage?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    includeUsage: false,
});

const activeNode = window.Vue.ref<{ node: Node; breadcrumbs: Breadcrumb[] } | null>(null);

const isLoading = window.Vue.ref(true);
const hasLoadError = window.Vue.ref(false);

/**
 * Load all the node usage details for the current active node
 */
async function loadUsage(): Promise<void> {
    if (activeNode.value === null) {
        throw new Error("No active node to load usage for");
    }

    const usageResponse = await fetch(activeNode.value.node._links.getUsage);
    if (usageResponse.ok) {
        const usage = (await usageResponse.json()) as { data: Usage[] };
        // we are not currently displaying folders in the usage list
        const withoutFolders = usage.data.filter((item: Usage) => item.discriminator !== ActivityTypes.FOLDER);
        activeNode.value.node.usage = withoutFolders;
    } else {
        console.error(usageResponse);
        hasLoadError.value = true;
    }
}

/**
 * load al the node details, retrieving the breadcrumbs by loading all the parents
 * @param nodeId - the ID of the node to load
 */
async function load(nodeId: number): Promise<void> {
    try {
        isLoading.value = true;
        const response = await fetch(`/api/curriculum/${nodeId}?ancestors=99`); // just use 99 as hopefully there's no tree that deep...
        if (response.ok) {
            const nodeWithParents = (await response.json()) as Node;

            activeNode.value = {
                node: nodeWithParents,
                breadcrumbs: getBreadcrumbs(nodeWithParents, []),
            };

            // Now load the usage information for the active node if required
            if (props.includeUsage) {
                await loadUsage();
            }
        } else {
            console.error(response);
            hasLoadError.value = true;
        }
    } catch (error) {
        console.error(error);
        hasLoadError.value = true;
    } finally {
        isLoading.value = false;
    }
}

/**
 * Add in the code if it's available otherwise just show the title
 * @returns { string }
 */
const title = window.Vue.computed(() => {
    if (activeNode.value === null) {
        return ""; // likely loading
    }

    // Prefix with the code, if available
    if (activeNode.value.node.code !== "") {
        return `[${activeNode.value.node.code}] ${activeNode.value.node.name}`;
    }

    return activeNode.value.node.name;
});

/**
 * Display the list of breadcrumbs to provide context on where the selected node comes from
 * @returns { string }
 */
const breadcrumbs = window.Vue.computed(() => {
    if (activeNode.value === null || activeNode.value.breadcrumbs.length === 0) {
        return "";
    }

    const justNames = activeNode.value.breadcrumbs.map((breadcrumb) => breadcrumb.name);
    return justNames.join(" > ");
});

/**
 * when there's a new node, we should load all the details to display
 */
window.Vue.watch(
    () => props.nodeId,
    (newNodeId) => {
        activeNode.value = null;
        if (newNodeId !== UNSET_ID) {
            void load(newNodeId);
        }
    },
);

/**
 * When initially loaded, we should load the node details if we have a valid node ID
 */
window.Vue.onMounted(() => {
    if (props.nodeId !== UNSET_ID) {
        void load(props.nodeId);
    }
});
</script>

<template>
    <div v-if="isLoading" class="loader" />
    <div v-else-if="activeNode !== null">
        <h2>{{ title }}</h2>
        <p v-if="breadcrumbs.length > 0" class="meta">{{ breadcrumbs }}</p>
        <node-usage-details
            :node="activeNode.node"
            :depth="0"
            :is-processing="isLoading"
            :has-load-error="hasLoadError"
        />
    </div>
</template>
