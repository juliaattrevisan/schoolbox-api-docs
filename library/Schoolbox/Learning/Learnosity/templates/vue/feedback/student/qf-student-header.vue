<script setup lang="ts">
/**
 * The student header, containing the toggle, student photo, name and mark for each item
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
// import HmStudentsStudentItemMark from "./hm-students-student-item-mark.vue";
import QfStudentHeaderAttemptMark from "./qf-student-header-attempt-mark.vue";

interface Props {
    student: Student; // the student to display
    container: HTMLDivElement | null; // the element that the report is rendered into
}
const props = defineProps<Props>();
</script>

<template>
    <div class="l-flex-row l-flex--justify-space l-gap-m f-no-margins">
        <div class="l-flex-row l-flex--align-center l-gap-m">
            <!-- <button
                v-if="props.student.isReportLoaded"
                :class="`toggle-button l-flex l-flex--align-center l-flex--justify-center ${
                    props.student.isExpanded ? 'icon-up-arrow' : 'icon-down-arrow'
                }`"
                :disabled="!props.student.isReportLoaded"
                @click.prevent="onToggleSelected"
            ></button> -->
            <span v-if="!props.student.isReportLoaded" class="loader"></span>
            <div class="c-img c-img--round c-img--s">
                <div class="c-img__wrapper">
                    <img
                        :src="`/portrait.php?id=${props.student.id}&size=square32`"
                        :alt="`Photo of ${props.student.name}`"
                    />
                </div>
            </div>
            <h2>
                {{ props.student.name }}
            </h2>
        </div>
        <div>
            <template v-if="props.student.isReportLoaded">
                <qf-student-header-attempt-mark :student="props.student" />
            </template>
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
