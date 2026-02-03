<script setup lang="ts">
/**
 * Display a list of pastoral flags
 */

import type { PastoralFlag } from "@schoolboxLibrary/Pastoral/types/PastoralFlag";

const props = defineProps<{
    flags: PastoralFlag[];
}>();

/**
 * Handle flag selection, triggering the modal to open
 * @param event - the href of the flag (as sbx-label is a custom element it'll be a CustomEvent)
 */
function onFlagSelect(event: CustomEvent): void {
    console.warn(event.detail);
}
</script>

<template>
    <div class="l-flex-row l-gap-xs">
        <sbx-label
            v-for="flag in props.flags"
            :key="`flag-${flag.typeId}-${flag.subtypeId}`"
            :title="flag.title"
            :text="flag.count.toString()"
            :icon="flag.icon"
            :background-color="flag.bgColor"
            :text-color="flag.fgColor"
            :href="flag.destinationUrl"
            :is-button="true"
            @select="onFlagSelect"
        />
    </div>
</template>

<style scoped></style>
