<script setup lang="ts">
import { getGradient } from "@js/util/cssUtils";
import ProgressTick from "@schoolboxLibrary/Learning/Assessment/templates/fragments/vue/progress-tick.vue";
import { useMarkbookStore } from "./store/markbookStore";

interface Props {
    classImplId: number;
    studentId: number;
    assessmentId: number;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const classImpl = window.Vue.computed(() => {
    if (mbStore.data === null) return null;

    return mbStore.data.classImpls[props.classImplId] || null;
});

const assessment = window.Vue.computed(() => {
    if (mbStore.data === null) return null;

    return mbStore.data.assessments[props.assessmentId] || null;
});

const aImpl = window.Vue.computed(() => {
    if (classImpl.value === null) return null;

    return classImpl.value.assessments[props.assessmentId] || null;
});

const student = window.Vue.computed(() => {
    if (classImpl.value === null) return null;

    return classImpl.value.students[props.studentId] || null;
});

const participant = window.Vue.computed(() => {
    if (aImpl.value === null || student.value === null) return null;

    return aImpl.value.participants[student.value.id] || null;
});
</script>

<template>
    <td
        v-if="classImpl && assessment && student"
        class="student-grade-cell"
        :class="{ excluded: !participant || participant.isExcluded }"
        :data-test="`markbook-cell-${assessment.id}-${student.id}`"
        data-pop-out-menu
    >
        <template v-if="participant === null || participant.isExcluded"></template>
        <div v-else-if="participant.progressTick" style="display: flex; justify-content: center">
            <progress-tick
                :progress-tick="participant.progressTick"
                :is-self-assessable="false"
                can-modify
                no-margin
                @update:class="(newClass) => (participant!.progressTick!.progressClass = newClass)"
            />
        </div>
        <template v-else>
            <span
                v-if="mbStore.showDrafts && participant.marks.draft !== null"
                data-tooltip="right"
                class="has-tip tip-right meta"
                :class="getGradient(participant.marks.draft.norm, 100, false)"
                data-width="105"
                title="Draft Mark"
            >
                <!-- todo: decorator.label -->
                {{ participant.marks.draft[mbStore.showDisplayMarks ? "hr" : "hrInstructor"] }}
            </span>
            <span
                v-else-if="participant.marks.published !== null"
                :title="participant.marks.published.comment ?? ''"
                :class="getGradient(participant.marks.published.norm, 100, false)"
            >
                <!-- todo: decorator.label -->
                {{ participant.marks.published[mbStore.showDisplayMarks ? "hr" : "hrInstructor"] }}
            </span>

            <!-- todo: decorator.indicator, decorator.notchTitle -->
            <span
                v-if="
                    participant.hasSubmittedResponse ||
                    participant.resubmissionRequired ||
                    participant.hasPublishedFeedback ||
                    participant.marks.draft
                "
                class="legend-indicator"
            ></span>

            <span
                v-if="participant.plan && !participant.plan.isDynamic"
                class="individualisation has-tip tip-left"
                data-width="200"
                data-tooltip
                :title="`${participant.plan.name} (${participant.plan.dueDate})`"
            >
                i
            </span>
        </template>
    </td>
    <td v-else class="na"></td>
</template>

<style scoped></style>
