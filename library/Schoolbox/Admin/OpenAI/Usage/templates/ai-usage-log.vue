<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { PaginationMeta } from "@js/util/sbx/apiUtils";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { EMPTY_DATE } from "@schoolboxVue/components/dateTimePicker/datePickerTypes";
import DateRange from "@schoolboxVue/components/dateTimePicker/date-range.vue";
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";
import RelativeTime from "@schoolboxVue/components/relativeTime/relative-time.vue";
import PaginatorButtons from "@schoolboxVue/components/paginator-buttons.vue";
import { AiLogFormat } from "./payload";
import type {
    AiSessionId,
    AiFeature,
    AiSession,
    AiUsageLogApiRequest,
    AiUsageLogApiResponse,
    AiSessionLogApiResponse,
} from "./payload";
import AulAgentChat from "./aul-agent-chat.vue";

interface Props {
    routeSearch: string;
    features: Record<string, { title: string }>;
}

const PAGE_SIZE = 30;

const UNKNOWN_FEATURE_TEXT = "Unknown Feature";

const props = defineProps<Props>();

const isLoading = window.Vue.ref(true);
const loadError = window.Vue.ref<string | null>(null);

const keywordFilter = window.Vue.ref<string>("");
const featureFilter = window.Vue.ref<keyof typeof props.features | null>(null);
const userFilter = window.Vue.ref<SearchSelectItem[]>([]);
const startDateFilter = window.Vue.ref<string>(EMPTY_DATE);
const endDateFilter = window.Vue.ref<string>(EMPTY_DATE);

const usageSessions = window.Vue.ref<AiSession[]>([]);
const pageInfo = window.Vue.ref<PaginationMeta | null>(null);

const sessionLoadError = window.Vue.ref<string | null>(null);
const selectedSessionId = window.Vue.ref<AiSessionId | null>(null);
const selectedSessionLog = window.Vue.ref<AiSessionLogApiResponse | null>(null);

type AiSelectedSessionInfo<TFeature extends AiFeature = AiFeature> = {
    [KLogFormat in AiLogFormat]: {
        logFormat: KLogFormat;
        session: AiSession<KLogFormat, TFeature>;
        log: AiSessionLogApiResponse<KLogFormat, TFeature>;
    };
}[AiLogFormat];

type AiSelectedSessionInfoLoading = Pick<AiSelectedSessionInfo, "session">;

