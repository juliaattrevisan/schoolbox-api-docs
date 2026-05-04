<script setup lang="ts">
/**
 * Add rows in the markable rubric grid for a specific capability
 * (also known as a "group" in the past)
 */

import locale from "@js/terminology/rosettaStone";
import MarkScheme from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/mark-scheme.vue";
import type { MarkScheme as CapabilityMarkScheme } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import type { RubricGridCapability, RubricGridLevel, RubricMarkedGridCapability } from "./types";
import RmGridIndicator from "./rm-grid-indicator.vue";
import { assertUnreachable } from "@js/util/errorUtils";

interface Props {
    capability: RubricGridCapability;
    selfAssessmentCapabilityMark: RubricMarkedGridCapability | null;
    capabilityMarkScheme: CapabilityMarkScheme;
    levels: RubricGridLevel[];
    participantId: UserAuthor["id"] | null;
    showSummaryCapability: boolean;
}
const props = defineProps<Props>();
const capabilityMark = defineModel<RubricMarkedGridCapability>("capabilityMark", { required: true });

const componentId = window.Vue.useId();

const showSummaryForThisCapability = window.Vue.computed(() => {
    return props.showSummaryCapability && props.capability.showCapabilityMark;
});

/** All indicators, with their associated mark data */
const indicators = window.Vue.computed(() => {
    return props.capability.indicators.map((indicator) => {
        const indicatorMark = capabilityMark.value.indicators.find((cap) => cap.id === indicator.id) ?? null;
        const selfAssessmentIndicatorMark =
            props.selfAssessmentCapabilityMark?.indicators.find((cap) => cap.id === indicator.id) ?? null;

        // if an indicator exists in the rubric, it must always exist in the marked grid
        if (!indicatorMark) {
            throw new Error(`Unable to find indicator mark for indicator "${indicator.id}"`);
        }

        return {
            indicator: indicator,
            mark: indicatorMark,
            selfAssessmentMark: selfAssessmentIndicatorMark,
        };
    });
});

/**
 * The raw unscaled sum of each selected descriptor (aka, the sum of indicator marks) for this capability
 */
const indicatorMarkSum = window.Vue.computed(() => {
    let total = 0;
    let hasMeaningfulMark = false;

    for (const indicator of indicators.value) {
        if (indicator.mark.descriptorId !== null && indicator.mark.value !== null) {
            total += indicator.mark.value;
            hasMeaningfulMark = true;
        }
    }

    if (!hasMeaningfulMark) {
        return null;
    }

    return total;
});

/**
 * Scale an unscaled mark based on the capability max mark
 * @param value the unscaled mark to scale
 * @returns the scaled mark
 */
function scaleMark(value: number | null): number | null {
    if (value === null) {
        return null;
    }

    const capabilityMax = props.capability.maxValue ?? 0; // TODO: can this actually be null??
    const derivedMax = props.capability.derivedMaxValue;

    const scale = derivedMax > 0 ? capabilityMax / derivedMax : 1;

    let scaledValue: number = value * scale;
    if (scaledValue % 1 !== 0) {
        scaledValue = parseFloat(scaledValue.toFixed(2));
    }

    return scaledValue;
}

/**
 * Turn a specified value into a percentage of the max marks for a capability
 * @param value value
 * @returns pecentage
 */
function getPercentage(value: string | number | null): string | null {
    let floatValue = parseFloat(String(value)); // yikes.
    if (Number.isNaN(floatValue)) {
        return null;
    }

    const capabilityMax = props.capability.maxValue ?? 0; // TODO: can this actually be null??

    let percentage = capabilityMax > 0 ? (floatValue / capabilityMax) * 100 : 0;

    if (percentage % 1 !== 0) {
        // only round and show 2dp when necessary
        percentage = parseFloat(percentage.toFixed(2));
    }

    return percentage.toString();
}

/**
 * Determine the text form that a mark should display as based on the mark scheme
 * @param value scaled mark value
 * @returns the human readable form of the mark to display
 */
