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
            /** An icon to display if the mark is readonly, if unset will show a disabled input field */
            readonlyIcon?: string;
        }[];
        /**
         * Parameters for the fetch request to persist the change
         */
        fetch: (aImpl: MarkbookClassImplActivity) => {
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
 * Find the readonly icon for a given aImpl's select value, if it exists
 * @param aImpl The relevant activity implementation
 * @returns The icon name, or null if none exists
 */
function getReadonlyIcon(aImpl: MarkbookClassImplActivity): string | null {
    if (props.input === undefined || props.input.selectOptions === undefined) {
        return null;
    }

    const { key } = props.input;
    const option = props.input.selectOptions.find((opt) => opt.value === aImpl[key]);
    if (option === undefined) {
        return null;
    }

    return option.readonlyIcon ?? null;
}

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

    const fetchInfo = props.input.fetch(aImpl);
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
    <tr v-if="mbStore.data !== null" class="sub-row" :class="{ 'markbook-faded': mbStore.feedbackSelectedCell }">
        <th class="fixed-left">{{ label }}</th>
        <td class="fixed-left">&nbsp;</td>
        <td v-if="mbStore.data.rank !== null"></td>
        <td
            v-for="{ assessment, aImpl, stat } in activities"
            :key="assessment.id"
            class="student-grade-cell"
            :data-assessment="assessment.id"
        >
            <template v-if="stat === null || aImpl === null"></template>
            <template v-else-if="props.input && (props.input.allowCourseManaged || !aImpl.courseManaged)">
                <template v-if="assessment.assessmentType === ACTIVITY_TYPE_TASK"></template>
                <span
                    v-else-if="props.input?.selectOptions && !aImpl.writable && getReadonlyIcon(aImpl) !== null"
                    :class="getReadonlyIcon(aImpl)"
                >
                    <!-- This field is a select and not writable, but has a readonlyIcon available to use - so use it -->
                </span>
                <select
                    v-else-if="props.input?.selectOptions"
                    v-model="aImpl[props.input.key]"
                    :name="props.statsKey"
                    :disabled="!aImpl.writable || undefined"
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
                    v-else
                    v-model="aImpl[props.input.key]"
                    type="number"
                    inputmode="numeric"
                    :name="props.statsKey"
                    :disabled="!aImpl.writable || undefined"
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
