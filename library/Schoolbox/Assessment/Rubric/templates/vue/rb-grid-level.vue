<script setup lang="ts">
/**
 * A heading cell for showing a "level" in the editable rubric grid
 */

import locale from "@js/terminology/rosettaStone";
import type { RubricGridLevel } from "./builderTypes";

interface Props {
    canMovePrev: boolean;
    canMoveNext: boolean;
}
const props = defineProps<Props>();

const levelName = defineModel<RubricGridLevel["name"]>("name", { required: true });

const emit = defineEmits<{ move: [direction: -1 | 1] }>();

const componentId = window.Vue.useId();
</script>

<template>
    <th class="rb-level" data-rubric-grid-level>
        <input
            :id="`rb-level-name-${componentId}`"
            v-model.trim="levelName"
            type="text"
            :placeholder="`${locale.rubricLevel.proper()} name`"
            maxlength="128"
        />

        <span class="sequencer">
            <a
                class="sbx-icon-only-button no-text"
                :class="{ disabled: !props.canMovePrev }"
                title="Move left"
                aria-label="Move left"
                :aria-disabled="!props.canMovePrev"
                @click.prevent="props.canMovePrev && emit('move', -1)"
            >
                <sbx-icon name="previous" />
            </a>
            <a
                class="sbx-icon-only-button no-text"
                :class="{ disabled: !props.canMoveNext }"
                title="Move right"
                aria-label="Move right"
                :aria-disabled="!props.canMoveNext"
                @click.prevent="props.canMoveNext && emit('move', 1)"
            >
                <sbx-icon name="next" />
            </a>
        </span>
    </th>
</template>
