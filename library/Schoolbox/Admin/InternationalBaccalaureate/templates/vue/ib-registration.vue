<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import EmptyState from "@schoolboxVue/components/empty-state.vue";
import CreateSessionModal from "./components/create-session-modal.vue";
import type { RegistrationSession, RegistrationSessionsResponse, SystemPurpose } from "../js/types/ibRegistrationTypes";

interface Props {
    sessionsEndpoint: string;
    createEndpoint: string;
    reportBaseUrl: string;
}
const props = defineProps<Props>();

type RegistrationSection = {
    systemPurpose: SystemPurpose;
    sessions: RegistrationSession[];
};

const sessionsBySystemPurpose = window.Vue.ref<Record<string, RegistrationSession[]>>({});
const systemPurposes = window.Vue.ref<SystemPurpose[]>([]);
const isLoading = window.Vue.ref<boolean>(true);
const activeCreateSystemPurpose = window.Vue.ref<SystemPurpose | null>(null);

const sessionPendingDelete = window.Vue.ref<RegistrationSession | null>(null);
const sessionPendingDeleteSystemPurposeId = window.Vue.ref<number | null>(null);
const isDeleteModalOpen = window.Vue.ref<boolean>(false);
const isDeleting = window.Vue.ref<boolean>(false);
const deleteError = window.Vue.ref<string | null>(null);

const registrationSections = window.Vue.computed<RegistrationSection[]>(() =>
    systemPurposes.value.map((systemPurpose) => ({
        systemPurpose,
        sessions: sessionsBySystemPurpose.value[String(systemPurpose.id)] ?? [],
    })),
);

const isCreateModalOpen = window.Vue.computed<boolean>({
    get: () => activeCreateSystemPurpose.value !== null,
    set: (isOpen) => {
        if (!isOpen) {
            activeCreateSystemPurpose.value = null;
        }
    },
});

/**
 *  Get the heading for a registration section based on the system purpose.
 *  @param systemPurpose - The system purpose for the section
 *  @returns The heading for the section
 */
function getSectionHeading(systemPurpose: SystemPurpose): string {
    return `Student Registration Information for ${systemPurpose.name}`;
}

/** Fetches sessions and system purposes from the API and builds registration sections. */
async function loadRegistrationSessions(): Promise<void> {
    isLoading.value = true;

    const response = await apiRequest<RegistrationSessionsResponse>({
        url: props.sessionsEndpoint,
        method: "GET",
    });

    isLoading.value = false;

    if (!response.success || typeof response.data !== "object" || response.data === null) {
        return;
    }

    sessionsBySystemPurpose.value = response.data.data;
    systemPurposes.value = Object.values(response.data.systemPurposes);
}

/**
 * Appends a newly created session to the active system purpose list.
 * @param session - The newly created registration session returned by the API
 */
function onRegistrationSessionCreated(session: RegistrationSession): void {
    const systemPurpose = activeCreateSystemPurpose.value;
    if (!systemPurpose) {
        return;
    }

    const systemPurposeId = String(systemPurpose.id);
    sessionsBySystemPurpose.value = {
        ...sessionsBySystemPurpose.value,
        [systemPurposeId]: [...(sessionsBySystemPurpose.value[systemPurposeId] ?? []), session],
    };
}

/**
 * Opens the create session modal for the given system purpose.
 * @param systemPurpose - The system purpose for the new session
 */
function openCreateModal(systemPurpose: SystemPurpose): void {
    activeCreateSystemPurpose.value = systemPurpose;
}

/**
 * Opens the delete confirmation modal for the given session.
 * @param systemPurposeId - The system purpose the session belongs to
 * @param session - The session to delete
 */
