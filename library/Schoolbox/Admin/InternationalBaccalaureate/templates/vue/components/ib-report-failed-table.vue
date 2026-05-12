<script setup lang="ts">
/**
 * Student table used in the Failed Registrations section of the registration report.
 * Extends the standard table with an error message column.
 */
import IbReportSubjectLabels from "./ib-report-subject-labels.vue";
import type { FailedRow } from "../../js/types/ibReportTypes";

interface Props {
    students: FailedRow[];
}
defineProps<Props>();
</script>

<template>
    <table class="tiger-stripe">
        <thead>
            <tr>
                <th scope="col"><span class="sr-only">Select</span></th>
                <th scope="col">Name</th>
                <th scope="col">Candidate Details</th>
                <th scope="col">HL</th>
                <th scope="col">SL</th>
                <th scope="col">Core</th>
                <th scope="col">Error Message</th>
                <th scope="col"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="student in students" :key="student.id">
                <td><input type="checkbox" disabled :aria-label="`Select ${student.name}`" /></td>
                <td>{{ student.name }}</td>
                <td>
                    <sbx-icon
                        v-if="student.candidateDetailsStatus === 'confirmed'"
                        name="approve"
                        color="var(--sbx-color-success, #27ae60)"
                        aria-label="Candidate details confirmed"
                    ></sbx-icon>
                    <sbx-icon
                        v-else
                        name="alert"
                        color="var(--sbx-color-warning, #e67e22)"
                        aria-label="Candidate details unconfirmed"
                    ></sbx-icon>
                </td>
                <td><ib-report-subject-labels :entry="student.subjects.hl" /></td>
                <td><ib-report-subject-labels :entry="student.subjects.sl" /></td>
                <td><ib-report-subject-labels :entry="student.subjects.core" /></td>
                <td class="ib-report-error-message">{{ student.errorMessage ?? "—" }}</td>
                <td>
                    <button type="button" disabled aria-label="More options">
                        <sbx-icon name="more"></sbx-icon>
                    </button>
                </td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped>
.ib-report-error-message {
    color: var(--sbx-color-error, #c0392b);
    font-size: 0.875rem;
}
</style>