function applyMarkScheme(value: string | number | null): {
    value: string | null;
    humanReadableMark: string | null;
} {
    const markScheme = props.capabilityMarkScheme;
    if (markScheme.template === "") {
        return { value: null, humanReadableMark: null };
    }

    if (markScheme.template === "TotalMarkFractional") {
        const stringValue = value !== null ? String(value) : null;
        return {
            value: stringValue,
            humanReadableMark: `${stringValue} / ${props.capability.maxValue}`,
        };
    }

    // turn whatever the value currently is into a percentage
    const percentage = getPercentage(value);

    if (markScheme.template === "Percentage") {
        return {
            value: percentage,
            humanReadableMark: `${percentage}%`,
        };
    }

    if (markScheme.template === "LetterGrade") {
        const grades = markScheme.context.conf;
        if (percentage === null || percentage === "") {
            return { value: null, humanReadableMark: null };
        }

        if (grades.length === 0) {
            // some strange stuff going on here from the old logic, not really sure if it matters anymore
            return { value: "", humanReadableMark: "" };
        }

        const numericValue = parseFloat(percentage); // yikes.

        // TODO: (from old logic) assumes values are always descending which might not be the case?
        // default to the last grade if nothing matches
        const defaultGrade = grades[grades.length - 1];
        const relevantGrade = grades.find((grade) => numericValue >= grade.lowerBound) ?? defaultGrade;
        return {
            value: relevantGrade.caption,
            humanReadableMark: relevantGrade.caption,
        };
    }

    assertUnreachable(markScheme);
}

/**
 * The mark of this capability derived from the current indicator marks
 */
const derivedCapabilityMark = window.Vue.computed(() => {
    const sum = indicatorMarkSum.value;
    const scaledMark = scaleMark(sum);
    const { value, humanReadableMark } = applyMarkScheme(scaledMark);

    return {
        scaledMark,
        value: value satisfies RubricMarkedGridCapability["value"],
        humanReadableMark: humanReadableMark satisfies RubricMarkedGridCapability["humanReadableMark"],
    };
});

const hasSelectedDescriptor = window.Vue.computed<boolean>(() =>
    capabilityMark.value.indicators.some((indMark) => indMark.descriptorId !== null),
);
const hasSelectedValuedDescriptor = window.Vue.computed<boolean>(() =>
    capabilityMark.value.indicators.some((indMark) => indMark.descriptorId !== null && indMark.value !== null),
);
const capabilityHasValuedDescriptors = window.Vue.computed<boolean>(() =>
    props.capability.indicators.some((ind) => ind.descriptors.some((d) => d && d.value !== null)),
);

const markIsDifferent = window.Vue.computed<boolean>(() => {
    const derivedMark = derivedCapabilityMark.value.value;
    const customMark = capabilityMark.value.value;

    if (props.capabilityMarkScheme.template === "LetterGrade") {
        // Compare letter grades by their string value (easier since we're dealing with brackets)
        return derivedMark !== customMark;
    }

    // Compare percentage and total marks by their numeric value
    const diff = Math.abs(parseFloat(String(derivedMark)) - parseFloat(String(customMark)));

    // Allow for precision errors when diffing
    return diff >= 0.01 || (Number.isNaN(diff) && hasSelectedDescriptor.value && capabilityHasValuedDescriptors.value);
});

/** Determine if we should show the "Assign XXX from indicators" message */
const showAssignFromButton = window.Vue.computed<boolean>(() => {
    const isCustomMarkEmpty = capabilityMark.value.value === null || capabilityMark.value.value.trim() === "";

    // If no descriptors are selected, and the customMark is not empty, show the button (as it must be customised)
    if (!isCustomMarkEmpty && !hasSelectedDescriptor.value) {
        return true;
    }

    // If only valueless descriptors are selected, and the customMark is already empty, never show the button (as it'll say null)
    if (isCustomMarkEmpty && hasSelectedDescriptor.value && !hasSelectedValuedDescriptor.value) {
        return false;
    }

    // Otherwise, show it if the mark is different
    return markIsDifferent.value;
});

/**
 * Whether this capability mark has been manually "customised".
 * When customised, changing the underlying descriptor selection will not change the capability mark.
 */
const isCustomised = window.Vue.ref<boolean>(false);

/**
 * Set the capability mark to the derived value based on the indicators
 */