const selectedSessionInfo = window.Vue.computed<AiSelectedSessionInfo | AiSelectedSessionInfoLoading | null>(() => {
    if (selectedSessionId.value === null) {
        return null;
    }

    const sessionInfo = usageSessions.value.find((s) => s.id === selectedSessionId.value);
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
    const filteredLog = logInfo.filter((entry) => entry.logFormat !== null && entry.data !== null);

    const info = { logFormat: sessionInfo.logFormat, session: sessionInfo, log: filteredLog };
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
 * Get the formatted createdAt date for a given AI session
 * @param session the given session
 * @returns The formatted createdAt date
 */
function formatSessionDate(session: AiSession): string {
    return window.dayjs(session.createdAt).format(window.dateDisplayFormats.dayjsDatetime);
}

/**
 * Load the usage logs for the current filters
 */
async function loadUsageLogs(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    closeSessionDetails();

    usageSessions.value = [];

    const response = await apiRequest<AiUsageLogApiResponse, AiUsageLogApiRequest>({
        method: "POST",
        url: props.routeSearch,
        body: {
            keyword: keywordFilter.value.trim() !== "" ? keywordFilter.value.trim() : null,
            feature: featureFilter.value,
            user: userFilter.value.length > 0 ? Number(userFilter.value[0].id) : null,
            startDate: startDateFilter.value !== EMPTY_DATE ? startDateFilter.value : null,
            endDate: endDateFilter.value !== EMPTY_DATE ? endDateFilter.value : null,
            page: pageInfo.value !== null ? pageInfo.value.pagination.current : 1,
            limit: pageInfo.value !== null ? pageInfo.value.pagination.limit : PAGE_SIZE,
        },
    });

    isLoading.value = false;

    if (!response.success) {
        loadError.value = response.message;
        return;
    }

    usageSessions.value = response.data.data;
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

window.Vue.onMounted(async () => {
    await loadUsageLogs();
});

/**
 * Open the details for a specific session
 * @param session The session to open
 */
async function openSessionDetails(session: AiSession): Promise<void> {
    if (!session._routes.getLog) {
        return; // no access to view the log
    }

    sessionLoadError.value = null;
    selectedSessionId.value = session.id;
    selectedSessionLog.value = null;

    const response = await apiRequest<AiSessionLogApiResponse>({
        method: "GET",
        url: session._routes.getLog,
    });

    if (!response.success) {
        sessionLoadError.value = response.message;
        return;
    }

    selectedSessionLog.value = response.data;
}
</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <section>
                <fieldset class="content">
                    <legend>Filter</legend>
                    <div class="small-12 columns l-flex-col l-gap-m">
                        <label>
                            Keyword
                            <input
                                v-model="keywordFilter"
                                class="f-no-margins"
                                type="text"
                                name="keyword"
                                placeholder="Filter by keyword"
                            />
                        </label>
                        <div class="l-flex-row l-flex--no-wrap l-gap-m">
                            <div class="l-flex-col l-flex__item--1">
                                <label>
                                    Feature
                                    <select v-model="featureFilter" name="feature" class="f-no-margins">
                                        <option :value="null">All</option>
                                        <option
                                            v-for="(featureConf, feature) in props.features"
                                            :key="feature"
                                            :value="feature"
                                        >
                                            {{ featureConf.title }}
                                        </option>
                                    </select>
                                </label>
                            </div>
                            <div class="l-flex-col l-flex__item--1">
                                <search-select
                                    v-model:selected="userFilter"
                                    label="User"
                                    label-id="users"
                                    endpoint="/ajax/user.php?groupID=0&"
                                    :is-single-select="true"
                                    placeholder="Search for user..."
                                />
                            </div>
                        </div>

                        <date-range
                            ref="dateRangeRef"
                            v-model:start-date="startDateFilter"
                            v-model:end-date="endDateFilter"
                            id-prefix="ai-usage-log-filter"
                        />
                    </div>
                </fieldset>
            </section>
            <div class="row">
                <div class="small-12 columns">
                    <button class="button submit" @click="loadUsageLogs">Filter</button>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="list-item">
            <h2 class="subheader">
                Activity<template v-if="!isLoading">: {{ usageSessions.length }} events</template>
            </h2>
        </div>
    </div>

    <div class="row">
        <div class="small-12 medium-6 columns l-mar-m--bottom" :class="{ island: isLoading }">
            <div v-if="loadError" data-alert class="alert-box alert">
                {{ loadError }}
            </div>

            <section v-else-if="isLoading">
                <div class="loader"></div>
            </section>

            <div v-else-if="usageSessions.length > 0">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Feature</th>
                            <th>User</th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="session in usageSessions" :key="session.id ?? undefined">
                            <td>
                                {{ formatSessionDate(session) }}
                            </td>
                            <td>
                                {{ props.features[session.feature]?.title ?? UNKNOWN_FEATURE_TEXT }}
                            </td>
                            <td>
                                <span v-if="!session.createdBy">System</span>
                                <component
                                    :is="session.createdBy._links.profile ? 'a' : 'span'"
                                    v-else
                                    :href="session.createdBy._links.profile ?? undefined"
                                    :title="session.createdBy._links.profile ? 'View Profile' : undefined"
                                >
                                    {{ session.createdBy.fullName }}
                                </component>
                            </td>
                            <td>{{ 200 !== session.returnCode ? "Error" : "" }}</td>
                            <td class="actions-small-1">
                                <nav v-if="session.id !== null && session.logFormat !== null && session._routes.getLog">
                                    <button
                                        type="button"
                                        class="sbx-icon-only-button f-no-margins"
                                        title="View session details"
                                        aria-label="View session details"
                                        :disabled="session.id.toString() === selectedSessionId?.toString()"
                                        @click="() => openSessionDetails(session)"
                                    >
                                        <sbx-icon name="view" />
                                    </button>
                                </nav>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <paginator-buttons
                v-if="pageInfo !== null && pageInfo.pagination.pages > 1"
                :page-count="pageInfo.pagination.pages"
                :current="pageInfo.pagination.current"
                @page-change="changePage"
            />
        </div>
        <div class="small-12 medium-6 island">
            <div v-if="sessionLoadError" data-alert class="alert-box alert">
                {{ sessionLoadError }}
            </div>
            <section v-else-if="selectedSessionInfo !== null">
                <div class="card small-12">
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
                <div class="clearfix"></div>

                <div v-if="!('log' in selectedSessionInfo)" class="loader"></div>
                <div v-else-if="selectedSessionInfo.log.length === 0" class="empty-state">
                    <sbx-icon name="hidden" size="2rem" />
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
                        :session-log="selectedSessionInfo.log"
                    />
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped></style>
