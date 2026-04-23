<script setup lang="ts">
import type { SubjectGroup, SubjectEntry } from "../../js/types/ibReportTypes";

interface Props {
    subjects: SubjectGroup;
}
defineProps<Props>();

const UNKNOWN_LANGUAGE = "UNKNOWN LANGUAGE";

/**
 * Returns the display label for a subject chip.
 * @param entry The subject entry.
 * @returns The chip label string.
 */
function chipLabel(entry: SubjectEntry): string {
    const language = entry.language ?? UNKNOWN_LANGUAGE;
    return `${entry.subject}: ${language}`;
}

/**
 * Returns true if the chip should be highlighted as unknown.
 * @param entry The subject entry.
 * @returns Whether the language is unknown.
 */
function isUnknown(entry: SubjectEntry): boolean {
    return !entry.language || entry.language === UNKNOWN_LANGUAGE;
}
</script>

<template>
    <div class="ib-report-chips l-flex l-gap-xs">
        <div class="ib-report-chips__group">
            <span
                v-for="entry in subjects.hl"
                :key="`hl-${entry.subject}`"
                class="ib-report-chips__chip"
                :class="{ 'ib-report-chips__chip--unknown': isUnknown(entry) }"
            >
                {{ chipLabel(entry) }}
            </span>
        </div>
        <div class="ib-report-chips__group">
            <span
                v-for="entry in subjects.sl"
                :key="`sl-${entry.subject}`"
                class="ib-report-chips__chip"
                :class="{ 'ib-report-chips__chip--unknown': isUnknown(entry) }"
            >
                {{ chipLabel(entry) }}
            </span>
        </div>
        <div class="ib-report-chips__group">
            <span
                v-for="entry in subjects.core"
                :key="`core-${entry.subject}`"
                class="ib-report-chips__chip"
                :class="{ 'ib-report-chips__chip--unknown': isUnknown(entry) }"
            >
                {{ chipLabel(entry) }}
            </span>
        </div>
    </div>
</template>

<style scoped>
.ib-report-chips {
    align-items: flex-start;
}

.ib-report-chips__group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 8rem;
}

.ib-report-chips__chip {
    background-color: var(--sbx-color-foreground-secondary, #f0f0f0);
    border-radius: 4px;
    font-size: 0.75rem;
    padding: 0.15rem 0.4rem;
    white-space: nowrap;
}

.ib-report-chips__chip--unknown {
    background-color: var(--sbx-color-error-bg, #fde8e8);
    color: var(--sbx-color-error, #c0392b);
}
</style>
