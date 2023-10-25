<script setup lang="ts">
/**
 * Contains a students response to an item, (only one item is shown at a time)
 */

import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import type { Student } from "../types/Student";
import type { StudentItem } from "../types/StudentItem";
import { useItemsStore } from "../store/hmItemsStore";
import hmStudentsStudentItemResponse from "./hm-students-student-item-response.vue";
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
const display = window.Vue.computed(() => {
    if (itemsStore.isFinalReview) {
        return "none";
    }
    return props.item.reference === itemsStore.activeItem?.reference ? "block" : "none";
});
</script>

<template>
    <div class="item">
        <div class="l-flex-col l-gap-m">
            <hm-students-student-item-response
                :student="props.student"
                :item="props.item"
                :container="props.container"
            />
            <hm-students-student-item-review :student="props.student" :item="props.item" />
        </div>
    </div>
</template>

<style scoped>
.item {
    display: v-bind(display);
}
</style>
