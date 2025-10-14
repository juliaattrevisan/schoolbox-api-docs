<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { EMPTY_DATE } from "@schoolboxVue/components/dateTimePicker/datePickerTypes";
import DateRange from "@schoolboxVue/components/dateTimePicker/date-range.vue";
import type { AiSession, AiSessionEntry, AiUserLogApiRequest, AiUserLogApiResponse } from "./payload";

interface Props {
    routeSearch: string;
    features: Record<string, { title: string }>;
}

const props = defineProps<Props>();

const isLoading = window.Vue.ref(true);
const loadError = window.Vue.ref<string | null>(null);

const featureFilter = window.Vue.ref<keyof typeof props.features | null>(null);
const userFilter = window.Vue.ref<SearchSelectItem[]>([]);
const startDateFilter = window.Vue.ref<string>(EMPTY_DATE);
const endDateFilter = window.Vue.ref<string>(EMPTY_DATE);

const usageSessions = window.Vue.ref<AiSession[]>([]);

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

    const response = await apiRequest<AiUserLogApiResponse, AiUserLogApiRequest>({
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
                AI Usage<template v-if="!isLoading">: {{ usageSessions.length }} results</template>
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
        <div class="small-12 columns">
            <div v-if="loadError" data-alert class="alert-box alert">
                {{ loadError }}
            </div>

            <div v-if="usageSessions.length > 0" class="scrollable">
                <table>
                    <thead>
                        <tr>
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
                                <component
                                    :is="session._routes.getLog ? 'a' : 'span'"
                                    :href="session._routes.getLog ? '#' : undefined"
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
    </div>
</template>

<style scoped></style>
