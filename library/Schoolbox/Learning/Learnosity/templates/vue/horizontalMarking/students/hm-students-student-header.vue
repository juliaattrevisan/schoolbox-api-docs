<script setup lang="ts">
/**
 * The student header, containing the toggle, student photo, name and mark for each item
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
import StudentItemMark from "@schoolboxLearnosity/vue/student/student-item-mark.vue";
import StudentHeaderAttemptMark from "@schoolboxLearnosity/vue/student/student-header-attempt-mark.vue";
import { useStore } from "../store/horizontalMarkingStore";
import { useItemsStore } from "../store/hmItemsStore";

interface Props {
    student: Student; // the student to display
    container: HTMLElement | null; // the element that the report is rendered into
}
const props = defineProps<Props>();

const emit = defineEmits<{
    toggle: [value: Student];
}>();

const itemsStore = useItemsStore();

/**
 * To show/hide the response when selected
 */
function onToggleSelected(): void {
    emit("toggle", props.student);
}
</script>

<template>
    <div class="l-flex-row l-flex--justify-space l-gap-m l-pad-m f-no-margins">
        <div class="l-flex-row l-flex--align-center l-gap-m f-no-margins">
            <button
                v-if="props.student.isReportLoaded"
                :class="`toggle-button l-flex l-flex--align-center l-flex--justify-center ${
                    props.student.isExpanded ? 'icon-up-arrow' : 'icon-down-arrow'
                }`"
                :disabled="!props.student.isReportLoaded"
                aria-label="Toggle student response"
                @click.prevent="onToggleSelected"
            ></button>
            <span v-else class="loader"></span>
            <div class="c-img c-img--round c-img--s">
                <div class="c-img__wrapper">
                    <img
                        :src="`/portrait.php?id=${props.student.id}&size=square64`"
                        :alt="`Photo of ${props.student.name}`"
                    />
                </div>
            </div>
            <h2>
                <a :href="props.student.urls?.getFeedback" title="View students response">{{ props.student.name }}</a>
            </h2>
        </div>
        <div v-if="props.student.isReportLoaded">
            <student-item-mark
                v-for="item in props.student.items"
                :key="item.reference"
                :item="item"
                :container="container as HTMLElement"
                :is-shown="item.reference === itemsStore.activeItem.reference"
                :show-title="false"
            />
            <student-header-attempt-mark
                v-if="itemsStore.isAttemptComment"
                :student="props.student"
                :mark-data="useStore().markData"
            />
        </div>
    </div>
</template>

<style scoped>
.toggle-button {
    background: none;
    color: var(--content-ui-foreground);
    height: 2.5rem;
    width: 2.5rem;
}

.loader {
    height: 2.5rem;
    width: 2.5rem;
}
</style>
