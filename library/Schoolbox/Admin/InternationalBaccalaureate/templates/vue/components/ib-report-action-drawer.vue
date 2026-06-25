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
    renotify: [];
    approveOnBehalf: [];
    registerViaApi: [];
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
        <ul class="flex-list buttons">
            <li v-if="activeSection === 'preparation'">
                <button
                    type="button"
                    class="button sbx-button--has-icon"
                    :disabled="isLoading"
                    @click="$emit('completePreparation')"
                >
                    <sbx-icon name="notifications-hollow"></sbx-icon>
                    Complete preparation
                </button>
            </li>
            <li v-if="activeSection === 'unconfirmed'">
                <button
                    type="button"
                    class="button sbx-button--has-icon"
                    :disabled="isLoading"
                    @click="$emit('renotify')"
                >
                    <sbx-icon name="notifications-hollow"></sbx-icon>
                    Renotify
                </button>
            </li>
            <li v-if="activeSection === 'unconfirmed'">
                <button
                    type="button"
                    class="button sbx-button--has-icon l-flex__item--1-auto"
                    :disabled="isLoading"
                    @click="$emit('approveOnBehalf')"
                >
                    <sbx-icon name="approve"></sbx-icon>
                    Approve on behalf of student
                </button>
            </li>
            <li v-if="activeSection === 'unconfirmed' || activeSection === 'confirmed' || activeSection === 'failed'">
                <button
                    type="button"
                    class="button sbx-button--has-icon"
                    :disabled="isLoading"
                    @click="$emit('resetDetails')"
                >
                    <sbx-icon name="undo"></sbx-icon>
                    Reset all details
                </button>
            </li>
            <li v-if="activeSection === 'confirmed' || activeSection === 'failed'">
                <button
                    type="button"
                    class="button sbx-button--has-icon l-flex__item--1-auto"
                    :disabled="isLoading"
                    @click="$emit('manuallyRegister')"
                >
                    <sbx-icon name="international-baccalaureate"></sbx-icon>
                    Mark as manually registered
                </button>
            </li>
            <li v-if="activeSection === 'confirmed' || activeSection === 'failed'">
                <button
                    type="button"
                    class="button submit sbx-button--has-icon"
                    :disabled="isLoading"
                    @click="$emit('registerViaApi')"
                >
                    <sbx-icon name="export"></sbx-icon>
                    Register via API
                </button>
            </li>
        </ul>
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
