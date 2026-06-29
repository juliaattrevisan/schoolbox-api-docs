<script setup lang="ts">
/**
 * The student header, containing the student photo, name and overall attempt mark
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";
import StudentHeaderAttemptMark from "@schoolboxLearnosity/vue/student/student-header-attempt-mark.vue";
import { useStore } from "../store/quizFeedbackStore";

interface Props {
    student: Student; // the student to display
}
const props = defineProps<Props>();
const store = useStore();
</script>

<template>
    <div class="c-island u-flex-row u-flex--justify-space u-gap-m u-pad-m f-no-margins">
        <div class="u-flex-row u-flex--align-center u-gap-m f-no-margins">
            <span v-if="!props.student.isReportLoaded" class="loader"></span>
            <div class="c-img c-img--round c-img--s">
                <div class="c-img__wrapper">
                    <img
                        :src="`/portrait.php?id=${props.student.id}&size=square64`"
                        :alt="`Photo of ${props.student.name}`"
                    />
                </div>
            </div>
            <h2>
                {{ props.student.name }}
            </h2>
        </div>
        <div v-if="store.hasMarkVisibility">
            <student-header-attempt-mark
                v-if="props.student.isReportLoaded"
                :student="props.student"
                :mark-data="useStore().markData"
            />
        </div>
    </div>
</template>

<style scoped>
.loader {
    height: 2rem;
    width: 2rem;
}
</style>
