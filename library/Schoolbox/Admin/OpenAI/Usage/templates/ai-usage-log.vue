<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { PaginationMeta } from "@js/util/sbx/apiUtils";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { EMPTY_DATE } from "@schoolboxVue/components/dateTimePicker/datePickerTypes";
import DateRange from "@schoolboxVue/components/dateTimePicker/date-range.vue";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import RelativeTime from "@schoolboxVue/components/relativeTime/relative-time.vue";
import PaginatorButtons from "@schoolboxVue/components/paginator-buttons.vue";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import type {
    AgentChatFeedMessage,
    AgentChatRequest,
    AgentChatResponse,
} from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type { AiSession, AiUsageLogApiRequest, AiUsageLogApiResponse, AiSessionLogApiResponse } from "./payload";

interface Props {
    routeSearch: string;
    features: Record<string, { title: string }>;
}

const PAGE_SIZE = 30;

const UNKNOWN_FEATURE_TEXT = "Unknown Feature";

const props = defineProps<Props>();
const acStore = useAgentChatStore();

const isLoading = window.Vue.ref(true);
const loadError = window.Vue.ref<string | null>(null);

const featureFilter = window.Vue.ref<keyof typeof props.features | null>(null);
const userFilter = window.Vue.ref<SearchSelectItem[]>([]);
const startDateFilter = window.Vue.ref<string>(EMPTY_DATE);
const endDateFilter = window.Vue.ref<string>(EMPTY_DATE);

const usageSessions = window.Vue.ref<AiSession[]>([]);
const pageInfo = window.Vue.ref<PaginationMeta | null>(null);

const sessionLoadError = window.Vue.ref<string | null>(null);
const selectedSessionId = window.Vue.ref<AiSession["id"] | null>(null);
const selectedSessionFeed = window.Vue.ref<AgentChatFeedMessage[] | null>(null);

const selectedSessionInfo = window.Vue.computed(() => {
    if (selectedSessionId.value === null) {
        return null;
    }

    const sessionInfo = usageSessions.value.find((s) => s.id === selectedSessionId.value);
    const feedInfo = selectedSessionFeed.value;

    if (sessionInfo === undefined || feedInfo === null) {
        return null;
    }

    return {
        session: sessionInfo,
        feed: feedInfo,
    };
});

/**
 * Close the current session details pane
 */
function closeSessionDetails(): void {
    sessionLoadError.value = null;
    selectedSessionId.value = null;
    selectedSessionFeed.value = null;
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
            keyword: null,
            feature: featureFilter.value,
            user: userFilter.value.length > 0 ? userFilter.value[0].id : null,
            after: startDateFilter.value !== EMPTY_DATE ? startDateFilter.value : null,
            before: endDateFilter.value !== EMPTY_DATE ? endDateFilter.value : null,
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
    selectedSessionFeed.value = null;

    const response = await apiRequest<AiSessionLogApiResponse>({
        method: "GET",
        url: session._routes.getLog,
    });

    if (!response.success) {
        sessionLoadError.value = response.message;
        return;
    }

    // TODO: this is going to have to handle whatever happens for lesson plans/non-chat stuff but for now just assume it's a chat

    selectedSessionFeed.value = response.data.flatMap((entry) => {
        const req: AgentChatRequest = {
            type: "request",
            entryKey: acStore.getNextEntryKey(),
            user: entry.createdBy,
            payload: {
                // TODO
                userPrompt: "prompt message",
                attributes: {},
                imageUrl: null,
                previousResponseId: null,
                aiSessionId: session.id,
            },
            file: null,
        };

        const res: AgentChatResponse = {
            type: "response",
            entryKey: acStore.getNextEntryKey(),
            status: "success", // assume success for now...
            responseId: null, // no openai response ID available I guess
            image: null,
            message: "response message", // TODO
        };

        return [req, res];
    });
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
                            Feature:
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

                        <search-select
                            v-model:selected="userFilter"
                            label="User"
                            label-id="users"
                            endpoint="/ajax/user.php?groupID=0&"
                            :is-single-select="true"
                            placeholder="Search for user..."
                        />

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

    <div v-if="isLoading" class="row">
        <div class="small-12 island">
            <section>
                <div class="loader"></div>
            </section>
        </div>
    </div>

    <div v-else class="row">
        <div
            class="small-12 columns l-mar-m--bottom"
            :class="{ 'medium-6': selectedSessionId !== null || sessionLoadError !== null }"
        >
            <div v-if="loadError" data-alert class="alert-box alert">
                {{ loadError }}
            </div>

            <div v-if="usageSessions.length > 0" class="scrollable">
                <table>
                    <thead>
                        <tr>
                            <th></th>
                            <th>Date</th>
                            <th>Feature</th>
                            <th>User</th>
                            <th>Session ID</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="session in usageSessions" :key="session.id">
                            <td>
                                <div class="selector">
                                    <input
                                        :id="`chk-${session.id}`"
                                        type="radio"
                                        name="ai-usage-session"
                                        :checked="session.id.toString() === selectedSessionId?.toString() || undefined"
                                        :disabled="!session._routes.getLog || undefined"
                                        @change="() => openSessionDetails(session)"
                                    />
                                    <label :for="`chk-${session.id}`" class="radio-label no-margin"></label>
                                </div>
                            </td>
                            <td>
                                {{ formatSessionDate(session) }}
                            </td>
                            <td>
                                {{ props.features[session.feature]?.title ?? UNKNOWN_FEATURE_TEXT }}
                            </td>
                            <td>
                                <component
                                    :is="session.createdBy._links.profile ? 'a' : 'span'"
                                    :href="session.createdBy._links.profile ?? undefined"
                                    :title="session.createdBy._links.profile ? 'View Profile' : undefined"
                                >
                                    {{ session.createdBy.fullName }}
                                </component>
                            </td>
                            <td>
                                <component
                                    :is="session._routes.getLog ? 'a' : 'span'"
                                    @click="() => openSessionDetails(session)"
                                >
                                    {{ session.id }}
                                </component>
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
        <div v-if="selectedSessionId !== null || sessionLoadError !== null" class="small-12 medium-6 island">
            <div v-if="sessionLoadError" data-alert class="alert-box alert">
                {{ sessionLoadError }}
            </div>
            <section v-else>
                <div v-if="selectedSessionInfo === null" class="loader"></div>
                <template v-else>
                    <div class="card small-12">
                        <span>
                            <user-photo :user-id="selectedSessionInfo.session.createdBy.id || 0" render="small" />
                            <h3>
                                {{ props.features[selectedSessionInfo.session.feature]?.title ?? UNKNOWN_FEATURE_TEXT }}
                            </h3>
                        </span>
                        <p class="meta">
                            Created by
                            <component
                                :is="selectedSessionInfo.session.createdBy._links.profile ? 'a' : 'span'"
                                :href="selectedSessionInfo.session.createdBy._links.profile ?? undefined"
                            >
                                {{ selectedSessionInfo.session.createdBy.fullName }}
                            </component>
                            <relative-time :datetime="selectedSessionInfo.session.createdAt" full-diff></relative-time>
                        </p>
                    </div>
                    <div class="clearfix"></div>
                    <ac-feed class="l-mar-m" :feed="selectedSessionInfo.feed" :allow-selection="false" />
                </template>
            </section>
        </div>
    </div>
</template>

<style scoped></style>
