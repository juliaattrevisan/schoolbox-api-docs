<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
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
                    :maxlength="MAX_INDICATOR_LENGTH"
                    :placeholder="`${locale.rubricCriterion.proper()} name`"
                ></textarea>
            </label>
        </th>

        <td v-for="level in props.levels" :key="level.id" :class="hasUsage ? '' : 'hide-v'">
            <!-- todo: descriptors -->
        </td>

        <td
            v-if="props.levels.length < props.maxLevels"
            :class="hasUsage ? '' : 'hide-v'"
            @click="hasUsage = true"
        ></td>
    </tr>
</template>

<style scoped>
.rb-indicator-name {
    white-space: pre-line;
}
</style>
