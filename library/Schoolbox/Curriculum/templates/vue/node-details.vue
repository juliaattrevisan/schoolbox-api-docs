<script setup>
/**
 * To view all details including breadcrumbs and description on a curriculum node
 */

import { ref, computed, watch } from "vue";
import { getBreadcrumbs, UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";

const props = defineProps({
    /**
     * the node to load and display
     */
    node: {
        type: Object,
        required: true,
    },
});

const isActive = window.Vue.ref(false);
const node = window.Vue.ref(null);
const isLoading = window.Vue.ref(true);
const hasLoadError = window.Vue.ref(false);

/**
 * when there's a new node, we should load all the details to display
 */
window.Vue.watch(
    () => props.node,
    (newNode) => {
        if (newNode.id === UNSET_ID) {
            isActive.value = false;
        } else {
            node.value = newNode;
            load();
            isActive.value = true;
        }
    }
);

/**
 * load al the node details, retrieving the breadcrumbs by loading all the parents
 */
async function load() {
    try {
        isLoading.value = true;
        const response = await fetch(`/api/curriculum/${node.value.id}?ancestors=99`); //just use 99 as hopefully there's no tree that deep...
        if (response.ok) {
            const nodeWithParents = await response.json();
            node.value.name = nodeWithParents.name;
            node.value.code = nodeWithParents.code;
            node.value.breadcrumbs = getBreadcrumbs(nodeWithParents, []);
            node.value.description = nodeWithParents.description;
        } else {
            console.warn(response);
            hasLoadError.value = true;
        }
    } catch (error) {
        console.warn(error);
        hasLoadError.value = true;
    } finally {
        isLoading.value = false;
    }
}

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = window.Vue.computed(() => {
    if (node.value?.code && node.value.code !== "") {
        return `[${node.value.code}] ${node.value.name}`;
    } else {
        return node.value?.name;
    }
});

/**
 * Display the list of breadcrumbs to provide context on where the selected node comes from
 *
 * @returns { String }
 */
const breadcrumbs = window.Vue.computed(() => {
    if (node.value?.breadcrumbs.length > 0) {
        const justNames = node.value.breadcrumbs.map((breadcrumb) => breadcrumb.name);
        return justNames.join(" > ");
    } else {
        return "";
    }
});
</script>

<template>
    <div v-if="isActive">
        <div class="loader" v-if="isLoading" />
        <template v-else>
            <h2>{{ title }}</h2>
            <p v-if="breadcrumbs.length > 0" class="meta">{{ breadcrumbs }}</p>
            <div v-html="node.description"></div>
        </template>
    </div>
</template>
