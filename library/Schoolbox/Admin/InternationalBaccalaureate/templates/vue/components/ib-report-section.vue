<script setup lang="ts">
/**
 * Collapsible section wrapper used across the registration report.
 * Displays a title, student count badge, and a chevron toggle.
 */
interface Props {
    title: string;
    count: number;
}
defineProps<Props>();

const isOpen = window.Vue.ref<boolean>(false);

/** Toggles the section open or closed. */
function toggle(): void {
    isOpen.value = !isOpen.value;
}
</script>

<template>
    <div class="ib-report-section c-island">
        <button
            type="button"
            class="ib-report-section__header l-flex l-flex--align-center l-flex--justify-space f-no-margins"
            :aria-expanded="isOpen"
            @click="toggle"
        >
            <span class="l-flex l-flex--align-center l-gap-xs f-no-margins">
                <span>{{ title }}</span>
                <span class="ib-report-section__count">{{ count }}</span>
            </span>
            <sbx-icon :name="isOpen ? 'up-arrow' : 'down-arrow'"></sbx-icon>
        </button>

        <div v-show="isOpen" class="ib-report-section__body">
            <p v-if="count === 0" class="ib-report-section__empty">No students in this section.</p>
            <slot v-else />
        </div>
    </div>
</template>

<style scoped>
.ib-report-section {
    border-radius: 4px;
    margin-bottom: 0.4rem;
}

.ib-report-section__header {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.875rem 1.25rem;
    width: 100%;
    text-align: left;
    font-size: 1rem;
}

.ib-report-section__count {
    background-color: var(--sbx-color-badge, #e0e0e0);
    border-radius: 4px;
    font-size: 0.8rem;
    padding: 0.1rem 0.5rem;
}

.ib-report-section__body {
    padding: 0 1.25rem 1.25rem;
}

.ib-report-section__empty {
    color: var(--sbx-color-text-secondary, #666);
    margin: 0.5rem 0;
}
</style>
