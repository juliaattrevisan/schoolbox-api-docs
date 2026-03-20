<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import type { RubricGridLevel } from "./types";

interface Props {
    canMovePrev: boolean;
    canMoveNext: boolean;
}
const props = defineProps<Props>();

const levelName = defineModel<RubricGridLevel["name"]>("name", { required: true });

const emit = defineEmits<{ move: [direction: -1 | 1] }>();
</script>

<template>
    <th class="rb-level">
        <input
            v-model.trim="levelName"
            type="text"
            :placeholder="`${locale.rubricLevel.proper()} name`"
            maxlength="128"
        />

        <span class="sequencer">
            <a
                class="icon-previous"
                :class="{ disabled: !props.canMovePrev }"
                title="Move left"
                :aria-disabled="!props.canMovePrev"
                @click.prevent="props.canMovePrev && emit('move', -1)"
            ></a>
            <a
                class="icon-next"
                :class="{ disabled: !props.canMoveNext }"
                title="Move right"
                :aria-disabled="!props.canMoveNext"
                @click.prevent="props.canMoveNext && emit('move', 1)"
            ></a>
        </span>
    </th>
</template>
