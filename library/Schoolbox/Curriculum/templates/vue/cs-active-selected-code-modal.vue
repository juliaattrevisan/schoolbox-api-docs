<script setup>

/**
 * to show further info in a modal on a code that's been selected
 */

import { ref, computed, watch } from 'vue';
import { useSelectedCodesStore } from './store/csSelectedCodesStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


const selectedCodesStore = useSelectedCodesStore();
const modalEl = ref(null);

/**
 * to open the modal when requested
 */
watch(() => selectedCodesStore.hasActiveCode, (hasActiveCode) => {
    if (hasActiveCode) {
        modalEl.value.open()
    }
});

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = computed(() => {
    if (selectedCodesStore.activeCode.code && selectedCodesStore.activeCode.code !== '') {
        return `[${selectedCodesStore.activeCode.code}] ${selectedCodesStore.activeCode.name}`;
    } else {
        return selectedCodesStore.activeCode.name;
    }
});

/**
 * close the modal, setting the activeCode to null so we can allow another selection
 */
function onModalClosed () {
    selectedCodesStore.setActiveCode(null);
}

</script>

<template>
    <reveal-modal
        ref="modalEl"
        @closed="onModalClosed"
    >
        <template v-if="selectedCodesStore.hasActiveCode">
            <h2>{{ title }}</h2>
            <p class="meta">{{ selectedCodesStore.activeCode.breadcrumb }}</p>
            <div v-html="selectedCodesStore.activeCode.description"></div>
        </template>
    </reveal-modal>
</template>

<style scoped>

</style>