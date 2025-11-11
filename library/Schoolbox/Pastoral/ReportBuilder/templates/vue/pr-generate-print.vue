<script setup lang="ts">
/**
 * to display a loading note while loading all records for print
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";

const isLoading = defineModel<boolean>("is-loading", { default: false }); // to show/hide the loading modal
</script>

<template>
    <basic-modal v-model:is-open="isLoading" size="s" :show-close-button="false">
        <div class="container l-flex-col l-flex--center l-gap-s">
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
