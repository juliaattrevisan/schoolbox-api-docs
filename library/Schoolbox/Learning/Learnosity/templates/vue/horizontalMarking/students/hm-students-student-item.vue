<script setup lang="ts">
/**
 * Contains a students response to an item, (only one item is shown at a time)
 */

import type { Student } from "../types/Student";
import type { StudentItem } from "../types/StudentItem";
import { useItemsStore } from "../store/hmItemsStore";
import hmStudentsStudentItemResponses from "./hm-students-student-item-responses.vue";
import hmStudentsStudentItemReview from "./hm-students-student-item-review.vue";

interface Props {
    student: Student; // the student to display
    item: StudentItem; //
    container: HTMLDivElement; // the parent container, to look for the relevant item
}
const props = defineProps<Props>();
const itemsStore = useItemsStore();

/**
 * To display the item when it's the active item
 */
const isActive = window.Vue.computed(() => {
    if (itemsStore.isFinalReview) {
        return false;
    }
    return props.item.reference === itemsStore.activeItem?.reference;
});
</script>

<template>
    <transition>
        <div v-if="isActive">
            <div class="l-flex-col l-gap-m">
                <hm-students-student-item-responses
                    :student="props.student"
                    :item="props.item"
                    :container="props.container"
                />
                <hm-students-student-item-review :student="props.student" :item="props.item" />
            </div>
        </div>
    </transition>
</template>

<style>
.v-enter-active {
    transition: opacity 0.3s ease;
}
.v-enter-from {
    opacity: 0;
}
</style>
