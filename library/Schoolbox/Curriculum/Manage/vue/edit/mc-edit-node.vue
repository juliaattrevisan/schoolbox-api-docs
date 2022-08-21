<script setup>

/**
 * to show further info in a modal on a code that's been selected
 */

import { ref, computed, watch } from 'vue';
import { useEditNodeStore } from '../store/mcEditNodeStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


const editNodeStore = useEditNodeStore();
const modalEl = ref(null);

/**
 * to open the modal when requested
 */
watch(() => editNodeStore.isActive, (isActive) => {
    if (isActive) {
        modalEl.value.open();
    }
});

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = computed(() => {
    if (editNodeStore.node.code && editNodeStore.node.code !== '') {
        return `[${editNodeStore.node.code}] ${editNodeStore.node.name}`;
    } else {
        return editNodeStore.node.name;
    }
});

/**
 * close the modal, setting the edited node to null
 * so we can allow another selection
 */
function onModalClosed () {
    editNodeStore.unset(null);
}

</script>

<template>
    <reveal-modal
        ref="modalEl"
        @closed="onModalClosed"
    >
        <template v-if="editNodeStore.isActive">
            <h2>{{ title }}</h2>
            <p class="meta">{{ editNodeStore.node.breadcrumb }}</p>
            <p>{{ editNodeStore.node.description }}</p>
        </template>
    </reveal-modal>
</template>

<style scoped>

</style>