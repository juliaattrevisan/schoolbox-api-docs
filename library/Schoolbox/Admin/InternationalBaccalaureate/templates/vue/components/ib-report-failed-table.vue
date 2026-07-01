<script setup lang="ts">
import { useSelectedList } from "@schoolboxVue/composables/form/useSelectedList";
import IbReportSubjectLabels from "./ib-report-subject-labels.vue";
import { useIbReportUpdateLanguageStore } from "../store/ibReportUpdateLanguageStore";
import type { FailedRow, StudentRow } from "../../js/types/ibReportTypes";

interface Props {
    students: FailedRow[];
    activeSelectionSection: StudentRow["stage"] | null;
}
const props = defineProps<Props>();

const selectedListModel = defineModel<FailedRow[]>("selectedList", { default: () => [] });
const students = window.Vue.toRef(props, "students");
const { selectedList, allSelected, isIndeterminate, showCheckboxes } = useSelectedList(students, selectedListModel);

const emit = defineEmits<{
    resetDetailsSingle: [studentId: number];
    manuallyRegisterSingle: [studentId: number];
}>();

const updateLanguageStore = useIbReportUpdateLanguageStore();

/**
 * Opens the update language modal for the given student.
 * @param student The student to update.
 */
function openModal(student: FailedRow): void {
    updateLanguageStore.prompt(student);
}
</script>

<template>
    <table class="tiger-stripe">
        <thead>
            <tr>
                <th scope="col" class="checklist">
                    <span
                        v-if="
                            showCheckboxes &&
                            (props.activeSelectionSection === null || props.activeSelectionSection === 'failed')
                        "
                    >
                        <input
                            id="select-all-failed"
                            v-model="allSelected"
                            type="checkbox"
                            :class="{ indeterminate: isIndeterminate }"
                            aria-label="Select all"
                        /><label class="checkbox-label" for="select-all-failed"></label>
                    </span>
                </th>
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
                <td class="checklist">
                    <template v-if="props.activeSelectionSection === null || props.activeSelectionSection === 'failed'">
                        <input
                            :id="`select-${student.id}`"
                            v-model="selectedList"
                            type="checkbox"
                            :value="student"
                            :aria-label="`Select ${student.name}`"
                        /><label class="checkbox-label" :for="`select-${student.id}`"></label>
                    </template>
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
                        title="Missing required candidate details."
                    ></sbx-icon>
                </td>
                <td><ib-report-subject-labels :entry="student.subjects.hl" /></td>
                <td><ib-report-subject-labels :entry="student.subjects.sl" /></td>
                <td><ib-report-subject-labels :entry="student.subjects.core" /></td>
                <td class="ib-report-error-message">{{ student.errorMessage ?? "—" }}</td>
                <td>
                    <sbx-pop-out
                        :content-id="`ib-failed-row-dropdown-${student.id}`"
                        move-to="#ib-report-pop-out-container"
                    ></sbx-pop-out>
                    <ul
                        :id="`ib-failed-row-dropdown-${student.id}`"
                        class="f-dropdown"
                        data-dropdown-content
                        aria-hidden="true"
                    >
                        <li v-if="student.updateLanguageEndpoint !== null">
                            <button class="sbx-button--has-icon" @click.prevent="openModal(student)">
                                <sbx-icon name="modify"></sbx-icon>
                                Update language selection
                            </button>
                        </li>
                        <li>
                            <button
                                class="sbx-button--has-icon"
                                @click.prevent="emit('resetDetailsSingle', student.id)"
                            >
                                <sbx-icon name="undo"></sbx-icon>
                                Reset all details
                            </button>
                        </li>
                        <li>
                            <button
                                class="sbx-button--has-icon"
                                @click.prevent="emit('manuallyRegisterSingle', student.id)"
                            >
                                <sbx-icon name="international-baccalaureate"></sbx-icon>
                                Mark as manually registered
                            </button>
                        </li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped>
.ib-report-error-message {
    color: var(--sbx-color-error, #c0392b);
    font-size: var(--sbx-font-size-xs, 0.875rem);
}
</style>
