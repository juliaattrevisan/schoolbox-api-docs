<script setup lang="ts">
import IbReportSubjectLabels from "./ib-report-subject-labels.vue";
import { useIbReportUpdateLanguageStore } from "../store/ibReportUpdateLanguageStore";
import type { StudentRow } from "../../js/types/ibReportTypes";

interface Props {
    students: StudentRow[];
}
defineProps<Props>();
const updateLanguageStore = useIbReportUpdateLanguageStore();

/**
 * Opens the update language modal for the given student.
 * @param student The student to update.
 */
function openModal(student: StudentRow): void {
    updateLanguageStore.prompt(student);
}
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
                <th scope="col"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="student in students" :key="student.id">
                <td class="checklist">
                    <input
                        :id="`select-${student.id}`"
                        type="checkbox"
                        disabled
                        :aria-label="`Select ${student.name}`"
                    /><label class="checkbox-label" :for="`select-${student.id}`"></label>
                </td>
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
                <td>
                    <sbx-pop-out
                        :content-id="`ib-row-dropdown-${student.id}`"
                        move-to="#ib-report-pop-out-container"
                    ></sbx-pop-out>
                    <ul
                        :id="`ib-row-dropdown-${student.id}`"
                        class="f-dropdown"
                        data-dropdown-content
                        aria-hidden="true"
                    >
                        <li v-if="student.updateLanguageEndpoint !== null">
                            <button class="sbx-button--has-icon" @click.prevent="openModal(student)">
                                <sbx-icon name="modify"></sbx-icon>
                                Update Language Selection
                            </button>
                        </li>
                        <!-- TODO: At the moment there's no way to distinguish the different states in vue, so needs to add condition later to restrict to unconfirmed state only    -->
                        <li>
                            <button class="sbx-button--has-icon">
                                <sbx-icon name="notifications-hollow"></sbx-icon>
                                Renotify student
                            </button>
                        </li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>
</template>
