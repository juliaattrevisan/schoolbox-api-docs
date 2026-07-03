<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import { useSelectedList } from "@schoolboxVue/composables/form/useSelectedList";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import IbReportSection from "./components/ib-report-section.vue";
import IbReportStandardTable from "./components/ib-report-standard-table.vue";
import IbReportFailedTable from "./components/ib-report-failed-table.vue";
import IbReportInProgressTable from "./components/ib-report-in-progress-table.vue";
import IbReportRegisteredTable from "./components/ib-report-registered-table.vue";
import IbUpdateLanguageModal from "./components/ib-update-language-modal.vue";
import IbReportActionDrawer from "./components/ib-report-action-drawer.vue";
import IbStudentConfirmationModal from "./components/ib-student-confirmation-modal.vue";
import { useIbReportUpdateLanguageStore } from "./store/ibReportUpdateLanguageStore";
import type {
    StudentRow,
    FailedRow,
    InProgressRow,
    RegisteredRow,
    SubjectEntry,
    ApiStudent,
    ApiSubject,
    ApiCandidatesResponse,
    ApiManuallyRegisterResponse,
    ApiUpdateConfirmationResponse,
    ApiRenotifyResponse,
} from "../js/types/ibReportTypes";

interface Props {
    candidatesEndpoint: string;
    candidateExportEndpoint: string;
    candidateNumberUpdateRoute: string;
    searchLanguagesEndpoint: string;
    renotifyEndpoint: string;
    completePreparationEndpoint: string;
    registerViaApiEndpoint: string;
    resetDetailsEndpoint: string;
    manuallyRegisterEndpoint: string;
    sessionTitle: string;
}
const props = defineProps<Props>();
const updateLanguageStore = useIbReportUpdateLanguageStore();
const renotifyEndpoint = props.renotifyEndpoint.trim() === "" ? null : props.renotifyEndpoint;

const isLoading = window.Vue.ref<boolean>(true);
const loadError = window.Vue.ref<string | null>(null);
const preparation = window.Vue.ref<StudentRow[]>([]);
const unconfirmed = window.Vue.ref<StudentRow[]>([]);
const confirmed = window.Vue.ref<StudentRow[]>([]);
const inProgress = window.Vue.ref<InProgressRow[]>([]);
const failed = window.Vue.ref<FailedRow[]>([]);
const registered = window.Vue.ref<RegisteredRow[]>([]);

const { selectedList: preparationSelected } = useSelectedList(preparation);
const { selectedList: unconfirmedSelected } = useSelectedList(unconfirmed);
const { selectedList: confirmedSelected } = useSelectedList(confirmed);
const { selectedList: failedSelected } = useSelectedList(failed);

const activeSelectionSection = window.Vue.computed<StudentRow["stage"] | null>(() => {
    if (preparationSelected.value.length > 0) return "preparation";
    if (unconfirmedSelected.value.length > 0) return "unconfirmed";
    if (confirmedSelected.value.length > 0) return "confirmed";
    if (failedSelected.value.length > 0) return "failed";
    return null;
});

// Enforce mutual exclusivity: selecting from one section clears all others
window.Vue.watch(preparationSelected, (newVal) => {
    if (newVal.length > 0) {
        unconfirmedSelected.value = [];
        confirmedSelected.value = [];
        failedSelected.value = [];
    }
});
window.Vue.watch(unconfirmedSelected, (newVal) => {
    if (newVal.length > 0) {
        preparationSelected.value = [];
        confirmedSelected.value = [];
        failedSelected.value = [];
    }
});
window.Vue.watch(confirmedSelected, (newVal) => {
    if (newVal.length > 0) {
        preparationSelected.value = [];
        unconfirmedSelected.value = [];
        failedSelected.value = [];
    }
});
window.Vue.watch(failedSelected, (newVal) => {
    if (newVal.length > 0) {
        preparationSelected.value = [];
        unconfirmedSelected.value = [];
        confirmedSelected.value = [];
    }
});

const isCompleting = window.Vue.ref<boolean>(false);
const isRegistering = window.Vue.ref<boolean>(false);
const isResetting = window.Vue.ref<boolean>(false);
const isManuallyRegistering = window.Vue.ref<boolean>(false);
const isApprovingOnBehalf = window.Vue.ref<boolean>(false);
const isRenotifying = window.Vue.ref<boolean>(false);
const registerViaApiModalOpen = window.Vue.ref<boolean>(false);
const resetModalOpen = window.Vue.ref<boolean>(false);
const studentsToRegister = window.Vue.ref<Pick<StudentRow, "id" | "userId" | "name" | "subjects">[]>([]);
const studentsToReset = window.Vue.ref<Pick<StudentRow, "id" | "userId" | "name">[]>([]);

