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

const menuOpenForId = window.Vue.ref<number | null>(null);
const isModalOpen = window.Vue.ref<boolean>(false);
const modalStudent = window.Vue.ref<StudentRow | null>(null);

/**
 * Toggles the actions dropdown for the given student row.
 * @param studentId The id of the student whose menu to toggle.
 */
function toggleMenu(studentId: number): void {
    menuOpenForId.value = menuOpenForId.value === studentId ? null : studentId;
}

/**
 * Opens the update language modal for the given student.
 * @param student The student to update.
 */
function openModal(student: StudentRow): void {
    modalStudent.value = student;
    isModalOpen.value = true;
    menuOpenForId.value = null;
}

/**
 * Forwards the saved event up to the parent report component.
 * @param studentId The id of the updated student.
 * @param updatedSubjects The new subject group with updated language selections.
 */
function onSaved(studentId: number, updatedSubjects: SubjectGroup): void {
    emit("studentUpdated", studentId, updatedSubjects);
}

/** Closes any open row actions dropdown. */
function closeMenu(): void {
    menuOpenForId.value = null;
}

window.Vue.onMounted(() => {
    document.addEventListener("click", closeMenu);
});

window.Vue.onUnmounted(() => {
    document.removeEventListener("click", closeMenu);
});
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
                    <div class="ib-report-row-actions" @click.stop>
                        <button
                            type="button"
                            :aria-expanded="menuOpenForId === student.id"
                            aria-label="More options"
                            @click.stop="toggleMenu(student.id)"
                        >
                            <sbx-icon name="more"></sbx-icon>
                        </button>
                        <div v-if="menuOpenForId === student.id" class="ib-report-row-actions__menu">
                            <button type="button" class="ib-report-row-actions__item" @click="openModal(student)">
                                Update Language Selection
                            </button>
                        </div>
                    </div>
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

<style scoped>
.ib-report-row-actions {
    position: relative;
    display: inline-block;
}

.ib-report-row-actions__menu {
    position: absolute;
    right: 0;
    top: 100%;
    z-index: 10;
    background: var(--sbx-color-background, #fff);
    border: 1px solid var(--sbx-color-border, #ccc);
    border-radius: 4px;
    min-width: 12rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}

.ib-report-row-actions__item {
    display: block;
    width: 100%;
    padding: 0.5rem 1rem;
    text-align: left;
    background: none;
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.ib-report-row-actions__item:hover {
    background: var(--sbx-color-background-hover, #f5f5f5);
}
</style>
