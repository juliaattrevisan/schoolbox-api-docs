<script setup>

/**
 * To view all details including breadcrumbs and description on a curriculum node
 */

import { ref, computed, onMounted } from 'vue';
import LoadingTable from '@schoolboxVue/components/loading/loading-table.vue';
// import { getBreadcrumbs } from '@schoolboxCurriclum/js/nodeUtils';


const props = defineProps({
    /**
     * the node to load and display
     */
    node: {
        type: Object,
        required: true,
    },
});

const node = ref(null);
const isLoading = ref(true);
const hasLoadError = ref(false);

/**
 *
 */
onMounted (() => {
    node.value = node.value;
    load();
});

/**
 *
 */
async function load () {
    try {
        isLoading.value = true;
        const response = await fetch(`/api/curriculum/${node.value.id}?ancestors=99`); //just use 99 as hopefully there's no tree that deep...
        if (response.ok) {
            const nodeWithParents = await response.json();
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
 * @NOTE - temporarily while wait for merge in anothe PR
 * Recursively loop through the parents adding in a breadcrumb for each
 *
 * @param { object }
 * @param { array }
 */
function getBreadcrumbs (node, currentBreadcrumbs) {
    if (node.hasOwnProperty('parent') && node.parent !== null) { //if there's a parent to use as a breadcrumb
        let breadcrumbs = [ { id: node.parent.id, name: node.parent.name }, ...currentBreadcrumbs ]; //add it in to the front of the list
        return breadcrumbs = getBreadcrumbs(node.parent, breadcrumbs); //and then check the next node up the list
    } else { //otherwise we must be at the top of the breadcrumb list
        return currentBreadcrumbs; //so we can return now
    }
}

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = computed(() => {
    if (node.value?.code && node.value.code !== '') {
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
const breadcrumbs = computed(() => {
    if (node.value?.breadcrumbs.length > 0) {
        const justNames = node.value.breadcrumbs.map((breadcrumb) => breadcrumb.name);
        return justNames.join(' > ');
    } else {
        return '';
    }
});

</script>

<template>
   <div>
        <h2>{{ title }}</h2>
        <loading-table v-if="isLoading" />
        <template v-else>
            <p v-if="breadcrumbs.length > 0" class="meta">{{ breadcrumbs }}</p>
            <div v-html="node.description"></div>
        </template>
   </div>
</template>