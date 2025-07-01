<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { getGradient } from "@js/util/cssUtils";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import { useMarkbookStore } from "./store/markbookStore";
import MbStudentGradeCell from "./mb-student-grade-cell.vue";
import type { MarkbookClassImplStudent } from "./types/payload";

interface Props {
    type: "course" | "class";
    classImplId: number;
    student: MarkbookClassImplStudent;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();
</script>

<template>
    <tr v-if="mbStore.data !== null">
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

<style scoped></style>
