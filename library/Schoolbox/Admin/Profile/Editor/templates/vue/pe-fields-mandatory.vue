<script setup lang="ts">
/**
 * The list of mandatory fields for the selected role type
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
