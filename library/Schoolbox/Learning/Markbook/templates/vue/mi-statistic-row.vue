<script setup lang="ts">
import { ACTIVITY_TYPE_TASK } from "@schoolboxAssessment/Types/assessmentTypes";
import { useMarkbookStore } from "./store/markbookStore";
import type { MarkbookClassImplActivity, MarkbookClassImpl } from "./types/payload";

interface Props {
    label: string;
    statsKey: keyof MarkbookClassImplActivity["stats"];
    input?: boolean;
    round?: number;
    classImpl: MarkbookClassImpl;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const activities = window.Vue.computed(() =>
    mbStore.orderedActivities.map((assessment) => {
        const aImpl = props.classImpl.assessments[assessment.id] ?? null;

        return {
            assessment,
            aImpl,
            stat: aImpl?.stats[props.statsKey] ?? null,
        };
    }),
);
</script>

<template>
    <tr v-if="mbStore.data !== null" class="sub-row">
        <th class="fixed-left">{{ label }}</th>
        <th class="fixed-left">&nbsp;</th>
        <td v-if="mbStore.data.rank !== null"></td>
        <td
            v-for="{ assessment, aImpl, stat } in activities"
            :key="assessment.id"
            class="student-grade-cell"
            :data-assessment="assessment.id"
        >
            <template v-if="stat === null || aImpl === null || !aImpl.writable"></template>
            <!-- todo: saving ability -->
            <input
                v-else-if="props.input && assessment.assessmentType !== ACTIVITY_TYPE_TASK && !aImpl.courseManaged"
                v-model="aImpl.weight"
                type="number"
                inputmode="numeric"
                :name="props.statsKey"
                min="0"
            />
            <span v-else-if="props.round !== undefined">
                {{ parseFloat(Number(stat).toFixed(props.round)) }}
            </span>
            <span v-else>
                {{ stat }}
            </span>
        </td>
    </tr>
</template>

<style scoped></style>
