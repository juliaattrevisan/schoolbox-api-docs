<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import IbReportSection from "./components/ib-report-section.vue";
import IbReportStandardTable from "./components/ib-report-standard-table.vue";
import IbReportFailedTable from "./components/ib-report-failed-table.vue";
import IbReportInProgressTable from "./components/ib-report-in-progress-table.vue";
import IbReportRegisteredTable from "./components/ib-report-registered-table.vue";
import IbUpdateLanguageModal from "./components/ib-update-language-modal.vue";
import IbReportActionDrawer from "./components/ib-report-action-drawer.vue";
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
} from "../js/types/ibReportTypes";

interface Props {
    candidatesEndpoint: string;
    candidateExportEndpoint: string;
    searchLanguagesEndpoint: string;
    renotifyEndpoint: string;
    completePreparationEndpoint: string;
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

const selectedStudentIds = window.Vue.ref<Set<number>>(new Set());
const isCompleting = window.Vue.ref<boolean>(false);

function mapSubjectEntry(apiSubject: ApiSubject): SubjectEntry {
    const lang = apiSubject.languages[0] ?? null;
    return {
        groupSubjectId: apiSubject.id,
        subject: apiSubject.subject,
        language: lang?.languageName ?? null,
        languageId: lang?.languageId ?? null,
    };
}

function mapStudentRow(s: ApiStudent, stage: StudentRow["stage"], studentRenotifyEndpoint: string | null = null): StudentRow {
    return {
        id: s.registrationSessionStudentId ?? s.userId,
        name: s.userName,
        userId: s.userId,
        candidateDetailsStatus: s.hasMissingDetails ? "unconfirmed" : "confirmed",
        subjects: {
            hl: s.ibGroupSubjects.HL.map(mapSubjectEntry),
            sl: s.ibGroupSubjects.SL.map(mapSubjectEntry),
            core: s.ibGroupSubjects.Core.map(mapSubjectEntry),
        },
        updateLanguageEndpoint: s._routes.updateLanguages,
        renotifyEndpoint: studentRenotifyEndpoint,
        stage,
    };
}

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
        failed.value = (byKey["failed"] ?? []).map((s) => mapStudentRow(s, "failed") as FailedRow);
        registered.value = (byKey["registered"] ?? []).map((s) => mapStudentRow(s, "registered") as RegisteredRow);
    } catch (e) {
        console.error("Failed to load candidates", e);
        loadError.value = "Failed to load registration report.";
    } finally {
        isLoading.value = false;
    }
}

updateLanguageStore.init(props.searchLanguagesEndpoint, preparation, unconfirmed, confirmed, failed);

function toggleSelection(studentId: number): void {
    const next = new Set(selectedStudentIds.value);
    if (next.has(studentId)) {
        next.delete(studentId);
    } else {
        next.add(studentId);
    }
    selectedStudentIds.value = next;
}

async function handleCompletePreparation(studentIds: number[]): Promise<void> {
    if (isCompleting.value) return;
    isCompleting.value = true;

    const toast = window.sbProps.toastActivate("Completing preparation...");

    try {
        const response = await apiRequest<{ success: boolean; message?: string }, { studentIds: number[] }>({
            url: props.completePreparationEndpoint,
            method: "POST",
            body: { studentIds },
        });

        if (!response.success) {
            toast.timeout({ text: response.message ?? "Failed to complete preparation.", isSuccess: false });
            return;
        }

        const removedIds = new Set(studentIds);
        preparation.value = preparation.value.filter((s) => !removedIds.has(s.id));
        selectedStudentIds.value = new Set();
        toast.timeout({
            text: `Preparation completed for ${studentIds.length} student${studentIds.length === 1 ? "" : "s"}.`,
        });
    } catch (e) {
        console.error("Failed to complete preparation", e);
        toast.timeout({ text: "Failed to complete preparation.", isSuccess: false });
    } finally {
        isCompleting.value = false;
    }
}

window.Vue.onMounted((): void => {
    void loadCandidates();
});
</script>

<template>
    <div class="small-12 island l-flex-col l-gap-m">
        <div class="l-flex l-flex--align-center l-flex--justify-space f-no-margins">
            <h2 class="subheader f-no-margins">{{ props.sessionTitle }}</h2>
            <a
                :href="props.candidateExportEndpoint"
                class="sbx-icon-only-button"
                aria-label="Download candidate details as CSV"
                title="Download candidate details as CSV"
            >
                <sbx-icon name="download"></sbx-icon>
            </a>
        </div>

        <div v-if="isLoading" class="loader"></div>

        <data-load-error v-else-if="loadError" data-title="registration report" :further-info="loadError" />

        <div v-else class="l-flex-col l-gap-s">
            <ib-report-section title="Preparation" :count="preparation.length">
                <ib-report-standard-table
                    :students="preparation"
                    :search-languages-endpoint="props.searchLanguagesEndpoint"
                    :selected-student-ids="selectedStudentIds"
                    @toggle-selection="toggleSelection"
                    @complete-preparation-single="(id) => handleCompletePreparation([id])"
                />
            </ib-report-section>

            <ib-report-section title="Students with unconfirmed details" :count="unconfirmed.length">
                <ib-report-standard-table :students="unconfirmed" />
            </ib-report-section>

            <ib-report-section title="Students with confirmed details ready for submission" :count="confirmed.length">
                <ib-report-standard-table :students="confirmed" />
            </ib-report-section>

            <ib-report-section title="In progress" :count="inProgress.length">
                <ib-report-in-progress-table :students="inProgress" />
            </ib-report-section>

            <ib-report-section title="Failed registrations" :count="failed.length">
                <ib-report-failed-table :students="failed" />
            </ib-report-section>

            <ib-report-section title="Registered" :count="registered.length">
                <ib-report-registered-table :students="registered" />
            </ib-report-section>
        </div>

        <div id="ib-report-pop-out-container"></div>

        <ib-update-language-modal />

        <ib-report-action-drawer
            v-if="selectedStudentIds.size > 0"
            :selected-count="selectedStudentIds.size"
            :is-loading="isCompleting"
            @complete-preparation="handleCompletePreparation([...selectedStudentIds])"
            @clear-selection="selectedStudentIds = new Set()"
        />
    </div>
</template>
