<script setup lang="ts">
/**
 * Renders a student's subjects as grouped labels (HL, SL, Core).
 * Highlights any subject with an unknown language using the alert sentiment.
 */
import type { SubjectGroup, SubjectEntry } from "../../js/types/ibReportTypes";

interface Props {
    subjects: SubjectGroup;
}
defineProps<Props>();

const UNKNOWN_LANGUAGE = "UNKNOWN LANGUAGE";

/**
 * Returns the display text for a subject label.
 * @param entry The subject entry.
 * @returns The label text string.
 */
function labelText(entry: SubjectEntry): string {
    const language = entry.language ?? UNKNOWN_LANGUAGE;
    return `${entry.subject}: ${language}`;
}

/**
 * Returns true if the label should use the alert sentiment.
 * @param entry The subject entry.
 * @returns Whether the language is unknown.
 */
function isUnknown(entry: SubjectEntry): boolean {
    return !entry.language || entry.language === UNKNOWN_LANGUAGE;
}
</script>

<template>
    <div class="ib-report-labels l-flex l-gap-xs">
        <div class="ib-report-labels__group">
            <sbx-label
                v-for="entry in subjects.hl"
                :key="`hl-${entry.subject}`"
                :text="labelText(entry)"
                :sentiment="isUnknown(entry) ? 'alert' : 'neutral'"
            ></sbx-label>
        </div>
        <div class="ib-report-labels__group">
            <sbx-label
                v-for="entry in subjects.sl"
                :key="`sl-${entry.subject}`"
                :text="labelText(entry)"
                :sentiment="isUnknown(entry) ? 'alert' : 'neutral'"
            ></sbx-label>
        </div>
        <div class="ib-report-labels__group">
            <sbx-label
                v-for="entry in subjects.core"
                :key="`core-${entry.subject}`"
                :text="labelText(entry)"
                :sentiment="isUnknown(entry) ? 'alert' : 'neutral'"
            ></sbx-label>
        </div>
    </div>
</template>

<style scoped>
.ib-report-labels {
    align-items: flex-start;
}

.ib-report-labels__group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 8rem;
}
</style>
