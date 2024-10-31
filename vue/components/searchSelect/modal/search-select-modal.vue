<script setup lang="ts">
/**
 * A modal to search and select items
 */

import SbxModal from "@schoolboxVue/components/modal/sbx-modal.vue";

interface Props {
    isOpen: boolean; // whether the modal is open
}
const props = defineProps<Props>();
</script>

<template>
    <div>
        <sbx-modal v-if="props.isOpen"></sbx-modal>
    </div>
</template>

<style scoped></style>
