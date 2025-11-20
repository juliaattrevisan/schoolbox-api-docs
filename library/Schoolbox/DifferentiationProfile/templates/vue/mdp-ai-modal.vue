<script setup lang="ts">
/**
 * Modal for generating AI content for a differentiation profile
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { useGenAiStore } from "./store/mdpGenAiStore";

const genAiStore = useGenAiStore();
</script>

<template>
    <basic-modal v-model:is-open="genAiStore.isOpen" size="s">
        <p>Blank</p>
    </basic-modal>
</template>
