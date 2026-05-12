<script setup lang="ts">
/**
 * Renders a student's entry as grouped labels (HL, SL, Core).
 * Highlights any subject with an unknown language using the alert sentiment.
 */
import type { SubjectEntry } from "../../js/types/ibReportTypes";

const props = defineProps<{
    entry: SubjectEntry[];
}>();

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
    <div class="l-flex-col l-gap-s">
        <sbx-label
            v-for="e in props.entry"
            :key="`hl-${e.subject}`"
            :text="labelText(e)"
            :sentiment="isUnknown(e) ? 'alert' : 'neutral'"
        ></sbx-label>
    </div>
</template>

<style scoped></style>
