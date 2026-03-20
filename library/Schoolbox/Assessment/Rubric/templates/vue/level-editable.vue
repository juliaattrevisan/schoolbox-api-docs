<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import type { RubricGridLevel } from "./types";

const levelName = defineModel<RubricGridLevel["name"]>("levelName", { required: true });
</script>

<template>
    <!-- TODO: data-sequencer-group="levels" -->
    <th class="rb-level">
        <input v-model="levelName" type="text" :placeholder="`${locale.rubricLevel.proper()} name`" maxlength="128" />

        <!-- TODO: sequencer (data-sequencer-h="levels", data-sequencer-left, data-sequencer-right)-->
        <span class="sequencer">
            <a class="icon-previous" title="Move left"></a>
            <a class="icon-next" title="Move right"></a>
        </span>
    </th>
</template>
