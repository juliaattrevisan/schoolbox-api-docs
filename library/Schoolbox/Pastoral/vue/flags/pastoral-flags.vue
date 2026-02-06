<script setup lang="ts">
/**
 * Display a list of pastoral flags
 */

import type { Routes, Config } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import type { PastoralFlag } from "@schoolboxLibrary/Pastoral/types/PastoralFlag";
import PastoralRecordsModal from "@schoolboxLibrary/Pastoral/vue/pastoral-records-modal.vue";

const props = defineProps<{
    flags: PastoralFlag[];
    pastoralConfig?: Config;
    pastoralRoutes?: Routes;
}>();

/**
 * To pass through to the pastoral-records-modal that displays when a flag is selected
 */
if (props.pastoralConfig && props.pastoralRoutes) {
    window.Vue.provide("pastoralConfig", props.pastoralConfig);
    window.Vue.provide("pastoralRoutes", props.pastoralRoutes);
}

/**
 * The details to display in the modal and trigger its opening
 */
const url = window.Vue.ref<string>("");
const title = window.Vue.ref<string>("");
const meta = window.Vue.ref<string>("");

/**
 * Handle flag selection, triggering the modal to open
 * @param event - the href of the flag (as sbx-label is a custom element it'll be a CustomEvent)
 */
function onFlagSelect(event: CustomEvent): void {
    const eventDetail = event.detail as { href: string; title: string }[];
    url.value = eventDetail[0].href;
    title.value = eventDetail[0].title;
}
</script>

<template>
    <div class="l-flex-row l-gap-xs">
        <sbx-label
            v-for="flag in props.flags"
            :key="`flag-${flag.typeId}-${flag.subtypeId ?? 0}`"
            :title="flag.title"
            :text="flag.count.toString()"
            :icon="flag.icon"
            :background-color="flag.bgColor"
            :text-color="flag.fgColor"
            :href="flag.destinationUrl"
            :is-button="true"
            :data-test="`pastoral-flags-${flag.typeId}-${flag.subtypeId ?? 0}`"
            @select="onFlagSelect"
        />
    </div>
    <pastoral-records-modal v-model:url="url" :title="title" :meta="meta" />
</template>

<style scoped></style>
