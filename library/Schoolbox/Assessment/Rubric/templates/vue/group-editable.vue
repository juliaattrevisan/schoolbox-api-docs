<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { MAX_CAPABILITY_LENGTH, MAX_INDICATOR_LENGTH } from "./types";
import type { RubricGridGroup } from "./types";

interface Props {
    totalColumns: number;
    group: RubricGridGroup;
    canMovePrev: boolean;
    canMoveNext: boolean;
}
const props = defineProps<Props>();

const groupName = defineModel<RubricGridGroup["name"]>("name", { required: true });

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
    <!-- TODO: data-group data-sequencer-group data-groupname -->
    <tr class="rb-group-header">
        <!-- TODO: no criteria specified... etc -->
        <th rowspan="2">
            <!-- todo: can't sequence when no criteria, those should stay at the end... -->
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

            <!-- todo: how on earth can groupName be null? I doubt it can be... see group-editable.twig -->
            <label v-if="props.group.inUseExcludingAssessment" class="rb-group-name">
                {{ groupName }}
                <span
                    data-tooltip
                    class="hint has-tip tip-right"
                    data-width="200"
                    :title="`This ${locale.rubricGroup.singular()} cannot be modified because it contains one or more ${locale.rubricCriterion.plural()} that are in use elsewhere`"
                >
                    ?
                </span>
            </label>

            <textarea
                v-else
                v-model.trim.lazy="groupName"
                :maxlength="MAX_CAPABILITY_LENGTH"
                :placeholder="`${locale.rubricGroup.proper()} name`"
            ></textarea>
        </th>
    </tr>

    <tr class="rb-new-criterion">
        <!-- Add a new indicator (criterion) -->
        <th :colspan="totalColumns - 1 /* subtract the capability column */">
            <textarea
                :placeholder="`+ Type to add new ${locale.rubricCriterion.singular()} ${groupName ? `to ${groupName}` : ''}`"
                :maxlength="MAX_INDICATOR_LENGTH"
            ></textarea>
        </th>
    </tr>
</template>

<style scoped>
.rb-group-name {
    white-space: pre-line;
}
</style>
