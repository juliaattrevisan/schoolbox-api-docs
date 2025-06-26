<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { getGradient } from "@js/util/cssUtils";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import { useMarkbookStore } from "./store/markbookStore";
import MbStudentGradeCell from "./mb-student-grade-cell.vue";
import MbClassStatisticRow from "./mb-class-statistic-row.vue";
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
            fetch: (assessmentId: number, aImpl: MarkbookClassImplActivity) => ({
                url: `/learning/assessments/weight/${assessmentId}`,
                data: { assessmentId, weight: aImpl.weight },
            }),
        },
    },
    {
        label: `Weight in ${locale.project.proper()}`,
        statsKey: "weightInProject",
        input: {
            key: "weight",
            allowCourseManaged: false,
            fetch: (assessmentId: number, aImpl: MarkbookClassImplActivity) => ({
                url: `/learning/assessments/weight/${assessmentId}`,
                data: { assessmentId, weight: aImpl.weight },
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
            v-if="props.type === 'class'"
            :class-impl="classImpl"
            label="Visibility"
            stats-key="markVisibility"
            :input="{
                key: 'markVisibility',
                selectOptions: markVisibilityOptions,
                allowCourseManaged: true,
                fetch: (assessmentId, aImpl) => ({
                    url: `/learning/assessments/visibility/${assessmentId}`,
                    data: { assessmentId, visibility: aImpl.markVisibility },
                }),
            }"
        />

        <tr v-if="props.type === 'course'">
            <th class="class-row class-row-heading fixed-left">
                <a :href="`/homepage/${classImpl.folder.id}`">{{ classImpl.folder.title }}</a>
            </th>
            <th class="class-row fixed-left"></th>
            <td v-if="mbStore.data.rank !== null"></td>
            <template v-for="{ activity, aImpl } in orderedClassImplActivities" :key="activity.id">
                <td v-if="aImpl !== null && aImpl.stats" class="class-row">
                    <a :href="aImpl._routes['learningAssessment.getStatus']">
                        <span v-if="mbStore.showDrafts && aImpl.stats.draftMean !== null" class="meta">
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

        <tr v-for="student in orderedClassImplStudents" :key="student.id">
            <th class="profile-cell fixed-left white">
                <div class="c-card">
                    <div class="l-flex-row l-gap-m l-pad-s">
                        <user-photo v-if="!mbStore.isDegraded" :user="student" render="small" rounded-corners />
                        <div class="l-flex-col l-flex--justify-center f-no-margins">
                            <a :href="`/search/user/${student.id}`" title="View Profile">
                                {{ student.lastName }}, {{ student.givenName }}
                            </a>
                        </div>
                    </div>
                </div>
            </th>

            <th
                class="fixed-left student-grade-cell"
                :class="
                    getGradient(
                        mbStore.showDrafts && student.stats.draftWeightedMean !== null
                            ? student.stats.meanDraftNormalised
                            : student.stats.meanNormalised,
                        100,
                        true,
                    )
                "
            >
                <a
                    v-if="mbStore.showDrafts && student.stats.draftWeightedMean !== null"
                    :class="getGradient(student.stats.meanDraftNormalised, 100, true)"
                    :href="student._routes.grades"
                    :title="`View students ${locale.grades.plural()}`"
                >
                    {{ student.stats.draftWeightedMean }}
                </a>
                <a
                    v-else
                    :class="getGradient(student.stats.meanNormalised, 100, true)"
                    :href="student._routes.grades"
                    :title="`View students ${locale.grades.plural()}`"
                >
                    {{ student.stats.weightedMean ?? "–" }}
                </a>
            </th>
            <td v-if="mbStore.data.rank !== null">
                <span v-if="mbStore.data.rank[student.id] !== undefined && student.stats.weightedMean">
                    {{ mbStore.data.rank[student.id] }}
                </span>
                <span v-else>–</span>
            </td>

            <mb-student-grade-cell
                v-for="activity in mbStore.orderedActivities"
                :key="activity.id"
                :assessment-id="activity.id"
                :class-impl-id="props.classImplId"
                :student-id="student.id"
            />
        </tr>
    </template>
</template>

<style scoped></style>
