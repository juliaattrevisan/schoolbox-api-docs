<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { PaginationMeta } from "@js/util/sbx/apiUtils";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import { EMPTY_DATE } from "@schoolboxVue/components/dateTimePicker/datePickerTypes";
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";
import RelativeTime from "@schoolboxVue/components/relativeTime/relative-time.vue";
import PaginatorButtons from "@schoolboxVue/components/paginator-buttons.vue";
import type { AiModerationEvent, AiModerationStatus } from "./moderationEvent";
import { AiLogFormat } from "./payload";
import type {
    AiFeature,
    AiSession,
    AiSessionId,
    AiSessionLogApiResponse,
    AiUsageLogApiRequest,
    AiUsageLogApiResponse,
} from "./payload";
import AulAgentChat from "./aul-agent-chat.vue";
import AulFilters from "./aul-filters.vue";
import AulSessionList from "./aul-session-list.vue";

interface Props {
    routeSearch: string;
    features: Record<AiFeature, { title: string }>;
}

const PAGE_SIZE = 30;

const UNKNOWN_FEATURE_TEXT = "Unknown Feature";
const SELECTED_SESSION_ACTIONS_ID = "ai-usage-log-selected-session-actions";

const props = defineProps<Props>();

const isLoading = window.Vue.ref(true);
const loadError = window.Vue.ref<string | null>(null);

const keywordFilter = window.Vue.ref<string>("");
const featureFilter = window.Vue.ref<keyof typeof props.features | null>(null);
const userFilter = window.Vue.ref<SearchSelectItem[]>([]);
const startDateFilter = window.Vue.ref<string>(EMPTY_DATE);
const endDateFilter = window.Vue.ref<string>(EMPTY_DATE);
const moderationStatusFilter = window.Vue.ref<AiModerationStatus[]>([]);

const sessions = window.Vue.ref<AiSession[]>([]);
const pageInfo = window.Vue.ref<PaginationMeta | null>(null);

const sessionLoadError = window.Vue.ref<string | null>(null);
const selectedSessionId = window.Vue.ref<AiSessionId | null>(null);
const selectedSessionLog = window.Vue.ref<AiSessionLogApiResponse | null>(null);

type AiSelectedSessionInfo<TFeature extends AiFeature = AiFeature> = {
    [KLogFormat in AiLogFormat]: {
        logFormat: KLogFormat;
        session: AiSession<KLogFormat, TFeature>;
        sessionLogs: AiSessionLogApiResponse<KLogFormat>["logs"];
        moderationEvents: AiModerationEvent[];
    };
}[AiLogFormat];

type AiSelectedSessionInfoLoading = Pick<AiSelectedSessionInfo, "session">;

const selectedSessionInfo = window.Vue.computed<AiSelectedSessionInfo | AiSelectedSessionInfoLoading | null>(() => {
    if (selectedSessionId.value === null) {
        return null;
    }

    const sessionInfo = sessions.value.find((s) => s.id === selectedSessionId.value);
    if (sessionInfo === undefined) {
        console.warn(`Session ID ${selectedSessionId.value} not found`); // shouldn't get here
        return null;
    }

    const logInfo = selectedSessionLog.value;
    if (logInfo === null) {
        // still loading
        return { session: sessionInfo };
    }

    // TODO: properly support sessions with multiple log formats, for now just filter out invalid logs
    const filteredLogs = logInfo.logs.filter((entry) => entry.logFormat !== null && entry.data !== null);

    // sessionInfo.logFormat only selects the renderer; the renderer interprets each entry's own logFormat.
    const info = {
        logFormat: sessionInfo.logFormat,
        session: sessionInfo,
        sessionLogs: filteredLogs,
        moderationEvents: logInfo.moderationEvents,
    };
    return info satisfies Record<keyof AiSelectedSessionInfo, unknown> as AiSelectedSessionInfo;
});

/**
 * Close the current session details pane
 */
