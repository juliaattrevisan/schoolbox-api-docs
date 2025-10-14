<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { EMPTY_DATE } from "@schoolboxVue/components/dateTimePicker/datePickerTypes";
import DateRange from "@schoolboxVue/components/dateTimePicker/date-range.vue";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import type {
    AgentChatFeedMessage,
    AgentChatRequest,
    AgentChatResponse,
} from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type {
    AiSession,
    AiSessionEntry,
    AiUsageLogApiRequest,
    AiUsageLogApiResponse,
    AiSessionLogApiResponse,
} from "./payload";

interface Props {
    routeSearch: string;
    features: Record<string, { title: string }>;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

const isLoading = window.Vue.ref(true);
const loadError = window.Vue.ref<string | null>(null);

const featureFilter = window.Vue.ref<keyof typeof props.features | null>(null);
const userFilter = window.Vue.ref<SearchSelectItem[]>([]);
const startDateFilter = window.Vue.ref<string>(EMPTY_DATE);
const endDateFilter = window.Vue.ref<string>(EMPTY_DATE);

const usageSessions = window.Vue.ref<AiSession[]>([]);

const isLoadingSession = window.Vue.ref(false);
const sessionDetails = window.Vue.ref<{
    sessionId: string;
    feed: AgentChatFeedMessage[];
} | null>(null);

/**
 * Get the formatted date to show for a log entry
 * @param log The log entry
 * @returns The formatted createdAt date
 */
function getLogDateFormatted(log: AiSessionEntry): string {
    return window.dayjs(log.createdAt).format(window.dateDisplayFormats.dayjsDateLong);
}

/**
 * Load the usage logs for the current filters
 */
async function loadUsageLogs(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    const response = await apiRequest<AiUsageLogApiResponse, AiUsageLogApiRequest>({
        method: "POST",
        url: props.routeSearch,
        body: {
            keyword: null,
            feature: featureFilter.value,
            user: userFilter.value.length > 0 ? userFilter.value[0].id : null,
            after: startDateFilter.value !== EMPTY_DATE ? startDateFilter.value : null,
            before: endDateFilter.value !== EMPTY_DATE ? endDateFilter.value : null,
            cursor: null,
        },
    });

    if (!response.success) {
        isLoading.value = false;
        usageSessions.value = [];
        loadError.value = response.message;
        return;
    }

    usageSessions.value = response.data.data;

    isLoading.value = false;
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

    isLoadingSession.value = true;
    sessionDetails.value = null;

    const response = await apiRequest<AiSessionLogApiResponse>({
        method: "GET",
        url: session._routes.getLog,
    });

    if (!response.success) {
        loadError.value = response.message;
        return;
    }

    sessionDetails.value = {
        sessionId: session.id,
        feed: response.data.flatMap((entry) => {
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
        }),
    };
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
        <div class="small-12 columns" :class="{ 'medium-6': isLoadingSession || sessionDetails !== null }">
            <div v-if="loadError" data-alert class="alert-box alert">
                {{ loadError }}
            </div>

            <div v-if="usageSessions.length > 0" class="scrollable">
                <table>
                    <thead>
                        <tr>
                            <th></th>
                            <th>Session ID</th>
                            <th>Date</th>
                            <th>Feature</th>
                            <th>User</th>
                            <th>Log Count</th>
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
                                        :checked="session.id === sessionDetails?.sessionId"
                                        :disabled="!session._routes.getLog || undefined"
                                        @change="() => openSessionDetails(session)"
                                    />
                                    <label :for="`chk-${session.id}`" class="radio-label no-margin"></label>
                                </div>
                            </td>
                            <td>
                                <component
                                    :is="session._routes.getLog ? 'a' : 'span'"
                                    @click="() => openSessionDetails(session)"
                                >
                                    {{ session.id }}
                                </component>
                            </td>
                            <td>
                                {{ getLogDateFormatted(session.entries[0]) }}
                            </td>
                            <td>
                                {{ props.features[session.entries[0].feature]?.title ?? "unknown" }}
                            </td>
                            <td>
                                <component
                                    :is="session.entries[0].createdBy._links.profile ? 'a' : 'span'"
                                    :href="session.entries[0].createdBy._links.profile ?? undefined"
                                    :title="session.entries[0].createdBy._links.profile ? 'View Profile' : undefined"
                                >
                                    {{ session.entries[0].createdBy.fullName }}
                                </component>
                            </td>
                            <td>
                                {{ session.entries.length }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div v-if="isLoadingSession || sessionDetails !== null" class="small-12 medium-6 island">
            <section>
                <div v-if="sessionDetails === null" class="loader"></div>
                <ac-feed v-else class="l-mar-m" :feed="sessionDetails.feed" :allow-selection="false" />
            </section>
        </div>
    </div>
</template>

<style scoped></style>
