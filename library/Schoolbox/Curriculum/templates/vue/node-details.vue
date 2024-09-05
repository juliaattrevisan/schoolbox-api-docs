<script setup lang="ts">
/**
 * To view all details including breadcrumbs and description on a curriculum node
 */

import MediaArticle from "@schoolboxVue/components/media-article.vue";
import { getBreadcrumbs, UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import type { Breadcrumb } from "@schoolboxCurriculum/types/Breadcrumb";
import type { Node } from "@schoolboxCurriculum/types/Node";

interface Props {
    /**
     * the ID of the node to load and display
     */
    nodeId: number;
}

const props = defineProps<Props>();

const activeNode = window.Vue.ref<{ node: Node; breadcrumbs: Breadcrumb[] } | null>(null);

const isLoading = window.Vue.ref(true);
const hasLoadError = window.Vue.ref(false);

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
        <media-article
            v-if="activeNode.node.description !== null"
            tag="div"
            :content="activeNode.node.description"
        ></media-article>
    </div>
</template>
