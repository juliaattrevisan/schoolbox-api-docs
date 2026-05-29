<script setup lang="ts">
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer.vue";
import type { StudentRow } from "../../js/types/ibReportTypes";

interface Props {
    isLoading: boolean;
}
defineProps<Props>();

const selectedList = defineModel<StudentRow[]>("selectedList", { default: () => [] });

defineEmits<{
    completePreparation: [];
}>();

/**
 * Clear the selection
 */
function clearSelection(): void {
    selectedList.value = [];
}
</script>

<template>
    <bottom-drawer :show-drawer="selectedList.length > 0">
        <span class="selected-count">
            <em>{{ selectedList.length }} selected</em>
        </span>
        <button class="button-clear" @click.prevent="clearSelection">Clear selection</button>
        <div class="l-flex-row l-flex--justify l-gap-xs">
            <button
                type="button"
                class="button sbx-button--has-icon l-flex__item--1-auto"
                :disabled="isLoading"
                @click="$emit('completePreparation')"
            >
                <sbx-icon name="notifications-hollow"></sbx-icon>
                Complete Preparation
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

@media (max-width: 768px) {
    .flex-container {
        flex-direction: column;
    }

    .l-flex__item--1-auto {
        flex: 1 1 100%;
    }
}
</style>
