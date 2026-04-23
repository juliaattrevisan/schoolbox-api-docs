<script setup lang="ts">
import IbReportSubjectChips from "./ib-report-subject-chips.vue";
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
                <th></th>
                <th>Name</th>
                <th>Candidate Details</th>
                <th>HL</th>
                <th>SL</th>
                <th>Core</th>
                <th>Error Message</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="student in students"
                :key="student.id"
            >
                <td><input type="checkbox" disabled aria-label="Select student" /></td>
                <td>{{ student.name }}</td>
                <td>
                    <sbx-icon
                        v-if="student.candidateDetailsStatus === 'confirmed'"
                        name="approve"
                        class="ib-report-icon--confirmed"
                        aria-label="Candidate details confirmed"
                    ></sbx-icon>
                    <sbx-icon
                        v-else
                        name="alert"
                        class="ib-report-icon--unconfirmed"
                        aria-label="Candidate details unconfirmed"
                    ></sbx-icon>
                </td>
                <td colspan="3"><ib-report-subject-chips :subjects="student.subjects" /></td>
                <td class="ib-report-error-message">{{ student.errorMessage }}</td>
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
.ib-report-icon--confirmed {
    color: var(--sbx-color-success, #27ae60);
}

.ib-report-icon--unconfirmed {
    color: var(--sbx-color-warning, #e67e22);
}

.ib-report-error-message {
    color: var(--sbx-color-error, #c0392b);
    font-size: 0.875rem;
}
</style>
