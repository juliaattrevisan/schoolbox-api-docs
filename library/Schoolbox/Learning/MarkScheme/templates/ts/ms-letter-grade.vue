<script setup lang="ts">
/**
 * Text-input for 'percentage' markScheme
 */

import locale from "@js/terminology/rosettaStone";
import MsTextInput from "./ms-text-input.vue";
import type { MarkSchemeLetterGrade, SubmissionErrorStates } from "./markScheme";

const LIMIT = 100;

interface Props {
    id: string | number;
    name?: string;
    markScheme: MarkSchemeLetterGrade;
    submissionErrorStates: SubmissionErrorStates;
    tabIndex?: number;
    studentView?: boolean;
    class?: string;
    /**
     * Should this form input be rendered as readonly?
     */
    readonly?: boolean;
}

const props = defineProps<Props>();
const mark = defineModel<string | null>("mark", { required: true });
</script>

<template>
    <ms-text-input
        v-if="props.readonly"
        :id="props.id"
        v-model:mark="mark"
        :name="props.name"
        :submission-error-states="props.submissionErrorStates"
        :class="props.class"
        :tab-index="props.tabIndex"
        :placeholder="props.readonly ? '(no mark given)' : '(no grade)'"
        :limit="LIMIT"
        :max-length="48"
        input-title=""
        readonly
    />

    <select
        v-else
        :id="props.id?.toString() ?? undefined"
        class="letter-grade-select"
        :class="props.class"
        :data-test="`assessment-feedback-mark-select-${props.id}`"
        :name="props.name"
        :tabindex="props.tabIndex"
        :title="`Select a ${locale.grades.proper()}`"
        @change="(e) => (mark = (e.target as HTMLSelectElement).value)"
    >
        <!-- If humanReadableMark is null, there isn't an existing mark; this is a new draft
         Select No Grade. Otherwise we need to find out whether the humanReadableMark exists
         in one of the other places that might match
         -->
        <option :value="null" disabled :selected="mark === null">(no grade)</option>

        <optgroup
            v-if="props.markScheme.context.conf.length === 0"
            :label="`Original ${locale.grades.properPlural()} Deleted`"
        >
            <option selected value="-1" disabled>empty</option>
        </optgroup>
        <!-- If humanReadableMark has a value, look for it among the current Grade Group dropdown options.
         If it's not here, then it might be one of the Submission States
         -->
        <optgroup v-else :label="locale.grades.properPlural()">
            <option
                v-for="(level, i) in props.markScheme.context.conf"
                :key="i"
                :value="level.caption"
                :selected="mark === level.caption"
            >
                {{ level.caption }}
            </option>
        </optgroup>

        <!-- If it didn't exist in the Grade Group, it might have been one of the Submission States
         like Submitted/Absent/Pending etc. so look there as well
         -->
        <optgroup v-if="!props.studentView && props.submissionErrorStates.length" label="States">
            <option
                v-for="state in props.submissionErrorStates"
                :key="state.id"
                :value="state.name"
                :selected="mark === state.name"
            >
                {{ state.name }}
            </option>
        </optgroup>

        <!-- If it still hasn't been found, then the humanReadableMark needs amendment by the teacher because:
            a) it's a value from a different mark scheme than what the assessment currently uses; or
            b) it's a value from a Grade Group option that no longer exists; or
            c) it's a value from a Submission State option that no longer exists; or
            d) some other unknown value under some other unknown circumstance

        Use -1 as an illegal value flag so alaressValidate can understand that this selected option
        should be changed to an actual option
        -->

        <!-- this covers the scenario where the grade level has been deleted -->
        <optgroup
            v-if="
                mark !== null &&
                mark.length &&
                props.markScheme.context.conf.find((level) => level.caption === mark) === undefined &&
                props.submissionErrorStates.find((state) => state.name === mark) === undefined
            "
            label="Original Deleted Grade"
        >
            <option selected value="-1" disabled>
                {{ mark }}
            </option>
        </optgroup>
    </select>
</template>
