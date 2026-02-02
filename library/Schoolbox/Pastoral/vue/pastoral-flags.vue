<script setup lang="ts">
/**
 * Display a list of pastoral flags
 */

import type { PastoralFlag } from "@schoolboxLibrary/Pastoral/types/PastoralFlag";

const props = defineProps<{
    userId: number;
    pastoralFlags: Record<string, PastoralFlag[]>;
}>();
</script>

<template>
    <div class="l-flex-row l-gap-xs">
        <sbx-label
            v-for="flag in pastoralFlags[props.userId]"
            :key="`flag-${flag.typeId}-${flag.subtypeId}`"
            :title="flag.title"
            :text="flag.count.toString()"
            :icon="flag.icon"
            :background-color="flag.bgColor"
            :text-color="flag.fgColor"
            :href="flag.destinationUrl"
        />
    </div>
</template>

<style scoped></style>
