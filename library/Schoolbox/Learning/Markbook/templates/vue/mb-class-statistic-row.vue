<script setup lang="ts">
import { ACTIVITY_TYPE_TASK } from "@schoolboxAssessment/Types/assessmentTypes";
import { useMarkbookStore } from "./store/markbookStore";
import type { MarkbookClassImplActivity, MarkbookClassImpl } from "./types/payload";

export interface Props {
    label: string;
    statsKey: keyof MarkbookClassImplActivity["stats"];
    input?: {
        allowCourseManaged?: boolean;
        key: keyof MarkbookClassImplActivity;
        selectOptions?: {
            value: number;
            /** WARNING: This label is output as v-html to allow for icons, so ensure sanitization occurs beforehand */
            label: string;
        }[];
        /**
         * Parameters for the fetch request to persist the change
         */
        fetch: (
            assessmentId: number,
            aImpl: MarkbookClassImplActivity,
        ) => {
            url: string;
            data: Record<string, unknown>;
        };
    };
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

/**
 * Persist a change to an activity
 * @param activityId The ID of the activity to update
 * @param aImpl The relevant activity implementation
 */
async function onValueChange(activityId: number, aImpl: MarkbookClassImplActivity): Promise<void> {
    if (props.input === undefined || props.input.fetch === undefined) {
        return;
    }

    const toast = window.sbProps.toastActivate("Updating assessment...");

    const fetchInfo = props.input.fetch(activityId, aImpl);
    try {
        const response = await fetch(fetchInfo.url, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(fetchInfo.data),
        });

        if (!response.ok) {
            // try to parse the error message
            let errorMessage = response.statusText;
            try {
                const errorData = (await response.json()) as { message: string };
                errorMessage = errorData?.message ?? errorMessage;
            } catch {
                // noop
            }

            throw new Error(errorMessage);
        }

        const data = (await response.json()) as { message: string };
        toast.timeout({
            text: data?.message ?? "Assessment updated successfully",
            isSuccess: true,
        });
    } catch (error) {
        console.error("Failed to update activity:", error);
        toast.timeout({
            text: "Failed to update assessment",
            isSuccess: false,
        });
    }
}
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
            <template v-else-if="props.input && (props.input.allowCourseManaged || !aImpl.courseManaged)">
                <select
                    v-if="props.input.selectOptions && assessment.assessmentType !== ACTIVITY_TYPE_TASK"
                    v-model="aImpl[props.input.key]"
                    :name="props.statsKey"
                    @change="onValueChange(assessment.id, aImpl)"
                >
                    <!-- eslint-disable vue/no-v-html -- we can't use <sbx-icon> in a select since html spec
                    -- doesn't allow elements in options, so the whole thing is v-html'd instead... -->
                    <option
                        v-for="option in props.input.selectOptions"
                        :key="option.value"
                        :value="option.value"
                        v-html="option.label"
                    ></option>
                    <!-- eslint-enable vue/no-v-html -->
                </select>
                <input
                    v-else-if="assessment.assessmentType !== ACTIVITY_TYPE_TASK"
                    v-model="aImpl[props.input.key]"
                    type="number"
                    inputmode="numeric"
                    :name="props.statsKey"
                    min="0"
                    @change="onValueChange(assessment.id, aImpl)"
                />
            </template>
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