function closeSessionDetails(): void {
    sessionLoadError.value = null;
    selectedSessionId.value = null;
    selectedSessionLog.value = null;
}

/**
 * Load the usage logs for the current filters
 */
async function loadUsageLogs(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    closeSessionDetails();

    sessions.value = [];

    const response = await apiRequest<AiUsageLogApiResponse, AiUsageLogApiRequest>({
        method: "POST",
        url: props.routeSearch,
        body: {
            keyword: keywordFilter.value.trim() !== "" ? keywordFilter.value.trim() : null,
            feature: featureFilter.value,
            user: userFilter.value.length > 0 ? Number(userFilter.value[0].id) : null,
            startDate: startDateFilter.value !== EMPTY_DATE ? startDateFilter.value : null,
            endDate: endDateFilter.value !== EMPTY_DATE ? endDateFilter.value : null,
            moderationStatus: moderationStatusFilter.value.length > 0 ? moderationStatusFilter.value : null,
            page: pageInfo.value !== null ? pageInfo.value.pagination.current : 1,
            limit: pageInfo.value !== null ? pageInfo.value.pagination.limit : PAGE_SIZE,
        },
    });

    isLoading.value = false;

    if (!response.success) {
        loadError.value = response.message;
        return;
    }

    sessions.value = response.data.data;
    pageInfo.value = response.data.metadata;
}

/**
 * To change the currently selected page
 * @param page - The new page number
 */
async function changePage(page: number): Promise<void> {
    if (pageInfo.value !== null) {
        // show that the page changed before loading the data
        pageInfo.value.pagination.current = page;
    }

    await loadUsageLogs();
}

/**
 * Apply the current filters, and invalidate pageInfo cache (go back to the first page)
 * to avoid ending up on a page that no longer exists.
 */
async function applyFilters(): Promise<void> {
    pageInfo.value = null;
    await loadUsageLogs();
}

/**
 * Reset the filters to their default values (show everything)
 */
async function resetFilters(): Promise<void> {
    keywordFilter.value = "";
    featureFilter.value = null;
    userFilter.value = [];
    startDateFilter.value = EMPTY_DATE;
    endDateFilter.value = EMPTY_DATE;
    moderationStatusFilter.value = [];

    await applyFilters();
}

window.Vue.onMounted(resetFilters);

/**
 * Open the details for a specific session
 * @param session The session to open
 */
async function openSessionDetails(session: AiSession): Promise<void> {
    if (!session._routes.getSessionLogs) {
        return; // no access to view the log
    }

    sessionLoadError.value = null;
    selectedSessionId.value = session.id;
    selectedSessionLog.value = null;

    const response = await apiRequest<AiSessionLogApiResponse>({
        method: "GET",
        url: session._routes.getSessionLogs,
    });

    if (!response.success) {
        sessionLoadError.value = response.message;
        return;
    }

    selectedSessionLog.value = response.data;
}

/**
 * Print the currently selected AI session log.
 */
function printSelectedSession(): void {
    window.print();
}
</script>

