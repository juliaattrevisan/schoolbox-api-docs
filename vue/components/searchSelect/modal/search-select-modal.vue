<script setup lang="ts">
/**
 * A modal to search and select items
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";

interface Props {
    isOpen: boolean; // whether the modal is open
}
const props = defineProps<Props>();
</script>

<template>
    <div>
        <basic-modal :is-open="props.isOpen">
            <h2>Browse Courses</h2>
        </basic-modal>
    </div>
</template>

<style scoped></style>
