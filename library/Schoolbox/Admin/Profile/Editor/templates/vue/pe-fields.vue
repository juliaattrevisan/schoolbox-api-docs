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
    <template v-if="peStore.selectedRoleType">
        <div class="small-12 xlarge-4 xxlarge-3 island">
            <pe-fields-mandatory />
        </div>
        <div class="small-12 xlarge-8 xxlarge-9 island">
            <h3 class="subheader">Fields</h3>
            <pe-fields-available />
            <pe-fields-selected />
        </div>
    </template>
</template>

<style scoped></style>
