<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { getGradient } from "@js/util/cssUtils";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import { useMarkbookStore } from "./store/markbookStore";
import MbStudentGradeCell from "./mb-student-grade-cell.vue";
import type { MarkbookClassImplStudent } from "./types/payload";

interface Props {
    classImplId: number;
    student: MarkbookClassImplStudent;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const studentRowEl = window.Vue.useTemplateRef<HTMLTableRowElement>("studentRowRef");

/**
 * A markbook row is faded if the selected feedback cell exists
 * and isn't for the current row
 */
const isFaded = window.Vue.computed(() => {
    if (mbStore.feedbackSelectedCell === null) {
        return false;
    }

    return (
        mbStore.feedbackSelectedCell.classImplId !== props.classImplId ||
        mbStore.feedbackSelectedCell.studentId !== props.student.id
    );
});

mbStore.feedbackDrawerEventBus.on(({ drawerRect }) => {
    if (studentRowEl.value === null) return;
    if (mbStore.feedbackSelectedCell === null) return;
    if (mbStore.feedbackSelectedCell.classImplId !== props.classImplId) return;
    if (mbStore.feedbackSelectedCell.studentId !== props.student.id) return;

    // Ensure that this current row is scrolled into view just above the feedback drawer if needed
    const rowRect = studentRowEl.value.getBoundingClientRect();
    const drawerTarget = window.innerHeight - drawerRect.height - rowRect.height / 2;

    if (rowRect.bottom > drawerTarget) {
        window.scrollBy({ top: rowRect.bottom - drawerTarget, behavior: "smooth" });
    }
});
</script>

<template>
    <tr
        v-if="mbStore.data !== null"
        ref="studentRowRef"
        :class="{
            'markbook-faded': isFaded,
        }"
    >
        <th class="profile-cell fixed-left white">
            <div class="c-card">
                <div class="l-flex-row l-gap-m l-pad-s">
                    <user-photo v-if="!mbStore.isDegraded" :user="student" render="small" rounded-corners />
                    <div class="l-flex-col l-flex--justify-center f-no-margins profile-cell-clip">
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
