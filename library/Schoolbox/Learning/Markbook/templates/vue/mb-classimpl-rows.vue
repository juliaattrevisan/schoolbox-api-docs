<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { ACTIVITY_TYPE_REPORTING, ACTIVITY_TYPE_TASK } from "@schoolboxAssessment/Types/assessmentTypes";
import { useMarkbookStore } from "./store/markbookStore";
import MbClassStatisticRow from "./mb-class-statistic-row.vue";
import MbClassimplRowStudent from "./mb-classimpl-row-student.vue";
import { getMarkVisibilityOptions } from "./markVisibilityOptions";
import type { Props as MbClassStatisticRowProps } from "./mb-class-statistic-row.vue";
import type { MarkbookClassImplActivity } from "./types/payload";
import { MarkbookType } from "./types/types";

interface Props {
    type: MarkbookType;
    classImplId: number;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const classStatisticRows: Omit<MbClassStatisticRowProps, "classImpl">[] = [
    {
        label: "Weight",
        statsKey: "weight",
        hideForTypes: [ACTIVITY_TYPE_TASK],
        input: {
            key: "weight",
            allowCourseManaged: false,
            readonlyForTypes: [ACTIVITY_TYPE_REPORTING],
            fetch: (aImpl: MarkbookClassImplActivity) => ({
                url: `/learning/assessments/weight/${aImpl.id}`,
                data: { assessmentId: aImpl.id, weight: aImpl.weight },
            }),
        },
    },
    {
        label: `Weight in ${locale.project.proper()}`,
        statsKey: "weightInProject",
        hideForTypes: [ACTIVITY_TYPE_TASK],
        input: {
            key: "weight",
            allowCourseManaged: false,
            readonlyForTypes: [ACTIVITY_TYPE_REPORTING],
            fetch: (aImpl: MarkbookClassImplActivity) => ({
                url: `/learning/assessments/weight/${aImpl.id}`,
                data: { assessmentId: aImpl.id, weight: aImpl.weight },
            }),
        },
    },
    { label: "% Total Weight", statsKey: "weightPercent", round: 2 },
    { label: `% Total Weight in ${locale.project.proper()}`, statsKey: "projectWeightPercent", round: 2 },
    { label: "Mean", statsKey: "mean" },
    { label: "Standard Deviation", statsKey: "sd" },
    { label: "Min", statsKey: "min" },
    { label: "Max", statsKey: "max" },
] as const;

const classImpl = window.Vue.computed(() => {
    if (mbStore.data === null) return null;

    return mbStore.data.classImpls[props.classImplId];
});

const orderedClassImplActivities = window.Vue.computed(() => {
    if (classImpl.value === null) return [];

    return mbStore.orderedClassImplActivities(classImpl.value);
});

const orderedClassImplStudents = window.Vue.computed(() => {
    if (classImpl.value === null) return [];

    return mbStore.orderedClassImplStudents(classImpl.value);
});
</script>

<template>
    <template v-if="mbStore.data !== null && classImpl !== null">
        <!-- Class Separator Row / Class Averages - Course Markbook Only -->
        <tr v-if="props.type === MarkbookType.COURSE" :class="{ 'markbook-faded': mbStore.feedbackSelectedCell }">
            <th class="class-row class-row-heading fixed-left">
                <!-- you may not have access to this folder... and will get hit with an error page... so we may need to look at routes or perms for this... -->
                <a :href="`/homepage/${classImpl.folder.id}`">{{ classImpl.folder.title }}</a>
            </th>
            <th class="class-row fixed-left"></th>
            <td v-if="mbStore.data.rank !== null" class="class-row"></td>
            <template v-for="{ activity, aImpl } in orderedClassImplActivities" :key="activity.id">
                <td v-if="aImpl !== null && aImpl.stats" class="class-row">
                    <a :href="aImpl._routes['learningAssessment.getStatus']">
                        <span v-if="mbStore.showDrafts && mbStore.aImplHasDraftMarks(aImpl)" class="meta">
                            {{ aImpl.stats.draftMean ?? "–" }}
                        </span>
                        <span v-else>
                            {{ aImpl.stats.mean ?? "–" }}
                        </span>
                    </a>
                </td>
                <td v-else class="class-row na"></td>
            </template>
        </tr>

        <!-- Class Average Statistic Rows - Class Markbook only  -->
        <template v-if="props.type === MarkbookType.CLASS && mbStore.showAverages">
            <mb-class-statistic-row
                v-for="row in classStatisticRows"
                :key="row.statsKey"
                :class-impl="classImpl"
                :label="row.label"
                :stats-key="row.statsKey"
                :hide-for-types="row.hideForTypes"
                :input="'input' in row ? row.input : undefined"
                :round="'round' in row ? row.round : undefined"
            />
        </template>

        <!-- Visibility Controls - Course/Class Markbook only -->
        <mb-class-statistic-row
            v-if="[MarkbookType.CLASS, MarkbookType.COURSE].includes(props.type)"
            :class-impl="classImpl"
            label="Visibility"
            stats-key="markVisibility"
            :input="{
                key: 'markVisibility',
                selectOptions: (aImpl) =>
                    getMarkVisibilityOptions(mbStore.data?.availableVisibilities ?? [], aImpl.markVisibility),
                allowCourseManaged: true,
                readonlyForTypes: [ACTIVITY_TYPE_REPORTING],
                fetch: (aImpl) => ({
                    url: `/learning/assessments/visibility/${aImpl.id}`,
                    data: { assessmentId: aImpl.id, visibility: aImpl.markVisibility },
                }),
            }"
        />

        <!-- All student rows for this class impl -->
        <mb-classimpl-row-student
            v-for="student in orderedClassImplStudents"
            :key="student.id"
            :class-impl-id="props.classImplId"
            :student="student"
        />
    </template>
</template>

<style scoped></style>
