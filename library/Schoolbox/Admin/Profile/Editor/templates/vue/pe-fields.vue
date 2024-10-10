<script setup lang="ts">
/**
 * To customise the fields displayed on a user profile page
 */

import PeFieldsAvailable from "./pe-fields-available.vue";
import PeFieldsSelected from "./pe-fields-selected.vue";
import PeFieldsMandatory from "./pe-fields-mandatory.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();
</script>

<template>
    <div v-if="peStore.selectedRoleType" class="l-grid l-grid--12 l-gap-m l-mar-m--top">
        <div class="small-12 large-3">
            <pe-fields-mandatory />
        </div>
        <div class="small-12 large-9">
            <pe-fields-available />
            <pe-fields-selected />
        </div>
    </div>
</template>

<style scoped></style>
