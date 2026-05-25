<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import EmptyState from "@schoolboxVue/components/empty-state.vue";
import CreateSessionModal from "./components/create-session-modal.vue";
import type { RegistrationSession } from "../js/types/ibRegistrationTypes";

interface Props {
    sessionsEndpoint: string;
    createEndpoint: string;
    reportBaseUrl: string;
}
const props = defineProps<Props>();

// ── DIPLOMA section state (systemPurposeId: 1) ───────────────────────────
// Future sections: add separate refs below this block, each with its own systemPurposeId.

const DIPLOMA_SYSTEM_PURPOSE_ID = 1;

const diplomaSessions = window.Vue.ref<RegistrationSession[]>([]);
const isDiplomaLoading = window.Vue.ref<boolean>(true);
const isDiplomaModalOpen = window.Vue.ref<boolean>(false);

const sessionPendingDelete = window.Vue.ref<RegistrationSession | null>(null);
const isDeleteModalOpen = window.Vue.ref<boolean>(false);
const isDeleting = window.Vue.ref<boolean>(false);
const deleteError = window.Vue.ref<string | null>(null);

/** Fetches sessions from the API and populates the DIPLOMA section. On failure, leaves the list empty (shows empty state). */
async function loadDiplomaSessions(): Promise<void> {
    isDiplomaLoading.value = true;

    const response = await apiRequest<Record<string, RegistrationSession[]>>({
        url: props.sessionsEndpoint,
        method: "GET",
    });

    isDiplomaLoading.value = false;

    if (!response.success || typeof response.data !== "object" || Array.isArray(response.data)) {
        return;
    }

    diplomaSessions.value = response.data[String(DIPLOMA_SYSTEM_PURPOSE_ID)] ?? [];
}

/**
 * Appends a newly created session to the DIPLOMA list.
 * @param session - The newly created registration session returned by the API
 */
function onDiplomaSessionCreated(session: RegistrationSession): void {
    diplomaSessions.value = [...diplomaSessions.value, session];
}

/**
 * Opens the delete confirmation modal for the given session.
 * @param session - The session to delete
 */
function openDeleteConfirm(session: RegistrationSession): void {
    sessionPendingDelete.value = session;
    isDeleteModalOpen.value = true;
}

/**
 * Calls the delete API for the pending session, then removes it from the list on success.
 */
async function confirmDelete(): Promise<void> {
    // Prevent multiple clicks
    if (isDeleting.value) {
        return;
    }
    const session = sessionPendingDelete.value;
    if (!session?.deleteEndpoint) {
        return;
    }
    const toast = window.sbProps.toastActivate("Deleting session...");
    try {
        isDeleting.value = true;
        deleteError.value = null;
        const response = await apiRequest({
            url: session.deleteEndpoint,
            method: "DELETE",
        });
        if (!response.success) {
            throw new Error(response.message || "Failed to delete");
        }
        toast.timeout({ text: "Session deleted", isSuccess: true });
        diplomaSessions.value = diplomaSessions.value.filter((s) => s.id !== session.id);
        isDeleteModalOpen.value = false;
        sessionPendingDelete.value = null;
    } catch (error) {
        // ignore abort errors, as the previous request was cancelled
        if (isAbortError(error)) {
            return;
        }
        if (error instanceof Error && hasErrorMessage(error)) {
            deleteError.value = error.message;
        }
        toast.timeout({ text: "Delete failed", isSuccess: false });
        console.error(error);
    } finally {
        isDeleting.value = false;
    }
}

const reportBaseUrl = window.Vue.computed<string | null>(() =>
    props.reportBaseUrl ? props.reportBaseUrl.replace(/\/$/, "") : null,
);

window.Vue.onMounted((): void => {
    void loadDiplomaSessions();
});
</script>

