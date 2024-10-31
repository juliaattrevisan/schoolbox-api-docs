<script setup lang="ts">
/**
 * A modal to search and select items
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import SsmFooter from "./ssm-footer.vue";

// interface Props {
//     isOpen: boolean; // whether the modal is open
// }
// const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    select: [];
}>();

const isOpen = defineModel<boolean>("is-open", { default: false }); // to open/close the modal

/**
 * Close the modal
 */
function onCancel(): void {
    isOpen.value = false;
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
        <basic-modal v-model:is-open="isOpen">
            <h2>Browse Courses</h2>
            <ssm-footer @cancel="onCancel" @select="onSelect" />
        </basic-modal>
    </div>
</template>

<style scoped></style>
