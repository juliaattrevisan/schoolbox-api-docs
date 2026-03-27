<script setup lang="ts">
/**
 * Add a row in the editable rubric grid for a specific indicator (child of a capability)
 * (also known as a "criterion" in the past)
 *
 * The indicator itself can be edited from the left column (which will be next
 * to a capability already). it can have multiple descriptors, each descriptor has
 * its own column corresponding to the associated level.
 *
 * There is an additional blank column at the end matching the "+ Level" column
 * in the header if it exists
 */

import locale from "@js/terminology/rosettaStone";
import DescriptorEditable from "./descriptor-editable.vue";
import { MAX_INDICATOR_LENGTH } from "./types";
import type { RubricGridIndicator, RubricGridLevel } from "./types";

interface Props {
    indicator: RubricGridIndicator;
    levels: RubricGridLevel[];
    maxLevels: number;
    canMovePrev: boolean;
    canMoveNext: boolean;
}
const props = defineProps<Props>();

const indicatorName = defineModel<RubricGridIndicator["name"]>("name", { required: true });
const hasUsage = defineModel<boolean>("hasUsage", { required: true });

const checkboxId = `rb-indicator-${window.Vue.useId()}`;

const emit = defineEmits<{ move: [direction: -1 | 1] }>();

/* only add maxlength to fields that actually change. to then only validate those fields characters count */
const indicatorNameHasChanged = window.Vue.ref(false);

window.Vue.onMounted(() => {
    if (document.readyState === "complete") {
        // If the document isn't ready, we can't reflow tooltips yet
        // As vue loads the template after foundation has been initialised, we need to reinitialise the tooltips
        window.jQuery(document).foundation("tooltip", "reflow");
    }
});
</script>

<template>
    <tr>
        <th>
            <span class="sequencer">
                <a
                    class="icon-up-arrow"
                    :class="{ disabled: !props.canMovePrev }"
                    title="Move up"
                    :aria-disabled="!props.canMovePrev"
                    @click.prevent="props.canMovePrev && emit('move', -1)"
                ></a>
                <a
                    class="icon-down-arrow"
                    :class="{ disabled: !props.canMoveNext }"
                    title="Move down"
                    :aria-disabled="!props.canMoveNext"
                    @click.prevent="props.canMoveNext && emit('move', 1)"
                ></a>
            </span>

            <input :id="checkboxId" v-model="hasUsage" type="checkbox" value="1" />

            <label
                v-if="props.indicator.inUseExcludingAssessment"
                class="checkbox-label rb-criterion rb-readonly rb-indicator-name"
                :for="checkboxId"
            >
                {{ indicatorName }}
                <span
                    data-tooltip
                    class="hint has-tip tip-right"
                    data-width="200"
                    :title="`This ${locale.rubricCriterion.singular()} cannot be modified because it is in use elsewhere`"
                >
                    ?
                </span>
            </label>

            <label v-else class="checkbox-label rb-criterion rb-editable" :for="checkboxId">
                <textarea
                    v-model.trim.lazy="indicatorName"
                    :maxlength="indicatorNameHasChanged ? MAX_INDICATOR_LENGTH : undefined"
                    :placeholder="`${locale.rubricCriterion.proper()} name`"
                    @change.once="indicatorNameHasChanged = true"
                ></textarea>
            </label>
        </th>

        <descriptor-editable
            v-for="(descriptor, levelIndex) in props.indicator.descriptors"
            :key="descriptor.id"
            v-model:indicator-enabled="hasUsage"
            v-model:description="descriptor.description"
            v-model:max-value="descriptor.maxValue"
            :level-name="props.levels[levelIndex].name"
            :indicator-name="props.indicator.name"
        ></descriptor-editable>

        <td
            v-if="props.levels.length < props.maxLevels"
            class="hide-for-medium-down"
            :class="hasUsage ? '' : 'hide-v'"
            @click="hasUsage = true"
        >
            &nbsp;
        </td>
    </tr>
</template>

<style scoped>
.rb-indicator-name {
    white-space: pre-line;
}
</style>
