<script setup>

/**
 * to show further info in a modal on a code that's been selected
 */

import { ref, computed, watch } from 'vue';
import { useSelectedNodesStore } from './store/mcSelectedNodesStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


const selectedNodesStore = useSelectedNodesStore();
const modalEl = ref(null);

/**
 * to open the modal when requested
 */
watch(() => selectedNodesStore.hasActiveNode, (hasActiveNode) => {
    if (hasActiveNode) {
        modalEl.value.open()
    }
});

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = computed(() => {
    if (selectedNodesStore.activeNode.code && selectedNodesStore.activeNode.code !== '') {
        return `[${selectedNodesStore.activeNode.code}] ${selectedNodesStore.activeNode.name}`;
    } else {
        return selectedNodesStore.activeNode.name;
    }
});

/**
 * Display the list of breadcrumbs to provide context on where the selected node comes from
 *
 * @returns { String }
 */
const breadcrumbs = computed(() => {
    if (selectedNodesStore.activeNode.breadcrumbs.length > 0) {
        const justNames = selectedNodesStore.activeNode.breadcrumbs.map((breadcrumb) => breadcrumb.name);
        return justNames.join(' > ');
    } else {
        return '';
    }
});

/**
 * close the modal, setting the activeNode to null so we can allow another selection
 */
function onModalClosed () {
    selectedNodesStore.setActiveNode(null);
}

</script>

<template>
    <reveal-modal
        ref="modalEl"
        @closed="onModalClosed"
    >
        <template v-if="selectedNodesStore.hasActiveNode">
            <h2>{{ title }}</h2>
            <p v-if="breadcrumbs.length > 0" class="meta">{{ breadcrumbs }}</p>
            <div v-html="selectedNodesStore.activeNode.description"></div>
        </template>
    </reveal-modal>
</template>

<style scoped>

</style>