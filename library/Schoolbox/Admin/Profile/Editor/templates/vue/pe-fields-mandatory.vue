<script setup lang="ts">
/**
 * The list of mandatory fields for the selected role type
 */

import PeFieldsMandatoryItem from "./pe-fields-mandatory-item.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();
</script>

<template>
    <template v-if="peStore.selectedRoleType">
        <pe-fields-mandatory-item
            v-for="mandatoryField in peStore.selectedRoleType.fields.mandatory"
            :key="mandatoryField.field"
            :field="mandatoryField"
        />
    </template>
</template>

<style scoped></style>
