<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { getGradient } from "@js/util/cssUtils";
import { ACTIVITY_TYPE_TASK } from "@schoolboxAssessment/Types/assessmentTypes";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import { useMarkbookStore } from "./store/markbookStore";
import MiCellGrade from "./mi-cell-grade.vue";
import MiStatisticRow from "./mi-statistic-row.vue";

interface Props {
    type: "course" | "class";
    classImplId: number;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const markVisibilityOptions = [
    // Please note; .icon and .description are both output via v-html are is not escaped
    { id: 0, icon: "&#xe63e;", description: "Staff Only" },
    { id: 1, icon: "&#xe667;", description: "Staff and Students" },
    { id: 2, icon: "&#xe666;", description: "Staff, Students and their Parents" },
] as const;

const classStatisticRows = [
    { label: "Weight", statsKey: "weight", input: true },
    { label: `Weight in ${locale.project.proper()}`, statsKey: "weightInProject", input: true },
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
            <mi-statistic-row
                v-for="row in classStatisticRows"
                :key="row.statsKey"
                :class-impl="classImpl"
                :label="row.label"
                :stats-key="row.statsKey"
                :input="'input' in row ? row.input : undefined"
                :round="'round' in row ? row.round : undefined"
            />
        </template>

        <tr v-if="props.type === 'class'" class="sub-row">
            <th class="fixed-left">Visibility</th>
            <th class="fixed-left"></th>
            <th v-if="mbStore.data.rank !== null"></th>
            <template v-for="{ activity, aImpl } in orderedClassImplActivities" :key="activity.id">
                <td v-if="aImpl !== null && activity.assessmentType != ACTIVITY_TYPE_TASK">
                    <!-- todo: saving ability -->
                    <select v-if="aImpl.writable" v-model="aImpl.markVisibility" name="visibility">
                        <!-- eslint-disable vue/no-v-html -- we can't use <sbx-icon> in a select, so this is the other way for now.
                          -- option.icon/description is defined above, and since html spec doesn't allow elements in options, the whole thing is v-html'd -->
                        <option
                            v-for="option in markVisibilityOptions"
                            :key="option.id"
                            :value="option.id"
                            :disabled="!mbStore.data.availableVisibilities.includes(option.id) || undefined"
                            v-html="`${option.icon} ${option.description}`"
                        ></option>
                        <!-- eslint-enable vue/no-v-html -->
                    </select>
                </td>
                <td v-else></td>
            </template>
        </tr>

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

            <mi-cell-grade
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
