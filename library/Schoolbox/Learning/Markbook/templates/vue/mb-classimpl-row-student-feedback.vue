<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { useMarkbookStore } from "./store/markbookStore";
import type { MarkbookClassImplStudent } from "./types/payload";

interface Props {
    classImplId: number;
    activityId: number;
    student: MarkbookClassImplStudent;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const cellInfo = window.Vue.computed(() => {
    if (mbStore.data === null) {
        return null;
    }

    const activity = mbStore.data.assessments[props.activityId];
    if (activity === undefined) {
        console.warn("Activity not found");
        return null;
    }

    const classImpl = mbStore.data.classImpls[props.classImplId];
    if (activity === undefined) {
        console.warn("Class implementation not found");
        return null;
    }

    const aImpl = classImpl.assessments[props.activityId];
    if (aImpl === undefined) {
        console.warn("aImpl not found");
        return null;
    }

    const participant = aImpl.participants[props.student.id];
    if (participant === undefined) {
        console.warn("Participant not found");
        return null;
    }

    return {
        activity,
        classImpl,
        aImpl,
        participant,
        instructorMarkSchemeTemplate: aImpl.instructorMarkScheme?.template || null,
    };
});
</script>

<template>
    <tr v-if="mbStore.data !== null && cellInfo !== null">
        <th class="fixed-left white"></th>
        <th class="fixed-left white"></th>
        <td v-if="mbStore.data.rank !== null"></td>

        <td :colspan="mbStore.orderedActivities.length">
            <div v-if="cellInfo.instructorMarkSchemeTemplate !== null" class="small-12 medium-3 column">
                <label>
                    {{ locale.grades.proper }}
                    INPUT
                </label>
                RESUBMISSION REQUIRED
            </div>
            <div
                class="small-12 column"
                :class="{
                    'medium-9': cellInfo.instructorMarkSchemeTemplate !== null,
                }"
            >
                COMMENT
                <template v-if="cellInfo.instructorMarkSchemeTemplate === null"> RESUBMISSION REQUIRED </template>
            </div>
        </td>
    </tr>
</template>

<style scoped></style>