function openDeleteConfirm(systemPurposeId: number, session: RegistrationSession): void {
    sessionPendingDelete.value = session;
    sessionPendingDeleteSystemPurposeId.value = systemPurposeId;
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
    const systemPurposeId = sessionPendingDeleteSystemPurposeId.value;
    if (systemPurposeId === null) {
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
        const key = String(systemPurposeId);
        sessionsBySystemPurpose.value = {
            ...sessionsBySystemPurpose.value,
            [key]: (sessionsBySystemPurpose.value[key] ?? []).filter((s) => s.id !== session.id),
        };
        isDeleteModalOpen.value = false;
        sessionPendingDelete.value = null;
        sessionPendingDeleteSystemPurposeId.value = null;
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
    void loadRegistrationSessions();
});
</script>

<template>
    <div class="small-12 island u-flex-col u-gap-m">
        <div v-if="isLoading" class="loader" data-test="ib-registration-loading"></div>

        <template v-else>
            <empty-state v-if="registrationSections.length === 0" icon="icon-list">
                <p>No registration session types are available.</p>
            </empty-state>

            <template v-else>
                <div
                    v-for="section in registrationSections"
                    :key="section.systemPurpose.id"
                    class="registration-section u-flex-col u-gap-s f-no-margins"
                    :data-test="`ib-registration-system-purpose-${section.systemPurpose.id}`"
                >
                    <empty-state v-if="section.sessions.length === 0" icon="icon-add">
                        <p>{{ getSectionHeading(section.systemPurpose) }}</p>
                        <p>
                            No registration sessions exist.
                            <button
                                type="button"
                                class="button"
                                :data-test="`ib-registration-system-purpose-${section.systemPurpose.id}-add-link`"
                                @click.prevent="openCreateModal(section.systemPurpose)"
                            >
                                Create a new session
                            </button>
                        </p>
                    </empty-state>

                    <template v-else>
                        <div class="u-flex u-flex--align-center u-flex--justify-space f-no-margins">
                            <h2 class="subheader f-no-margins">
                                {{ getSectionHeading(section.systemPurpose) }}
                            </h2>
                            <button
                                type="button"
                                class="sbx-icon-only-button"
                                style="font-size: 1.5rem; line-height: 1"
                                :data-test="`ib-registration-system-purpose-${section.systemPurpose.id}-add`"
                                :aria-label="`Create new ${section.systemPurpose.name} examination session`"
                                @click.prevent="openCreateModal(section.systemPurpose)"
                            >
                                +
                            </button>
                        </div>

                        <div class="session-list-box">
                            <ul
                                class="session-list"
                                :data-test="`ib-registration-system-purpose-${section.systemPurpose.id}-list`"
                            >
                                <li
                                    v-for="session in section.sessions"
                                    :key="session.id"
                                    class="session-item"
                                    :data-test="`ib-registration-system-purpose-${section.systemPurpose.id}-session-${session.id}`"
                                >
                                    <div class="u-flex u-flex--align-center u-flex--justify-space u-gap-xs">
                                        <div class="u-flex-col">
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
                                            @click="openDeleteConfirm(section.systemPurpose.id, session)"
                                        >
                                            <sbx-icon name="delete"></sbx-icon>
                                        </button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </template>
                </div>
            </template>
        </template>

        <CreateSessionModal
            v-if="activeCreateSystemPurpose"
            v-model:is-open="isCreateModalOpen"
            :create-endpoint="createEndpoint"
            :system-purpose-id="activeCreateSystemPurpose.id"
            :system-purpose-name="activeCreateSystemPurpose.name"
            @session-created="onRegistrationSessionCreated"
        />

        <BasicModal
            v-model:is-open="isDeleteModalOpen"
            size="s"
            :show-close-button="true"
            :is-close-disabled="isDeleting"
        >
            <form class="u-flex-col u-gap-s f-no-margins">
                <h2>Please Confirm</h2>
                <p>Are you sure you want to delete this session?</p>
                <request-error v-if="deleteError" :further-info="deleteError" />
                <div class="u-flex u-gap-xs f-no-margins">
                    <button type="button" class="button alert u-flex__item--1" @click="confirmDelete">
                        {{ isDeleting ? "Deleting..." : "Delete" }}
                    </button>
                    <button type="button" class="button u-flex__item--1" @click="isDeleteModalOpen = false">
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
