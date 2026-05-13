<script setup lang="ts">
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
    <div class="ib-action-drawer">
        <span class="ib-action-drawer__count">
            {{ selectedCount }} student{{ selectedCount === 1 ? "" : "s" }} selected
        </span>
        <button type="button" class="button secondary small" @click="$emit('clearSelection')">
            Clear
        </button>
        <button
            type="button"
            class="button small"
            :disabled="isLoading"
            @click="$emit('completePreparation')"
        >
            Complete Preparation
        </button>
        <button
            type="button"
            class="button small ib-action-drawer__submit-btn"
            disabled
            aria-disabled="true"
            title="Coming soon"
        >
            Submit to IB
        </button>
    </div>
</template>

<style scoped>
.ib-action-drawer {
    position: sticky;
    bottom: 0;
    background: var(--sbx-color-surface, #fff);
    border-top: 1px solid var(--sbx-color-border, #ddd);
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.08);
    z-index: 10;
}

.ib-action-drawer__count {
    flex: 1;
}

.ib-action-drawer__submit-btn {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