<template>
    <div class="row no-print">
        <div class="small-12 island">
            <aul-filters
                v-model:keyword-filter="keywordFilter"
                v-model:feature-filter="featureFilter"
                v-model:user-filter="userFilter"
                v-model:start-date-filter="startDateFilter"
                v-model:end-date-filter="endDateFilter"
                v-model:moderation-status-filter="moderationStatusFilter"
                :features="props.features"
                @apply-filters="applyFilters"
            />
        </div>
    </div>

    <div class="no-print">
        <div class="list-item">
            <h2 class="subheader">
                Activity<template v-if="!isLoading">: {{ sessions.length }} events</template>
            </h2>
        </div>
    </div>

    <div class="row">
        <div class="small-12 medium-6 columns l-mar-m--bottom no-print" :class="{ island: isLoading }">
            <div v-if="loadError" data-alert class="alert-box alert">
                {{ loadError }}
            </div>

            <section v-else-if="isLoading">
                <div class="loader"></div>
            </section>

            <div v-else-if="sessions.length > 0">
                <aul-session-list
                    :features="props.features"
                    :sessions="sessions"
                    :selected-session-id="selectedSessionId"
                    @open-session-details="openSessionDetails"
                />
            </div>

            <div v-else class="empty-state">
                <sbx-icon name="ai" size="2.5rem"></sbx-icon>
                <p>
                    No logs match the current filters. Try another filter or
                    <a href="#" @click.prevent="resetFilters">show everything</a>.
                </p>
            </div>

            <paginator-buttons
                v-if="pageInfo !== null && pageInfo.pagination.pages > 1"
                :page-count="pageInfo.pagination.pages"
                :current="pageInfo.pagination.current"
                @page-change="changePage"
            />
        </div>
        <div class="small-12 medium-6 island ai-usage-log__viewer">
            <div v-if="sessionLoadError" data-alert class="alert-box alert">
                {{ sessionLoadError }}
            </div>
            <section v-else-if="selectedSessionInfo !== null">
                <div class="card small-12 actions-small-1">
                    <div class="list-item">
                        <span>
                            <user-photo
                                v-if="selectedSessionInfo.session.createdBy"
                                :user-id="selectedSessionInfo.session.createdBy.id || 0"
                                render="small"
                            />
                            <h3>
                                {{ props.features[selectedSessionInfo.session.feature]?.title ?? UNKNOWN_FEATURE_TEXT }}
                            </h3>
                        </span>
                        <p class="meta">
                            <span v-if="!selectedSessionInfo.session.createdBy"> Created </span>
                            <template v-else>
                                Created by
                                <component
                                    :is="selectedSessionInfo.session.createdBy._links.profile ? 'a' : 'span'"
                                    :href="selectedSessionInfo.session.createdBy._links.profile ?? undefined"
                                >
                                    {{ selectedSessionInfo.session.createdBy.fullName }}
                                </component>
                            </template>
                            <relative-time :datetime="selectedSessionInfo.session.createdAt" full-diff></relative-time>
                        </p>
                    </div>
                    <nav class="no-print">
                        <sbx-pop-out :content-id="SELECTED_SESSION_ACTIONS_ID"></sbx-pop-out>
                    </nav>
                    <ul :id="SELECTED_SESSION_ACTIONS_ID" class="f-dropdown no-print" data-dropdown-content>
                        <li>
                            <a
                                href="#"
                                class="sbx-button--has-icon"
                                data-test="ai-usage-log-print-session"
                                @click.prevent="printSelectedSession"
                            >
                                <sbx-icon name="print"></sbx-icon>
                                Print / Save as PDF
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="clearfix"></div>

                <div v-if="!('logFormat' in selectedSessionInfo)" class="loader"></div>
                <div v-else-if="selectedSessionInfo.sessionLogs.length === 0" class="empty-state">
                    <sbx-icon name="hidden" size="xl" />
                    <p>
                        The {{ props.features[selectedSessionInfo.session.feature].title }} feature does not support
                        previews for this session.
                    </p>
                </div>
                <div v-else class="l-mar-m">
                    <aul-agent-chat
                        v-if="
                            selectedSessionInfo.logFormat === AiLogFormat.AGENT_CHAT ||
                            selectedSessionInfo.logFormat === AiLogFormat.GUARDRAIL
                        "
                        :feature="selectedSessionInfo.session.feature"
                        :session-logs="selectedSessionInfo.sessionLogs"
                        :moderation-events="selectedSessionInfo.moderationEvents"
                    />
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped lang="scss">
/* styles for printing (scoped) */
@media print {
    .no-print {
        display: none !important;
    }

    .ai-usage-log__viewer {
        width: 100% !important;
    }
}
</style>

<style>
/* styles for printing (global) */
@media print {
    #container,
    .island {
        padding: 0; /* to get more of the page estate */
    }

    .breadcrumb,
    .admin-container > .small-12 > h1,
    #footer {
        display: none !important;
    }
}
</style>
