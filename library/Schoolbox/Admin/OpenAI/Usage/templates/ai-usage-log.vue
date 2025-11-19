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
import { AiFeature } from "./payload";
import type { AiSession, AiUsageLogApiRequest, AiUsageLogApiResponse, AiSessionLogApiResponse } from "./payload";
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
const selectedSessionId = window.Vue.ref<AiSession["id"] | null>(null);
const selectedSessionLog = window.Vue.ref<AiSessionLogApiResponse | null>(null);

type AiSelectedSessionInfo = {
    [K in AiFeature]: {
        feature: K;
        session: AiSession<K>;
        log: AiSessionLogApiResponse<K>;
    };
}[AiFeature];

const selectedSessionInfo = window.Vue.computed<AiSelectedSessionInfo | null>(() => {
    if (selectedSessionId.value === null) {
        return null;
    }

    const sessionInfo = usageSessions.value.find((s) => s.id === selectedSessionId.value);
    const logInfo = selectedSessionLog.value;

    if (sessionInfo === undefined || logInfo === null) {
        return null;
    }

    const info = { feature: sessionInfo.feature, session: sessionInfo, log: logInfo };
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
            user: userFilter.value.length > 0 ? userFilter.value[0].id : null,
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
        <div
            class="small-12 columns l-mar-m--bottom"
            :class="{ 'medium-6': selectedSessionId !== null || sessionLoadError !== null, island: isLoading }"
        >
            <div v-if="loadError" data-alert class="alert-box alert">
                {{ loadError }}
            </div>

            <section v-else-if="isLoading">
                <div class="loader"></div>
            </section>

            <div v-else-if="usageSessions.length > 0" class="scrollable">
                <table>
                    <thead>
                        <tr>
                            <th></th>
                            <th>Date</th>
                            <th>Feature</th>
                            <th>User</th>
                            <th>&nbsp;</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="session in usageSessions" :key="session.id">
                            <td>
                                <div v-if="session.sessionId" class="selector">
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
                            <td v-if="200 !== session.returnCode">Error</td>
                            <td v-else>&nbsp;</td>
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
                    <div class="l-mar-m">
                        <aul-agent-chat
                            v-if="selectedSessionInfo.feature === AiFeature.IMAGE"
                            :session-log="selectedSessionInfo.log"
                        />
                        <div v-else class="empty-state">
                            <i class="icon-view-hollow"></i>
                            <p>
                                The {{ props.features[selectedSessionInfo.feature].title }} feature does not support
                                previews
                            </p>
                        </div>
                    </div>
                </template>
            </section>
        </div>
    </div>
</template>

<style scoped></style>
