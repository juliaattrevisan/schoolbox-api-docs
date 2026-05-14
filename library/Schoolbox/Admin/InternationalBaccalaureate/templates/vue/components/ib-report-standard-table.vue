<script setup lang="ts">
import IbReportSubjectLabels from "./ib-report-subject-labels.vue";
import IbUpdateLanguageModal from "./ib-update-language-modal.vue";
import type { StudentRow, SubjectGroup } from "../../js/types/ibReportTypes";

interface Props {
    students: StudentRow[];
    searchLanguagesEndpoint: string;
}
defineProps<Props>();

const emit = defineEmits<{
    studentUpdated: [studentId: number, updatedSubjects: SubjectGroup];
}>();

const isModalOpen = window.Vue.ref<boolean>(false);
const modalStudent = window.Vue.ref<StudentRow | null>(null);

/**
 * Opens the update language modal for the given student.
 * @param student The student to update.
 */
function openModal(student: StudentRow): void {
    modalStudent.value = student;
    isModalOpen.value = true;
}

/**
 * Forwards the saved event up to the parent report component.
 * @param studentId The id of the updated student.
 * @param updatedSubjects The new subject group with updated language selections.
 */
function onSaved(studentId: number, updatedSubjects: SubjectGroup): void {
    emit("studentUpdated", studentId, updatedSubjects);
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
                <td>
                    <sbx-pop-out :content-id="`ib-row-dropdown-${student.id}`"></sbx-pop-out>
                    <ul
                        :id="`ib-row-dropdown-${student.id}`"
                        class="f-dropdown"
                        data-dropdown-content
                        aria-hidden="true"
                    >
                        <li v-if="student.updateLanguageEndpoint !== null">
                            <a href="#" @click.prevent="openModal(student)">Update Language Selection</a>
                        </li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>
    <ib-update-language-modal
        v-model:is-open="isModalOpen"
        :student="modalStudent"
        :search-languages-endpoint="searchLanguagesEndpoint"
        @saved="onSaved"
    />
</template>
