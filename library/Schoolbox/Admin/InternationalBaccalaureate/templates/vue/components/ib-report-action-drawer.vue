<script setup lang="ts">
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer.vue";

interface Props {
    selectedCount: number;
    isLoading: boolean;
}
defineProps<Props>();

defineEmits<{
    completePreparation: [];
    clearSelection: [];
}>();
</script>

<template>
    <bottom-drawer :show-drawer="selectedCount > 0">
        <span class="selected-count">
            <em>{{ selectedCount }} student{{ selectedCount === 1 ? "" : "s" }} selected</em>
        </span>
        <button type="button" class="button-clear" @click="$emit('clearSelection')">Clear</button>
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
