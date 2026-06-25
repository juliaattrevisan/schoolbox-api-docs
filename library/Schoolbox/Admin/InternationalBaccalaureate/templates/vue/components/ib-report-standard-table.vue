<script setup lang="ts">
import { useSelectedList } from "@schoolboxVue/composables/form/useSelectedList";
import IbReportSubjectLabels from "./ib-report-subject-labels.vue";
import { useIbReportUpdateLanguageStore } from "../store/ibReportUpdateLanguageStore";
import type { StudentRow } from "../../js/types/ibReportTypes";

interface Props {
    students: StudentRow[];
    searchLanguagesEndpoint: string;
    section: StudentRow["stage"];
    activeSelectionSection: StudentRow["stage"] | null;
}
const props = defineProps<Props>();

const selectedListModel = defineModel<StudentRow[]>("selectedList", { default: () => [] });
const students = window.Vue.toRef(props, "students");
const { selectedList, allSelected, isIndeterminate, showCheckboxes } = useSelectedList(students, selectedListModel);

const updateLanguageStore = useIbReportUpdateLanguageStore();

const emit = defineEmits<{
    completePreparationSingle: [studentId: number];
    renotifySingle: [userId: number];
    registerViaApiSingle: [studentId: number];
    resetDetailsSingle: [studentId: number];
    manuallyRegisterSingle: [studentId: number];
    approveOnBehalfSingle: [studentId: number];
}>();

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
                <th scope="col" class="checklist">
                    <span
                        v-if="
                            showCheckboxes &&
                            (props.activeSelectionSection === null || props.activeSelectionSection === props.section)
                        "
                    >
                        <input
                            :id="`select-all-${props.section}`"
                            v-model="allSelected"
                            type="checkbox"
                            :class="{ indeterminate: isIndeterminate }"
                            aria-label="Select all"
                        /><label class="checkbox-label" :for="`select-all-${props.section}`"></label>
                    </span>
                </th>
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
                    <template
                        v-if="props.activeSelectionSection === null || props.activeSelectionSection === props.section"
                    >
                        <input
                            :id="`select-${student.id}`"
                            v-model="selectedList"
                            type="checkbox"
                            :value="student"
                            :disabled="student.stage !== props.section"
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
                                Update language selection
                            </button>
                        </li>
                        <li v-if="student.stage === 'confirmed'">
                            <button
                                class="sbx-button--has-icon"
                                @click.prevent="emit('registerViaApiSingle', student.id)"
                            >
                                <sbx-icon name="export"></sbx-icon>
                                Register via API
                            </button>
                        </li>
                        <li v-if="student.renotifyEndpoint !== null">
                            <button
                                class="sbx-button--has-icon"
                                @click.prevent="emit('renotifySingle', student.userId)"
                            >
                                <sbx-icon name="notifications-hollow"></sbx-icon>
                                Renotify student
                            </button>
                        </li>
                        <li v-if="student.stage === 'preparation'">
                            <button
                                class="sbx-button--has-icon"
                                @click.prevent="emit('completePreparationSingle', student.id)"
                            >
                                <sbx-icon name="notifications-hollow"></sbx-icon>
                                Complete preparation
                            </button>
                        </li>
                        <li v-if="student.stage === 'unconfirmed'">
                            <button
                                class="sbx-button--has-icon"
                                @click.prevent="emit('approveOnBehalfSingle', student.id)"
                            >
                                <sbx-icon name="approve"></sbx-icon>
                                Approve on behalf of student
                            </button>
                        </li>
                        <li v-if="student.stage === 'confirmed'">
                            <button
                                class="sbx-button--has-icon"
                                @click.prevent="emit('manuallyRegisterSingle', student.id)"
                            >
                                <sbx-icon name="international-baccalaureate"></sbx-icon>
                                Mark as manually registered
                            </button>
                        </li>
                        <li v-if="student.stage === 'unconfirmed' || student.stage === 'confirmed'">
                            <button
                                class="sbx-button--has-icon"
                                @click.prevent="emit('resetDetailsSingle', student.id)"
                            >
                                <sbx-icon name="undo"></sbx-icon>
                                Reset all details
                            </button>
                        </li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>
</template>
