<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import type { RubricGridLevel } from "./types";

const levelName = defineModel<RubricGridLevel["name"]>("levelName", { required: true });

interface Props {
    canMoveLeft: boolean;
    canMoveRight: boolean;
}
const props = defineProps<Props>();

const emit = defineEmits<{
    move: [direction: "left" | "right"];
}>();
</script>

<template>
    <!-- TODO: data-sequencer-group="levels" -->
    <th class="rb-level">
        <input v-model="levelName" type="text" :placeholder="`${locale.rubricLevel.proper()} name`" maxlength="128" />

        <!-- TODO: sequencer (data-sequencer-h="levels", data-sequencer-left, data-sequencer-right)-->
        <span class="sequencer">
            <a
                class="icon-previous"
                :class="{ disabled: !props.canMoveLeft }"
                title="Move left"
                :aria-disabled="!props.canMoveLeft"
                @click.prevent="props.canMoveLeft && emit('move', 'left')"
            ></a>
            <a
                class="icon-next"
                :class="{ disabled: !props.canMoveRight }"
                title="Move right"
                :aria-disabled="!props.canMoveRight"
                @click.prevent="props.canMoveRight && emit('move', 'right')"
            ></a>
        </span>
    </th>
</template>