/**
 * Maps a raw API subject entry to the frontend SubjectEntry shape.
 * Takes the first language when multiple are configured.
 * @param apiSubject The raw subject from the API.
 * @returns A SubjectEntry for use in the frontend.
 */
function mapSubjectEntry(apiSubject: ApiSubject): SubjectEntry {
    const lang = apiSubject.languages[0] ?? null;
    return {
        groupSubjectId: apiSubject.id,
        subject: apiSubject.subject,
        language: lang?.languageName ?? null,
        languageId: lang?.languageId ?? null,
    };
}

/**
 * Maps a raw API student entry to the frontend StudentRow shape.
 * @param s The raw student from the API.
 * @param stage The pipeline stage this student belongs to.
 * @param studentRenotifyEndpoint The endpoint to use for renotifying this student.
 * @returns A StudentRow for use in the frontend.
 */
function mapStudentRow(
    s: ApiStudent,
    stage: StudentRow["stage"],
    studentRenotifyEndpoint: string | null = null,
): StudentRow {
    return {
        id: s.registrationSessionStudentId ?? s.userId,
        name: s.userName,
        userId: s.userId,
        ibCandidateNumber: s.ibCandidateNumber,
        candidateDetailsStatus: s.hasMissingDetails ? "unconfirmed" : "confirmed",
        missingFields: s.missingFields,
        subjects: {
            hl: s.ibGroupSubjects.HL.map(mapSubjectEntry),
            sl: s.ibGroupSubjects.SL.map(mapSubjectEntry),
            core: s.ibGroupSubjects.Core.map(mapSubjectEntry),
        },
        updateLanguageEndpoint: s._routes.updateLanguages,
        updateConfirmationEndpoint: s._routes.updateConfirmation,
        renotifyEndpoint: studentRenotifyEndpoint,
        stage,
    };
}

/**
 * Fetches all candidate data from the API and populates section arrays.
 */
async function loadCandidates(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
        const response = await apiRequest<ApiCandidatesResponse>({
            url: props.candidatesEndpoint,
            method: "GET",
        });

        if (!response.success) {
            loadError.value = response.message ?? "Failed to load registration report.";
            return;
        }

        const { students, statuses } = response.data;
        const byKey: Record<string, ApiStudent[]> = {};
        for (const status of statuses) {
            byKey[status.key] = students[String(status.value)] ?? [];
        }

        preparation.value = (byKey["preparation"] ?? []).map((s) => mapStudentRow(s, "preparation"));
        unconfirmed.value = (byKey["unconfirmed"] ?? []).map((s) => mapStudentRow(s, "unconfirmed", renotifyEndpoint));
        confirmed.value = (byKey["confirmed"] ?? []).map((s) => mapStudentRow(s, "confirmed"));
        inProgress.value = (byKey["inProgress"] ?? []).map((s) => mapStudentRow(s, "inProgress"));
        failed.value = (byKey["failed"] ?? []).map(
            (s): FailedRow => ({
                ...mapStudentRow(s, "failed"),
                errorMessage: s.errorMessage,
            }),
        );
        registered.value = (byKey["registered"] ?? []).map(
            (s): RegisteredRow => ({
                ...mapStudentRow(s, "registered"),
                manuallyRegisteredBy: s.manuallyRegisteredBy,
                manuallyRegisteredAt: s.manuallyRegisteredAt,
            }),
        );
    } catch (e) {
        console.error("Failed to load candidates", e);
        loadError.value = "Failed to load registration report.";
    } finally {
        isLoading.value = false;
    }
}

updateLanguageStore.init(props.searchLanguagesEndpoint, preparation, unconfirmed, confirmed, failed);

/**
 * Clears the current student selection across all sections.
 */
function clearSelection(): void {
    preparationSelected.value = [];
    unconfirmedSelected.value = [];
    confirmedSelected.value = [];
    failedSelected.value = [];
}

