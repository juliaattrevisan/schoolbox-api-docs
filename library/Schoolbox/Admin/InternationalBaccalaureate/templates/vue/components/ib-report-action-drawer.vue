<script setup lang="ts">
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer.vue";
import type { StudentRow } from "../../js/types/ibReportTypes";

interface Props {
    selectedCount: number;
    isLoading: boolean;
    activeSection: StudentRow["stage"] | null;
}
defineProps<Props>();

defineEmits<{
    completePreparation: [];
    approveOnBehalf: [];
    resetDetails: [];
    manuallyRegister: [];
    clearSelection: [];
}>();
</script>

<template>
    <bottom-drawer :show-drawer="selectedCount > 0">
        <span class="selected-count">
            <em>{{ selectedCount }} student{{ selectedCount === 1 ? "" : "s" }} selected</em>
        </span>
        <button type="button" class="button-clear" @click="$emit('clearSelection')">Clear selection</button>
        <div class="l-flex-row l-flex--justify l-gap-xs">
            <button
                v-if="activeSection === 'preparation'"
                type="button"
                class="button sbx-button--has-icon l-flex__item--1-auto"
                :disabled="isLoading"
                @click="$emit('completePreparation')"
            >
                <sbx-icon name="notifications-hollow"></sbx-icon>
                Complete Preparation
            </button>
            <button
                v-if="activeSection === 'unconfirmed'"
                type="button"
                class="button sbx-button--has-icon l-flex__item--1-auto"
                :disabled="isLoading"
                @click="$emit('approveOnBehalf')"
            >
                <sbx-icon name="approve"></sbx-icon>
                Approve on behalf of student
            </button>
            <button
                v-if="activeSection === 'unconfirmed' || activeSection === 'confirmed' || activeSection === 'failed'"
                type="button"
                class="button sbx-button--has-icon l-flex__item--1-auto"
                :disabled="isLoading"
                @click="$emit('resetDetails')"
            >
                <sbx-icon name="undo"></sbx-icon>
                Reset All Details
            </button>
            <button
                v-if="activeSection === 'confirmed' || activeSection === 'failed'"
                type="button"
                class="button sbx-button--has-icon l-flex__item--1-auto"
                :disabled="isLoading"
                @click="$emit('manuallyRegister')"
            >
                <sbx-icon name="approve"></sbx-icon>
                Mark as manually registered
            </button>
            <button
                type="button"
                class="button sbx-button--has-icon l-flex__item--1-auto"
                disabled
                aria-disabled="true"
                title="Coming soon"
            >
                Submit to IB
            </button>
        </div>
    </bottom-drawer>
</template>

<style scoped>
.selected-count {
    display: inline-block;
    margin-right: 0.5rem;
}

.button-clear {
    background: none;
    display: inline-block;
    font-size: 0.8rem;
    padding: 0;
    vertical-align: baseline;
}
</style>
