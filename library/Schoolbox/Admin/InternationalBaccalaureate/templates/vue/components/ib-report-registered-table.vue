<script setup lang="ts">
/**
 * Student table used in the Registered section of the registration report.
 * Includes the student's IB candidate number.
 */
import IbReportSubjectLabels from "./ib-report-subject-labels.vue";
import type { RegisteredRow } from "../../js/types/ibReportTypes";

interface Props {
    students: RegisteredRow[];
}
defineProps<Props>();
</script>

<template>
    <table class="tiger-stripe">
        <thead>
            <tr>
                <th>Name</th>
                <th>Candidate Details</th>
                <th>HL</th>
                <th>SL</th>
                <th>Core</th>
                <th>IB Candidate Number</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="student in students" :key="student.id">
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
                <td>{{ student.ibCandidateNumber ?? "—" }}</td>
            </tr>
        </tbody>
    </table>
</template>