/**
 * Submits the given students to the complete-preparation endpoint,
 * moves them from Preparation to Unconfirmed on success, and shows a toast notification.
 * @param students The students to mark as preparation-complete.
 */
async function handleCompletePreparation(students: StudentRow[]): Promise<void> {
    if (students.length === 0) return;
    if (isCompleting.value) return;
    isCompleting.value = true;

    const userIds = Array.from(new Set(students.map((s) => s.userId)));
    const toast = window.sbProps.toastActivate("Completing preparation...");

    try {
        const response = await apiRequest<{ success: boolean; message?: string }, { userIds: number[] }>({
            url: props.completePreparationEndpoint,
            method: "POST",
            body: { userIds },
        });

        if (!response.success) {
            toast.timeout({ text: response.message ?? "Failed to complete preparation.", isSuccess: false });
            return;
        }

        const removedUserIds = new Set(userIds);
        const completedStudents = preparation.value
            .filter((s) => removedUserIds.has(s.userId))
            .map((s) => ({ ...s, stage: "unconfirmed" as const, renotifyEndpoint }));
        preparation.value = preparation.value.filter((s) => !removedUserIds.has(s.userId));
        unconfirmed.value = [...completedStudents, ...unconfirmed.value];
        clearSelection();
        toast.timeout({
            text: `Preparation completed for ${userIds.length} student${userIds.length === 1 ? "" : "s"}.`,
        });
        // Reload to get the real registrationSessionStudentId for students that didn't have a
        // persisted row before completePreparation. Without this, subsequent actions (e.g. manual
        // register) send the stale userId instead of the session-student ID and the backend rejects them.
        void loadCandidates();
    } catch (e) {
        console.error("Failed to complete preparation", e);
        toast.timeout({ text: "Failed to complete preparation.", isSuccess: false });
    } finally {
        isCompleting.value = false;
    }
}

/**
 * Opens the register-via-API confirmation modal for the given student IDs.
 * @param studentIds The ids to queue for API registration.
 */
function promptRegisterViaApi(studentIds: number[]): void {
    studentsToRegister.value = [...confirmed.value, ...failed.value]
        .filter((s) => studentIds.includes(s.id))
        .map((s) => ({ id: s.id, userId: s.userId, name: s.name, subjects: s.subjects }));
    if (studentsToRegister.value.length === 0) return;
    registerViaApiModalOpen.value = true;
}

/**
 * Queues the selected confirmed students for API registration and moves them into the in-progress section.
 */
async function handleRegisterViaApi(): Promise<void> {
    if (studentsToRegister.value.length === 0) return;
    if (isRegistering.value) return;
    isRegistering.value = true;
    registerViaApiModalOpen.value = false;

    const studentIds = studentsToRegister.value.map((s) => s.id);
    const toast = window.sbProps.toastActivate("Queueing API registration...");

    try {
        const response = await apiRequest<{ success: boolean; message?: string }, { studentIds: number[] }>({
            url: props.registerViaApiEndpoint,
            method: "POST",
            body: { studentIds },
        });

        if (!response.success) {
            toast.timeout({ text: response.message ?? "Failed to queue API registration.", isSuccess: false });
            return;
        }

        const removedIds = new Set(studentIds);
        const queuedStudents = studentsToRegister.value.map(({ id, name, subjects }) => ({
            id,
            name,
            subjects,
        }));
        confirmed.value = confirmed.value.filter((s) => !removedIds.has(s.id));
        failed.value = failed.value.filter((s) => !removedIds.has(s.id));
        inProgress.value = [...queuedStudents, ...inProgress.value];
        clearSelection();
        toast.timeout({
            text: `Queued ${studentIds.length} student${studentIds.length === 1 ? "" : "s"} for API registration.`,
        });
    } catch (e) {
        console.error("Failed to queue API registration", e);
        toast.timeout({ text: "Failed to queue API registration.", isSuccess: false });
    } finally {
        isRegistering.value = false;
        studentsToRegister.value = [];
    }
}

/**
 * Opens the reset-details confirmation modal for the given student IDs.
 * @param studentIds The ids to reset.
 */
function promptResetDetails(studentIds: number[]): void {
    const allStudents = [...unconfirmed.value, ...confirmed.value, ...failed.value];
    studentsToReset.value = allStudents
        .filter((s) => studentIds.includes(s.id))
        .map((s) => ({ id: s.id, userId: s.userId, name: s.name }));
    if (studentsToReset.value.length === 0) return;
    resetModalOpen.value = true;
}

