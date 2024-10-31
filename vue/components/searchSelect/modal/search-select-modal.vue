<script setup lang="ts">
/**
 * A modal to search and select items
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import SsmFooter from "./ssm-footer.vue";

interface Props {
    isOpen: boolean; // whether the modal is open
}
const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    select: [];
}>();

/**
 * Close the modal
 */
function onCancel(): void {
    emit("close");
}

/**
 * The selection has been confirmed, so close the modal
 * and insert into the current selection
 */
function onSelect(): void {
    emit("select");
}
</script>

<template>
    <div>
        <basic-modal :is-open="props.isOpen">
            <h2>Browse Courses</h2>
            <ssm-footer @cancel="onCancel" @select="onSelect" />
        </basic-modal>
    </div>
</template>

<style scoped></style>
