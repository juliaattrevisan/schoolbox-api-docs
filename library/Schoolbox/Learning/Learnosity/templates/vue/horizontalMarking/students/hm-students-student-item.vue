<script setup lang="ts">
/**
 * Contains a students response to an item, (only one item is shown at a time)
 */

import type { Student } from "../types/Student";
import type { StudentItem } from "../types/StudentItem";
import { useItemsStore } from "../store/hmItemsStore";
import hmStudentsStudentItemResponse from "./hm-students-student-item-response.vue";
import hmStudentsStudentItemComment from "./hm-students-student-item-comment.vue";

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
                <div class="item-responses l-flex-col l-gap-m">
                    <hm-students-student-item-response
                        v-for="(response, index) in props.item.responses"
                        :key="response.learnosityReference"
                        :student="props.student"
                        :question="props.item.questions[index]"
                        :response="response"
                        :container="props.container"
                    />
                </div>
                <hm-students-student-item-comment :student="props.student" :item="props.item" />
            </div>
        </div>
    </transition>
</template>

<style lang="scss">
.item-responses {
    border-top: 1px solid var(--body-background);
    border-bottom: 1px solid var(--body-background);
    padding: var(--spacing-m) 0;
    .lrn_widget {
        margin: 0;
        padding: 0;
    }
}

.v-enter-active {
    transition: opacity 0.3s ease;
}
.v-enter-from {
    opacity: 0;
}
</style>