/**
 * Submits the reset-details request for the students in `studentsToReset`,
 * removes them from their section arrays on success, reloads all candidates,
 * and shows a toast notification.
 */
async function handleResetDetails(): Promise<void> {
    if (studentsToReset.value.length === 0) return;
    if (isResetting.value) return;
    isResetting.value = true;
    resetModalOpen.value = false;

    const studentIds = studentsToReset.value.map((s) => s.id);
    const toast = window.sbProps.toastActivate("Resetting details...");

    try {
        const response = await apiRequest<{ success: boolean; message?: string }, { studentIds: number[] }>({
            url: props.resetDetailsEndpoint,
            method: "POST",
            body: { studentIds },
        });

        if (!response.success) {
            toast.timeout({ text: response.message ?? "Failed to reset details.", isSuccess: false });
            return;
        }

        const removedIds = new Set(studentIds);
        unconfirmed.value = unconfirmed.value.filter((s) => !removedIds.has(s.id));
        confirmed.value = confirmed.value.filter((s) => !removedIds.has(s.id));
        failed.value = failed.value.filter((s) => !removedIds.has(s.id));
        clearSelection();
        void loadCandidates();
        toast.timeout({
            text: `Details reset for ${studentIds.length} student${studentIds.length === 1 ? "" : "s"}.`,
        });
    } catch (e) {
        console.error("Failed to reset details", e);
        toast.timeout({ text: "Failed to reset details.", isSuccess: false });
    } finally {
        isResetting.value = false;
        studentsToReset.value = [];
    }
}

/**
 * POSTs the given student IDs to the mark-as-manually-registered endpoint.
 * On success, moves matched students from confirmed/failed into registered.
 * @param studentIds The registration session student IDs to register.
 */
async function handleManuallyRegister(studentIds: number[]): Promise<void> {
    if (isManuallyRegistering.value) return;
    isManuallyRegistering.value = true;

    const toast = window.sbProps.toastActivate("Marking as manually registered...");

    try {
        const response = await apiRequest<ApiManuallyRegisterResponse, { studentIds: number[] }>({
            url: props.manuallyRegisterEndpoint,
            method: "POST",
            body: { studentIds },
        });

        if (!response.success) {
            toast.timeout({
                text: response.message ?? "Failed to mark as manually registered.",
                isSuccess: false,
            });
            return;
        }

        const removedIds = new Set(studentIds);
        const manuallyRegisteredBy = response.data?.manuallyRegisteredBy ?? null;
        const manuallyRegisteredAt = response.data?.manuallyRegisteredAt ?? null;
        const newRegistered = [
            ...confirmed.value
                .filter((s) => removedIds.has(s.id))
                .map((s) => ({ ...s, stage: "registered" as const, manuallyRegisteredBy, manuallyRegisteredAt })),
            ...failed.value
                .filter((s) => removedIds.has(s.id))
                .map((s) => ({ ...s, stage: "registered" as const, manuallyRegisteredBy, manuallyRegisteredAt })),
        ];
        confirmed.value = confirmed.value.filter((s) => !removedIds.has(s.id));
        failed.value = failed.value.filter((s) => !removedIds.has(s.id));
        registered.value = [...newRegistered, ...registered.value];
        clearSelection();
        toast.timeout({
            text: `${studentIds.length} student${studentIds.length === 1 ? "" : "s"} marked as manually registered.`,
        });
    } catch (e) {
        console.error("Failed to mark as manually registered", e);
        toast.timeout({ text: "Failed to mark as manually registered.", isSuccess: false });
    } finally {
        isManuallyRegistering.value = false;
    }
}

/**
 * Confirms both candidate and subject details on behalf of the given students,
 * then moves them from the Unconfirmed section to Confirmed.
 * @param students The unconfirmed students to approve on behalf of.
 */