function useDerivedMark(): void {
    capabilityMark.value.value = derivedCapabilityMark.value.value;
    capabilityMark.value.scaledMark = derivedCapabilityMark.value.scaledMark;
    capabilityMark.value.humanReadableMark = derivedCapabilityMark.value.humanReadableMark;

    // once you've clicked this button, the mark is no longer treated as customised
    // and should update automatically with indicator changes again
    isCustomised.value = false;
}

window.Vue.onMounted(() => {
    // Track the initial customised state based on if the actual scaled mark differs from the derived one on page load
    if (capabilityMark.value.scaledMark !== derivedCapabilityMark.value.scaledMark) {
        isCustomised.value = true;
    }
});
</script>

<template>
    <tr v-if="indicators.length">
        <th
            class="rb-group-name markdown"
            :rowspan="indicators.length + (showSummaryForThisCapability ? 2 : 1) /* header+summary */"
        >
            <span>
                <!-- TODO: should be scrubbed (markdown) and output as html -->
                {{ props.capability.name }}
            </span>
        </th>
    </tr>

    <rm-grid-indicator
        v-for="indicator in indicators"
        :key="indicator.indicator.id"
        v-model:indicator-mark="indicator.mark"
        :indicator="indicator.indicator"
        :self-assessment-indicator-mark="indicator.selfAssessmentMark"
        :levels="props.levels"
        :participant-id="props.participantId"
    />

    <tr v-if="showSummaryForThisCapability">
        <th class="rb-group-summary" :colspan="props.levels.length + 1 /* descriptors + indicator column */">
            <!-- TODO: this span wrapper is probably unnecessary and solely for the old data attrs -->
            <!-- TODO: data-group-summary="{{ rubricId }}-{{ capabilityId }}" -->
            <!-- TODO: data-max="{{ capabilityMaxValue }}" -->
            <!-- TODO: data-row-max-sum="{{ capabilityMaxFromIndicators }}" -->
            <!-- TODO: data-group-id="{{ capabilityId }}" -->
            <!-- TODO: data-rubric-id="{{ rubricGrid.id }}" -->
            <!-- TODO: data-mark-type="{{ capabilityMarkType.mode }}" -->
            <span>
                <label :for="`capability-${props.capability.id}-mark-${componentId}`">
                    {{ locale.rubricGroup.proper() }} mark
                </label>

                <!-- TODO: data-group-mark -->
                <mark-scheme
                    :id="`capability-${props.capability.id}-mark-${componentId}`"
                    v-model:mark="capabilityMark.value"
                    :mark-scheme="props.capabilityMarkScheme"
                    :submission-error-states="[] /** no error states here */"
                    :limit="capability.maxValue ?? undefined"
                    class="rb-value-input groupMark"
                />

                <template v-if="props.capabilityMarkScheme.template === 'Percentage'"> % </template>
                <template v-else-if="props.capabilityMarkScheme.template === 'TotalMarkFractional'">
                    / {{ capability.maxValue }}
                </template>
            </span>

            <a
                v-if="showAssignFromButton"
                class="fieldAssign"
                data-use-descriptor-values
                @click.prevent="useDerivedMark"
            >
                <!-- avoid showing "assign null from ...", only show if there's a meaningful value -->
                <!-- TODO: confirm if this works, otherwise (hasSelectedDescriptor && hasSelectedValuedDescriptor && capabilityHasValuedDescriptors) is the old logic -->
                <template v-if="derivedCapabilityMark.scaledMark !== null">
                    Assign
                    <!-- TODO: data-group-descriptor-sum -->
                    <span class="descriptor-sum">{{ derivedCapabilityMark.value }}</span>
                    from {{ locale.rubricCriterion.plural() }}
                </template>
                <template v-else>
                    Remove {{ locale.grades.singular() }} based on {{ locale.rubricCriterion.plural() }}
                </template>
            </a>

            <!-- Self-Assessment -->
            <p v-if="props.selfAssessmentCapabilityMark?.humanReadableMark" class="meta">
                {{ props.selfAssessmentCapabilityMark.humanReadableMark }}
                given in
                {{ locale.selfAssessment.singular() }}
            </p>
        </th>
    </tr>
</template>