<template>
    <div class="small-12 island l-flex-col l-gap-m">
        <!-- ── DIPLOMA SECTION ───────────────────────────────────────────────
             Hardcoded: Student Registration Information for Diploma Programme Year 2.
             Future sections are added below as additional hardcoded blocks,
             each with its own reactive state (sessions, loading, error, modal).
             Empty state: entire section collapses into a single card (no external heading).
             Populated state: heading + add button appear above the session list.
        ──────────────────────────────────────────────────────────────────── -->
        <div v-if="isDiplomaLoading" class="loader" data-test="ib-registration-diploma-loading"></div>

        <template v-else>
            <empty-state v-if="diplomaSessions.length === 0" icon="icon-add">
                <p>Student Registration Information for Diploma Programme Year 2 [DIPLOMA]</p>
                <p>
                    No registration sessions exist.
                    <button
                        type="button"
                        class="button"
                        data-test="ib-registration-diploma-add-link"
                        @click.prevent="isDiplomaModalOpen = true"
                    >
                        Create a new session
                    </button>
                </p>
            </empty-state>

            <!-- Populated state -->
            <template v-else>
                <div class="l-flex l-flex--align-center l-flex--justify-space f-no-margins">
                    <h2 class="subheader f-no-margins">
                        Student Registration Information for Diploma Programme Year 2 [DIPLOMA]
                    </h2>
                    <button
                        type="button"
                        class="sbx-icon-only-button"
                        style="font-size: 1.5rem; line-height: 1"
                        data-test="ib-registration-diploma-add"
                        aria-label="Create new Diploma Programme Year 2 examination session"
                        @click.prevent="isDiplomaModalOpen = true"
                    >
                        +
                    </button>
                </div>

                <div class="session-list-box">
                    <ul class="session-list" data-test="ib-registration-diploma-list">
                        <li
                            v-for="session in diplomaSessions"
                            :key="session.id"
                            class="session-item"
                            :data-test="`ib-registration-diploma-session-${session.id}`"
                        >
                            <div class="l-flex l-flex--align-center l-flex--justify-space l-gap-xs">
                                <div class="l-flex-col">
                                    <a v-if="reportBaseUrl" :href="`${reportBaseUrl}/${session.id}`">{{
                                        session.title
                                    }}</a>
                                    <span v-else>{{ session.title }}</span>
                                    <small
                                        >Session created by {{ session.createdBy }} on
                                        {{ new Date(session.createdAt).toLocaleDateString() }},
                                        {{ new Date(session.createdAt).toLocaleTimeString() }}</small
                                    >
                                </div>
                                <button
                                    v-if="session.deleteEndpoint"
                                    type="button"
                                    class="sbx-icon-only-button"
                                    :aria-label="`Delete session ${session.title}`"
                                    :title="`Delete session ${session.title}`"
                                    @click="openDeleteConfirm(session)"
                                >
                                    <sbx-icon name="delete"></sbx-icon>
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </template>
        </template>

        <CreateSessionModal
            v-model:is-open="isDiplomaModalOpen"
            :create-endpoint="createEndpoint"
            :system-purpose-id="DIPLOMA_SYSTEM_PURPOSE_ID"
            @session-created="onDiplomaSessionCreated"
        />

        <BasicModal
            v-model:is-open="isDeleteModalOpen"
            size="s"
            :show-close-button="true"
            :is-close-disabled="isDeleting"
        >
            <form class="l-flex-col l-gap-s f-no-margins">
                <h2>Please Confirm</h2>
                <p>Are you sure you want to delete this session?</p>
                <request-error v-if="deleteError" :further-info="deleteError" />
                <div class="l-flex l-gap-xs f-no-margins">
                    <button type="button" class="button alert l-flex__item--1" @click="confirmDelete">
                        {{ isDeleting ? "Deleting..." : "Delete" }}
                    </button>
                    <button type="button" class="button l-flex__item--1" @click="isDeleteModalOpen = false">
                        Cancel
                    </button>
                </div>
            </form>
        </BasicModal>
    </div>
</template>

<style scoped>
.session-list-box {
    background-color: var(--sbx-color-foreground-primary, white);
    border-radius: var(--sbx-config-container-corner-radius, 0);
    box-shadow: var(--sbx-config-container-drop-shadow, none);
    padding: 0 1rem;
}

.session-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.session-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--sbx-color-border, #e8e8e8);
}

.session-item:last-child {
    border-bottom: none;
}
</style>