async function handleApproveOnBehalf(students: StudentRow[]): Promise<void> {
    if (isApprovingOnBehalf.value) return;
    isApprovingOnBehalf.value = true;

    const toast = window.sbProps.toastActivate("Approving on behalf of student(s)...");

    try {
        const results = await Promise.allSettled(
            students.map(async (student) => {
                const endpoint = student.updateConfirmationEndpoint;
                if (!endpoint) throw new Error(`No confirmation endpoint for student ${student.id}`);

                const candidateResponse = await apiRequest<
                    ApiUpdateConfirmationResponse,
                    { section: string; confirmed: boolean }
                >({
                    url: endpoint,
                    method: "PUT",
                    body: { section: "candidate", confirmed: true },
                });
                if (!candidateResponse.success) {
                    throw new Error(candidateResponse.message ?? "Failed to confirm candidate details.");
                }

                const subjectResponse = await apiRequest<
                    ApiUpdateConfirmationResponse,
                    { section: string; confirmed: boolean }
                >({
                    url: endpoint,
                    method: "PUT",
                    body: { section: "subject", confirmed: true },
                });
                if (!subjectResponse.success) {
                    throw new Error(subjectResponse.message ?? "Failed to confirm subject details.");
                }

                return student;
            }),
        );

        const succeededStudents = students.filter((_, i) => results[i].status === "fulfilled");
        const failedCount = students.length - succeededStudents.length;

        if (succeededStudents.length === 0) {
            toast.timeout({ text: "Failed to approve on behalf of student.", isSuccess: false });
            return;
        }

        const succeededIds = new Set(succeededStudents.map((s) => s.id));

        unconfirmed.value = unconfirmed.value.filter((s) => !succeededIds.has(s.id));
        confirmed.value = [
            ...succeededStudents.map((s) => ({ ...s, stage: "confirmed" as const, renotifyEndpoint: null })),
            ...confirmed.value,
        ];

        clearSelection();

        const successText = `${succeededStudents.length} student${succeededStudents.length === 1 ? "" : "s"} approved on behalf.`;
        toast.timeout({
            text: failedCount > 0 ? `${successText} ${failedCount} failed.` : successText,
            isSuccess: failedCount === 0,
        });
    } catch (e) {
        console.error("Failed to approve on behalf", e);
        toast.timeout({ text: "Failed to approve on behalf of student.", isSuccess: false });
    } finally {
        isApprovingOnBehalf.value = false;
    }
}

/**
 * Renotifies the given unconfirmed students via the session-level renotify endpoint.
 * @param students The students to renotify.
 */
async function handleRenotifySelected(students: StudentRow[]): Promise<void> {
    if (isRenotifying.value || !renotifyEndpoint || students.length === 0) return;
    isRenotifying.value = true;

    const toast = window.sbProps.toastActivate("Renotifying student(s)...");

    try {
        const response = await apiRequest<ApiRenotifyResponse, { studentIds: number[] }>({
            url: renotifyEndpoint,
            method: "POST",
            body: { studentIds: students.map((s) => s.userId) },
        });

        if (!response.success) {
            toast.timeout({ text: response.message ?? "Failed to renotify student(s).", isSuccess: false });
            return;
        }

        clearSelection();
        toast.timeout({
            text: response.data.message ?? `${students.length} student${students.length === 1 ? "" : "s"} renotified.`,
            isSuccess: !response.data.partial,
        });
    } catch (e) {
        console.error("Failed to renotify students", e);
        toast.timeout({ text: "Failed to renotify student(s).", isSuccess: false });
    } finally {
        isRenotifying.value = false;
    }
}

window.Vue.onMounted((): void => {
    void loadCandidates();
});
</script>

