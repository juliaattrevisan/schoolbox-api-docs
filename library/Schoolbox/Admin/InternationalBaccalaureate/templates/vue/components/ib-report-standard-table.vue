<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import IbReportSubjectLabels from "./ib-report-subject-labels.vue";
import { useIbReportUpdateLanguageStore } from "../store/ibReportUpdateLanguageStore";
import type { StudentRow } from "../../js/types/ibReportTypes";

interface Props {
    students: StudentRow[];
}
defineProps<Props>();
const updateLanguageStore = useIbReportUpdateLanguageStore();
const renotifyingStudentIds = window.Vue.ref<Set<number>>(new Set());

/**
 * Opens the update language modal for the given student.
 * @param student The student to update.
 */
function openModal(student: StudentRow): void {
    updateLanguageStore.prompt(student);
}

/**
 * Re-sends the unconfirmed details notification to the given student.
 * @param student The student to renotify.
 */
async function renotifyStudent(student: StudentRow): Promise<void> {
    if (student.renotifyEndpoint === null || renotifyingStudentIds.value.has(student.userId)) {
        return;
    }

    renotifyingStudentIds.value = new Set([...renotifyingStudentIds.value, student.userId]);
    const toast = window.sbProps.toastActivate("Renotifying student...");

    try {
        const response = await apiRequest<unknown>({
            url: student.renotifyEndpoint,
            method: "POST",
            body: {
                studentIds: [student.userId],
            },
        });

        if (!response.success) {
            toast.timeout({ text: response.message ?? "Failed to renotify student.", isSuccess: false });
            return;
        }

        toast.timeout({ text: "Student renotified." });
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : "Failed to renotify student.";
        toast.timeout({ text: errorMessage, isSuccess: false });
    } finally {
        const nextStudentIds = new Set(renotifyingStudentIds.value);
        nextStudentIds.delete(student.userId);
        renotifyingStudentIds.value = nextStudentIds;
    }
}

/**
 * Whether the student currently has an in-flight renotify request.
 * @param student The student row.
 * @returns True if the student is currently renotifying, false otherwise.
 */
function isRenotifying(student: StudentRow): boolean {
    return renotifyingStudentIds.value.has(student.userId);
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
                        <li v-if="student.renotifyEndpoint !== null">
                            <button
                                class="sbx-button--has-icon"
                                :disabled="isRenotifying(student)"
                                @click.prevent="renotifyStudent(student)"
                            >
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
