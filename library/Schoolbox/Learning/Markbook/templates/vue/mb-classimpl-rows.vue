<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { useMarkbookStore } from "./store/markbookStore";
import MbClassStatisticRow from "./mb-class-statistic-row.vue";
import MbClassimplRowStudent from "./mb-classimpl-row-student.vue";
import type { Props as MbClassStatisticRowProps } from "./mb-class-statistic-row.vue";
import type { MarkbookClassImplActivity } from "./types/payload";

interface Props {
    type: "course" | "class";
    classImplId: number;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const markVisibilityOptions = [
    // Please note; .label is output via v-html and is not escaped
    { value: 0, label: "&#xe63e; Staff Only" },
    { value: 1, label: "&#xe667; Staff and Students" },
    { value: 2, label: "&#xe666; Staff, Students and their Parents" },
] as { value: number; label: string }[];

const classStatisticRows: Omit<MbClassStatisticRowProps, "classImpl">[] = [
    {
        label: "Weight",
        statsKey: "weight",
        input: {
            key: "weight",
            allowCourseManaged: false,
            fetch: (aImpl: MarkbookClassImplActivity) => ({
                url: `/learning/assessments/weight/${aImpl.id}`,
                data: { assessmentId: aImpl.id, weight: aImpl.weight },
            }),
        },
    },
    {
        label: `Weight in ${locale.project.proper()}`,
        statsKey: "weightInProject",
        input: {
            key: "weight",
            allowCourseManaged: false,
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
        <tr v-if="props.type === 'course'" :class="{ 'markbook-faded': mbStore.feedbackSelectedCell }">
            <th class="class-row class-row-heading fixed-left">
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

        <template v-if="props.type === 'class' && mbStore.showAverages">
            <mb-class-statistic-row
                v-for="row in classStatisticRows"
                :key="row.statsKey"
                :class-impl="classImpl"
                :label="row.label"
                :stats-key="row.statsKey"
                :input="'input' in row ? row.input : undefined"
                :round="'round' in row ? row.round : undefined"
            />
        </template>

        <mb-class-statistic-row
            :class-impl="classImpl"
            label="Visibility"
            stats-key="markVisibility"
            :input="{
                key: 'markVisibility',
                selectOptions: markVisibilityOptions,
                allowCourseManaged: true,
                fetch: (aImpl) => ({
                    url: `/learning/assessments/visibility/${aImpl.id}`,
                    data: { assessmentId: aImpl.id, visibility: aImpl.markVisibility },
                }),
            }"
        />

        <mb-classimpl-row-student
            v-for="student in orderedClassImplStudents"
            :key="student.id"
            :class-impl-id="props.classImplId"
            :student="student"
        />
    </template>
</template>

<style scoped></style>
