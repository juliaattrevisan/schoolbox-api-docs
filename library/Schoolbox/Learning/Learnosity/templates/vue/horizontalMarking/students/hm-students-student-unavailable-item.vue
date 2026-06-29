<script setup lang="ts">
/**
 * Displayed when the item was not available to the student at the time of the attempt
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
import { useItemsStore } from "../store/hmItemsStore";

interface Props {
    student: Student; // the student to display
}
const props = defineProps<Props>();
const itemsStore = useItemsStore();

/**
 * To display when the item was not available to the student at the time of the attempt
 * @returns {boolean} - whether the item is active
 */
const isActive = window.Vue.computed(() => {
    if (itemsStore.isAttemptComment) {
        return false;
    }
    const referenceList = props.student.items.map((item) => item.reference);
    return !referenceList.includes(itemsStore.activeItem?.reference);
});
</script>

<template>
    <div v-if="isActive" class="u-flex-col l-gap-m l-pad-m">
        <div class="alert-box f-no-margins">This item was not in the quiz when the student attempted the quiz.</div>
    </div>
</template>

<style scoped></style>
