<script setup lang="ts">
/**
 * The editable rubric grid shown on the activity create/modify page (currently due work, soon to be more)
 */

import type { Ref } from "vue";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { assertUnreachable } from "@js/util/errorUtils";
import locale from "@js/terminology/rosettaStone";
import { shiftArrayItem } from "@js/util/arrayUtils";
import type { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import RbGridLevel from "./rb-grid-level.vue";
import RbGridCapability from "./rb-grid-capability.vue";
import RbApplyResultSet from "./rb-apply-result-set.vue";
import { translateGridToFormPayload } from "./payload";
import type { RubricGridCreateCapabilityResponse, RubricGridCreateIndicatorResponse } from "./payload";
import { MAX_CAPABILITY_LENGTH } from "./types";
import type { RubricGrid, RubricGridLevel, RubricGridCapability, RubricGridDescriptor, ResultSet } from "./types";

interface Props {
    rubric: RubricGrid;
    folderId: number;
    maxLevels: number;
    capabilityMarkType: MarkTypeSlug;
    routeRubricCreate: string;
    resultSets: ResultSet[];
    hasBeenExpanded: boolean;
}
const props = defineProps<Props>();

const rubricGridLevels = defineModel<RubricGrid["levels"]>("rubricGridLevels", { required: true });
const rubricGridCapabilities = defineModel<RubricGrid["capabilities"]>("rubricGridCapabilities", { required: true });

const formValueRef = window.Vue.useTemplateRef<HTMLInputElement>("formValueRef");

/**
 * Move a level left or right
 * @param index the index of the level to move
 * @param direction the direction to move
 */
function moveLevel(index: number, direction: -1 | 1): void {
    shiftArrayItem(rubricGridLevels.value, index, direction);

    // move corresponding descriptors
    for (const capability of rubricGridCapabilities.value) {
        for (const indicator of capability.indicators) {
            shiftArrayItem(indicator.descriptors, index, direction);
        }
    }
}

/**
 * Adds a level to the grid, on the right-hand side
 * @param levelName the name of the new level
 * @param levelValue the default value to give to the new descriptors
 */
function addLevel(levelName: RubricGridLevel["name"], levelValue: RubricGridDescriptor["maxValue"]): void {
    const lowestId = Math.min(...rubricGridLevels.value.map((l) => l.id), 0);
    const newLevel: RubricGridLevel = {
        id: lowestId - 1, // negative IDs are unsaved
        name: levelName,
    };
    rubricGridLevels.value.push(newLevel);

    // add corresponding descriptors
    for (const capability of rubricGridCapabilities.value) {
        for (const indicator of capability.indicators) {
            const lowestDescriptorId = Math.min(...indicator.descriptors.map((d) => d.id), 0);
            indicator.descriptors.push({
                id: lowestDescriptorId - 1, // negative IDs are unsaved
                description: "",
                maxValue: levelValue,
            });
        }
    }
}

/**
 * Delete a level from the grid, removing all associated descriptors
 * @param index the index of the level to delete
 */
function deleteLevel(index: number): void {
    rubricGridLevels.value.splice(index, 1);

    for (const capability of rubricGridCapabilities.value) {
        for (const indicator of capability.indicators) {
            indicator.descriptors.splice(index, 1);
        }
    }
}

/**
 * Returns the normalised string for comparison to avoid duplicates.
 * @param string the string to normalise
 * @returns normalised string
 * @see /library/Schoolbox/Assessment/Rubric/Util.php::normalizeName (must use the same logic)
 */
function normaliseForComparison(string: string): string {
    // Ensure var really is a string before performing string operations
    return string.toString().replace(/\s+/g, " ").trim().toLowerCase();
}

const isCreating = window.Vue.ref(false); // whether we're currently creating a new capability/indicator
const newCapabilityName = window.Vue.ref("");

/**
 * When a key is pressed in the "add capability" or "add indicator" textarea,
 * if it's a non-shift return (i.e. shift+return is ignored), add a new capability/indicator to the grid
 * @param event the keydown event
 * @param type the type of item to add, either capability or indicator
 * @param capability if adding an indicator, the capability to add the new item to (will push to capability.indicators)
 * @param nameRef a ref pointing to the name of the new item
 */
async function onAddKeydown<TType extends "capability" | "indicator">(
    event: KeyboardEvent,
    type: TType,
    capability: TType extends "indicator" ? RubricGridCapability : null,
    nameRef: Ref<string, string>,
): Promise<void> {
    if (event.key !== "Enter" || event.shiftKey) {
        return;
    }

    event.preventDefault();

    const name = nameRef.value.trim();
    if (name.length === 0 || isCreating.value) {
        return; // prevent blank and double-fire from keydown to blue
    }

    /**
     * Ensure user is not trying to add a duplicate group. The server-side validation will prevent
     * duplicate criterion, but cannot prevent duplicate groups as they are not persisted until a indicator
     * name is entered.
     */
    const normalisedName = normaliseForComparison(name);
    const nameLocale = type === "capability" ? locale.rubricGroup : locale.rubricCriterion;

    const toast = window.sbProps.toastActivate(`Creating ${nameLocale.singular()}...`);
    if (rubricGridCapabilities.value.some((c) => normaliseForComparison(c.name) === normalisedName)) {
        toast.timeout({
            text: `${nameLocale.proper()} name already exists!`,
            isSuccess: false,
        });
        return;
    }

    isCreating.value = true; // prevent mods & double-fire
    nameRef.value = "";

    const response = await apiRequest<
        TType extends "capability" ? RubricGridCreateCapabilityResponse : RubricGridCreateIndicatorResponse
    >({
        url: props.routeRubricCreate,
        method: "POST",
        body: {
            type,
            name,
            folderId: props.folderId,
            rubricId: props.rubric.id,
            levelCount: rubricGridLevels.value.length,
            capabilityId: type === "indicator" && capability!.id >= 0 ? capability!.id : null,
            capabilityName: type === "indicator" ? capability!.name : null,
        },
    });

    isCreating.value = false;
    if (!response.success) {
        toast.timeout({ text: response.message, isSuccess: false });
        return;
    }

    toast.timeout({ text: `${nameLocale.proper()} created!` });

    if (type === "capability") {
        // TType is "capability", so response is RubricGridCreateCapabilityResponse
        const newCapability = response.data as RubricGridCreateCapabilityResponse;
        if (newCapability.id === null) {
            // The API for adding capabilities may/will return `null` for the new ID
            // however, we need a unique ID to use as a key in Vue
            const lowestId = Math.min(...rubricGridCapabilities.value.map((c) => c.id), 0);
            newCapability.id = lowestId - 1; // negative IDs are unsaved
        }

        rubricGridCapabilities.value.push(newCapability);
    } else if (type === "indicator") {
        // TType is "indicator", so response is RubricGridCreateIndicatorResponse
        const { capabilityId, indicator } = response.data as RubricGridCreateIndicatorResponse;

        // If the capability was new, then it would've been created and given an actual ID.
        // need to update our knowledge of the new ID if so.
        if (capability!.id < 0 && capabilityId !== null) {
            capability!.id = capabilityId;
        }

        capability!.indicators.push(indicator);
    } else {
        assertUnreachable(type);
    }
}

/**
 * When the return key is pressed, add a new capability to the grid
 * (this needs to exist as the ref can't be passed directly when inside <template>)
 * @param event the keydown event
 */
async function onAddCapabilityKeydown(event: KeyboardEvent): Promise<void> {
    await onAddKeydown(event, "capability", null, newCapabilityName);
}

/** The total number of columns in the grid */
const totalColumns = window.Vue.computed(() => {
    let columns = rubricGridLevels.value.length + 2; // levels + capability + criterion columns

    if (rubricGridLevels.value.length < props.maxLevels) {
        columns += 1; // "add level" button
    }

    return columns;
});

/**
 * Applies a result set to the rubric grid,
 * replacing all levels and descriptors with the values from the result set
 * @param resultSet the result set to apply
 */
function applyResultSet(resultSet: ResultSet): void {
    // this wipes all the existing levels/descriptors and replaces them with the ones from the results set
    // the new descriptor values just count up from 0

    // delete all existing levels
    while (rubricGridLevels.value.length) {
        deleteLevel(rubricGridLevels.value.length - 1);
    }

    // add each new level
    for (const result of resultSet.results) {
        addLevel(result.label, result.value);
    }
}

/** Emit a change event so jquery.alaressValidate can pick it up for grid-based rules */
const formValue = window.Vue.computed(() => JSON.stringify(translateGridToFormPayload(props.rubric)));
window.Vue.watch(formValue, () => {
    if (formValueRef.value) {
        window.jQuery(formValueRef.value).valid();
    }
});
</script>

<template>
    <rb-apply-result-set
        v-if="props.resultSets.length"
        :result-sets="props.resultSets"
        @apply-result-set="applyResultSet"
    />

    <!-- hidden input to submit the rubric grid as JSON -->
    <input
        ref="formValueRef"
        type="hidden"
        :name="`rubric[${rubric.id}]`"
        :value="JSON.stringify(translateGridToFormPayload(rubric))"
        :data-rubric-form-input="
            hasBeenExpanded || undefined /* delay this until the actual content exists so validate can see it */
        "
    />

    <h3 class="subheader show-for-medium-down">Levels</h3>
    <div v-if="hasBeenExpanded" class="scrollable">
        <table class="rb stack-for-medium-down" data-rubric :data-test="`rubric-grid-${rubric.id}`">
            <thead>
                <tr>
                    <th class="rb-no-cell">
                        <!-- capabilities -->
                        {{ locale.rubricGroup.properPlural() }}
                    </th>
                    <th class="rb-no-cell">
                        <!-- indicators -->
                        {{ locale.rubricCriterion.properPlural() }}
                    </th>
                    <rb-grid-level
                        v-for="(level, index) in rubricGridLevels"
                        :key="level.id"
                        v-model:name="level.name"
                        :can-move-prev="index > 0"
                        :can-move-next="index < rubricGridLevels.length - 1"
                        @move="(dir) => moveLevel(index, dir)"
                    />
                    <th v-if="rubricGridLevels.length < props.maxLevels">
                        <!-- Add a new level -->
                        <a href="#" data-test="rubric-spawn-level" @click.prevent="() => addLevel('', null)">
                            &plus; {{ locale.rubricLevel.proper() }}
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <rb-grid-capability
                    v-for="(capability, index) in rubricGridCapabilities"
                    :key="capability.id"
                    v-model:name="capability.name"
                    v-model:indicators="capability.indicators"
                    v-model:max-value="capability.maxValue"
                    :capability="capability"
                    :levels="rubricGridLevels"
                    :total-columns="totalColumns"
                    :is-creating="isCreating"
                    :max-levels="props.maxLevels"
                    :capability-mark-type="props.capabilityMarkType"
                    :use-mark-range="props.rubric.useMarkRange"
                    :can-move-prev="index > 0"
                    :can-move-next="index < rubricGridCapabilities.length - 1"
                    @move="(dir) => shiftArrayItem(rubricGridCapabilities, index, dir)"
                    @add-indicator-keydown="(e, nameRef) => onAddKeydown(e, 'indicator', capability, nameRef)"
                />

                <tr class="rb-group-header rb-new-group">
                    <!-- Add a new group -->
                    <th :colspan="totalColumns">
                        <textarea
                            v-model.trim="newCapabilityName"
                            :placeholder="`+ Type to add new ${locale.rubricGroup.singular()}`"
                            :maxlength="MAX_CAPABILITY_LENGTH"
                            :disabled="isCreating || undefined"
                            @keydown="(e) => onAddCapabilityKeydown(e)"
                        ></textarea>
                    </th>
                </tr>
            </tbody>
        </table>
    </div>
</template>