<template>
    <div class="small-12 island u-flex-col u-gap-m">
        <div class="u-flex u-flex--align-center u-flex--justify-space f-no-margins">
            <h2 class="subheader f-no-margins">{{ props.sessionTitle }}</h2>
            <sbx-pop-out content-id="ib-report-actions-dropdown"></sbx-pop-out>
        </div>
        <ul id="ib-report-actions-dropdown" class="f-dropdown" data-dropdown-content aria-hidden="true">
            <li>
                <a :href="props.candidateExportEndpoint" class="sbx-button--has-icon">
                    <sbx-icon name="download"></sbx-icon>
                    Export to CSV
                </a>
            </li>
            <li>
                <a :href="props.candidateNumberUpdateRoute" class="sbx-button--has-icon">
                    <sbx-icon name="modify"></sbx-icon>
                    Update candidate numbers
                </a>
            </li>
        </ul>

        <div v-if="isLoading" class="loader"></div>

        <data-load-error v-else-if="loadError" data-title="registration report" :further-info="loadError" />

        <div v-else class="u-flex-col u-gap-s">
            <ib-report-section title="Preparation" :count="preparation.length">
                <ib-report-standard-table
                    v-model:selected-list="preparationSelected"
                    :students="preparation"
                    :search-languages-endpoint="props.searchLanguagesEndpoint"
                    section="preparation"
                    :active-selection-section="activeSelectionSection"
                    @complete-preparation-single="(student) => handleCompletePreparation([student])"
                    @reset-details-single="(id) => promptResetDetails([id])"
                />
            </ib-report-section>

            <ib-report-section title="Students with unconfirmed details" :count="unconfirmed.length">
                <ib-report-standard-table
                    v-model:selected-list="unconfirmedSelected"
                    :students="unconfirmed"
                    :search-languages-endpoint="props.searchLanguagesEndpoint"
                    section="unconfirmed"
                    :active-selection-section="activeSelectionSection"
                    @renotify-single="
                        (userId) => handleRenotifySelected(unconfirmed.filter((s) => s.userId === userId))
                    "
                    @approve-on-behalf-single="(id) => handleApproveOnBehalf(unconfirmed.filter((s) => s.id === id))"
                    @reset-details-single="(id) => promptResetDetails([id])"
                />
            </ib-report-section>

            <ib-report-section title="Students with confirmed details ready for submission" :count="confirmed.length">
                <ib-report-standard-table
                    v-model:selected-list="confirmedSelected"
                    :students="confirmed"
                    :search-languages-endpoint="props.searchLanguagesEndpoint"
                    section="confirmed"
                    :active-selection-section="activeSelectionSection"
                    @register-via-api-single="(id) => promptRegisterViaApi([id])"
                    @reset-details-single="(id) => promptResetDetails([id])"
                    @manually-register-single="(id) => handleManuallyRegister([id])"
                />
            </ib-report-section>

            <ib-report-section title="In progress" :count="inProgress.length">
                <ib-report-in-progress-table :students="inProgress" />
            </ib-report-section>

            <ib-report-section title="Failed registrations" :count="failed.length">
                <ib-report-failed-table
                    v-model:selected-list="failedSelected"
                    :students="failed"
                    :active-selection-section="activeSelectionSection"
                    @register-via-api-single="(id) => promptRegisterViaApi([id])"
                    @reset-details-single="(id) => promptResetDetails([id])"
                    @manually-register-single="(id) => handleManuallyRegister([id])"
                />
            </ib-report-section>

            <ib-report-section title="Registered" :count="registered.length">
                <ib-report-registered-table :students="registered" />
            </ib-report-section>
        </div>

        <div id="ib-report-pop-out-container"></div>

        <ib-update-language-modal />

        <ib-student-confirmation-modal
            v-model:is-open="registerViaApiModalOpen"
            :students="studentsToRegister"
            description="Registering via API will queue the selected student(s) for the next IBIS submission run."
            confirm-label="Register via API"
            :is-loading="isRegistering"
            @confirm="handleRegisterViaApi"
            @cancel="registerViaApiModalOpen = false"
        />

        <ib-student-confirmation-modal
            v-model:is-open="resetModalOpen"
            :students="studentsToReset"
            description="Resetting will clear all registration details and move the selected student(s) back to Preparation."
            confirm-label="Reset all details"
            :is-loading="isResetting"
            @confirm="handleResetDetails"
            @cancel="resetModalOpen = false"
        />

        <ib-report-action-drawer
            :selected-count="
                preparationSelected.length +
                unconfirmedSelected.length +
                confirmedSelected.length +
                failedSelected.length
            "
            :is-loading="
                isCompleting ||
                isRegistering ||
                isResetting ||
                isApprovingOnBehalf ||
                isManuallyRegistering ||
                isRenotifying
            "
            :active-section="activeSelectionSection"
            @complete-preparation="handleCompletePreparation(preparationSelected)"
            @register-via-api="promptRegisterViaApi([...confirmedSelected, ...failedSelected].map((s) => s.id))"
            @approve-on-behalf="handleApproveOnBehalf(unconfirmedSelected)"
            @renotify="handleRenotifySelected(unconfirmedSelected)"
            @reset-details="
                promptResetDetails([...unconfirmedSelected, ...confirmedSelected, ...failedSelected].map((s) => s.id))
            "
            @manually-register="handleManuallyRegister([...confirmedSelected, ...failedSelected].map((s) => s.id))"
            @clear-selection="clearSelection"
        />
    </div>
</template>
