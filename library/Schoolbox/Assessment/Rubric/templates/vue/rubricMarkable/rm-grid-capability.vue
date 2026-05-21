<script setup lang="ts">
/**
 * Add rows in the markable rubric grid for a specific capability
 * (also known as a "group" in the past)
 */

import locale from "@js/terminology/rosettaStone";
import { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import MarkScheme from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/mark-scheme.vue";
import type { MarkScheme as CapabilityMarkScheme } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";
import type { User } from "@schoolboxLibrary/types/User";
import { applyMarkScheme, deriveScaledMark } from "./rubricUtils";
import type { RubricGridCapability, RubricGridLevel, RubricMarkedGridCapability } from "./types";
import RmGridIndicator from "./rm-grid-indicator.vue";

interface Props {
    capability: RubricGridCapability;
    selfAssessmentCapabilityMark: RubricMarkedGridCapability | null;
    capabilityMarkScheme: CapabilityMarkScheme;
    levels: RubricGridLevel[];
    participantId: User["id"] | null;
    showSummaryCapability: boolean;
    showDescriptorValue: boolean;
    isSelfGrading: boolean;
    isSelfAssess: boolean;
    isStudentView: boolean;
}
const props = defineProps<Props>();

const capabilityMark = defineModel<RubricMarkedGridCapability>("capabilityMark", { required: true });

const componentId = window.Vue.useId();
const markSchemeInputId = `capability-${props.capability.id}-mark-${componentId}`;

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

    const capabilityMax = props.capability.maxValue ?? 0;
    const derivedMax = props.capability.derivedMaxValue;

    const scale = derivedMax > 0 ? capabilityMax / derivedMax : 1;

    let scaledValue: number = value * scale;
    if (scaledValue % 1 !== 0) {
        scaledValue = parseFloat(scaledValue.toFixed(2));
    }

    return scaledValue;
}

/**
 * The mark of this capability derived from the current indicator marks
 */
const derivedCapabilityMark = window.Vue.computed(() => {
    const sum = indicatorMarkSum.value;
    const scaledMark = scaleMark(sum);
    const { value, humanReadableMark } = applyMarkScheme(
        scaledMark,
        props.capability.maxValue,
        props.capabilityMarkScheme,
    );

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

    if (props.capabilityMarkScheme.mode === MarkTypeSlug.LETTER) {
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

/**
 * When an indicator mark is updated, update the capability mark under certain conditions
 */
function onIndicatorMarkUpdated(): void {
    // Figure out if we should update the capability mark, based on the logic in SA-17198
    // The capability mark should always be kept in sync if:
    const shouldUpdateCapabilityMark =
        // - the capability mark is NOT customised
        !isCustomised.value ||
        // - or if there are no longer any selected descriptors in the capability (the user *just* deselected all descriptors)
        !hasSelectedDescriptor.value ||
        // - or if the capability mark is currently empty (has never been set, or was cleared by the user at some point)
        capabilityMark.value.value === null ||
        capabilityMark.value.value.trim() === "";

    if (shouldUpdateCapabilityMark) {
        useDerivedMark();
    }
}

window.Vue.onMounted(() => {
    // Track the initial customised state based on:
    // - if the actual scaled mark differs from the derived one on page load (equivalent to if the assign from button is showing)
    // - OR if there's a value, but all the descriptors in this capability are valueless (the only way to get a mark is to manually set it)
    if (showAssignFromButton.value || !capabilityHasValuedDescriptors.value) {
        isCustomised.value = true;
    }
});

/** When the capability mark changes (either from user input or automatically via descriptor updates) */
window.Vue.watch(
    () => capabilityMark.value.value,
    () => {
        // force sync the scaledMark/humanReadableMark attrs whenever the value changes
        // the value we recieve in capabilityMark.value.value here may be a percentage, letter grade, or total mark
        // depending on the mark type, so first rederive the scaled mark based on the raw value and re-apply the mark scheme
        const scaledMark = deriveScaledMark(
            capabilityMark.value.value,
            props.capability.maxValue,
            props.capabilityMarkScheme,
        );
        const { humanReadableMark } = applyMarkScheme(
            scaledMark,
            props.capability.maxValue,
            props.capabilityMarkScheme,
        );

        capabilityMark.value.scaledMark = scaledMark;
        capabilityMark.value.humanReadableMark = humanReadableMark;

        // if the mark now differs to the derived mark (aka assign from is showing), consider it customised
        if (showAssignFromButton.value) {
            isCustomised.value = true;
        } else {
            // remove the customised flag if at least one descriptor has a value AND the scaled mark is not empty.
            // We do this as when all descriptors are valueless, the only way a scaled mark can be set is manually
            // But we don't want to show the descriptor values button in this case as it'd never have a useful value
            if (capabilityHasValuedDescriptors.value && derivedCapabilityMark.value.scaledMark !== null) {
                isCustomised.value = false;
            }

            // or, if the capability mark input is actually empty then we should remove the customised flag
            if (capabilityMark.value.value === null || capabilityMark.value.value.trim() === "") {
                isCustomised.value = false;
            }
        }
    },
);
</script>

<template>
    <tr v-if="indicators.length">
        <th
            class="rb-group-name markdown"
            :rowspan="indicators.length + (showSummaryForThisCapability ? 2 : 1) /* header+summary */"
        >
            <!-- eslint-disable-next-line vue/no-v-html -- scrubbed in the backend -->
            <span v-html="props.capability.name"></span>
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
        :show-descriptor-value="props.showDescriptorValue"
        :is-self-grading="props.isSelfGrading"
        :is-self-assess="props.isSelfAssess"
        :is-student-view="props.isStudentView"
        @indicator-mark-updated="onIndicatorMarkUpdated"
    />

    <tr v-if="showSummaryForThisCapability">
        <th class="rb-group-summary" :colspan="props.levels.length + 1 /* descriptors + indicator column */">
            <span>
                <label :for="markSchemeInputId"> {{ locale.rubricGroup.proper() }} mark </label>

                <mark-scheme
                    :id="markSchemeInputId"
                    v-model:mark="capabilityMark.value"
                    :name="markSchemeInputId"
                    :mark-scheme="props.capabilityMarkScheme"
                    :submission-error-states="[] /** no error states here */"
                    :limit="capability.maxValue ?? undefined"
                    :error-placeholder-selector="`#${markSchemeInputId}-error`"
                    :allow-invalid-mark="true /** since we want to be able to show the assign from button if invalid */"
                    class="rb-value-input groupMark"
                />

                <template v-if="props.capabilityMarkScheme.mode === MarkTypeSlug.PERCENT"> % </template>
                <template v-else-if="props.capabilityMarkScheme.mode === MarkTypeSlug.TOTAL">
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
                <template v-if="derivedCapabilityMark.scaledMark !== null">
                    Assign
                    <span class="descriptor-sum">{{ derivedCapabilityMark.value }}</span>
                    from {{ locale.rubricCriterion.plural() }}
                </template>
                <template v-else>
                    Remove {{ locale.grades.singular() }} based on {{ locale.rubricCriterion.plural() }}
                </template>
            </a>

            <!-- make sure the error appears after the assign from button -->
            <div :id="`${markSchemeInputId}-error`"></div>

            <!-- Self-Assessment -->
            <p v-if="props.selfAssessmentCapabilityMark?.humanReadableMark" class="meta">
                ({{ props.selfAssessmentCapabilityMark.humanReadableMark }}
                given in
                {{ locale.selfAssessment.singular() }})
            </p>
        </th>
    </tr>
</template>
