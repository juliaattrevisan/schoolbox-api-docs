<script setup lang="ts">
/**
 * Display a list of pastoral flags
 */

import type { PastoralFlag } from "@schoolboxLibrary/Pastoral/types/PastoralFlag";
import PastoralRecordsModal from "@schoolboxLibrary/Pastoral/vue/pastoral-records-modal.vue";

const props = defineProps<{
    flags: PastoralFlag[];
}>();

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
    console.warn(event.detail);
    // temporary static values for testing
    url.value =
        "/pastoral/report/results/8/tab/studentByType/scope?column=total&selected=records&limit=20&row%5Bstudent_id%5D=13";
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
    <pastoral-records-modal v-model:url="url" :title="title" :meta="meta" />
</template>

<style scoped></style>
