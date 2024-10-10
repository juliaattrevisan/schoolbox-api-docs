<script setup lang="ts">
/**
 * To customise the portrait image displayed on a user profile page
 */

import PeFieldsActiveItem from "./pe-fields-active-item.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();
</script>

<template>
    <template v-if="peStore.selectedRoleType">
        <pe-fields-active-item
            v-for="mandatoryField in peStore.selectedRoleType.fields.mandatory"
            :key="mandatoryField.field"
            :field="mandatoryField"
            :dragging="false"
        />
    </template>
</template>

<style scoped></style>
