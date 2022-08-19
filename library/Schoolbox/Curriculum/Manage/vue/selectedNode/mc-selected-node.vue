<script setup>

/**
 * to show further info in a modal on a code that's been selected
 */

import { ref, computed, watch } from 'vue';
import { useSelectedNodeStore } from '../store/mcSelectedNodeStore';
// import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


const selectedNodeStore = useSelectedNodeStore();
// const modalEl = ref(null);

/**
 * to open the modal when requested
 */
watch(() => selectedNodeStore.isActive, (isActive) => {
    if (isActive) {
        //
    }
});

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = computed(() => {
    if (selectedNodeStore.node.code && selectedNodeStore.node.code !== '') {
        return `[${selectedNodeStore.node.code}] ${selectedNodeStore.node.name}`;
    } else {
        return selectedNodeStore.node.name;
    }
});

/**
 * close the modal, setting the activeCode to null so we can allow another selection
 */
// function onModalClosed () {
//     selectedNodeStore.unset(null);
// }

</script>

<template>
    <h2>{{ title }}</h2>
    <p class="meta">{{ selectedNodeStore.node.breadcrumb }}</p>
    <p>{{ selectedNodeStore.node.description }}</p>
</template>

<style scoped>

</style>