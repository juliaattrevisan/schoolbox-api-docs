<script setup lang="ts">
/**
 * to display a loading note while loading all records for print
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";

const props = defineProps<{
    loadError: string | null;
}>();

const isLoading = defineModel<boolean>("is-loading", { default: false }); // to show/hide the loading modal
</script>

<template>
    <basic-modal v-model:is-open="isLoading" size="s" :show-close-button="false" :has-focus-trap="false">
        <data-load-error v-if="props.loadError" data-title="record" :further-info="props.loadError" />
        <div v-else class="container u-flex-col u-flex--center l-gap-s" role="status" aria-live="polite">
            <div class="loader"></div>
            <h3><em>Generating print report, please wait... </em></h3>
        </div>
    </basic-modal>
</template>

<style scoped>
@media print {
    .container {
        display: none;
    }
}
</style>
