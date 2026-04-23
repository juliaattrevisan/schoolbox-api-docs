<script setup lang="ts">
import IbReportSubjectChips from "./ib-report-subject-chips.vue";
import type { InProgressRow } from "../../js/types/ibReportTypes";

interface Props {
    students: InProgressRow[];
}
defineProps<Props>();

/**
 * Formats an ISO 8601 timestamp as "DD/MM/YYYY H:MMam/pm".
 * @param iso The ISO 8601 timestamp string.
 * @returns The formatted date/time string.
 */
function formatSubmittedAt(iso: string): string {
    const date = new Date(iso);
    const day = String(date.getDate()).padStart(2, "0");
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const year = date.getFullYear();
    const hours = date.getHours();
    const minutes = String(date.getMinutes()).padStart(2, "0");
    const ampm = hours >= 12 ? "pm" : "am";
    const displayHours = hours % 12 === 0 ? 12 : hours % 12;
    return `${day}/${month}/${year} ${displayHours}:${minutes}${ampm}`;
}
</script>

<template>
    <table class="tiger-stripe">
        <thead>
            <tr>
                <th>Name</th>
                <th>Attempted IBIS Submission at</th>
                <th>HL</th>
                <th>SL</th>
                <th>Core</th>
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="student in students"
                :key="student.id"
            >
                <td>{{ student.name }}</td>
                <td>{{ formatSubmittedAt(student.ibisSubmittedAt) }}</td>
                <td colspan="3"><ib-report-subject-chips :subjects="student.subjects" /></td>
            </tr>
        </tbody>
    </table>
</template>
