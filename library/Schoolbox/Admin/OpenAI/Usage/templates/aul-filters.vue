<script setup lang="ts">
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import DateRange from "@schoolboxVue/components/dateTimePicker/date-range.vue";
import { STATUS_LABELS } from "./moderationEvent";
import type { AiModerationStatus } from "./moderationEvent";
import type { AiFeature } from "./payload";

interface Props {
    features: Record<AiFeature, { title: string }>;
}

const props = defineProps<Props>();
const emit = defineEmits(["applyFilters"]);

const keywordFilter = defineModel<string>("keywordFilter", { required: true });
const featureFilter = defineModel<keyof typeof props.features | null>("featureFilter", { required: true });
const userFilter = defineModel<SearchSelectItem[]>("userFilter", { required: true });
const startDateFilter = defineModel<string>("startDateFilter", { required: true });
const endDateFilter = defineModel<string>("endDateFilter", { required: true });
const moderationStatusFilter = defineModel<AiModerationStatus[]>("moderationStatusFilter", { required: true });
</script>

<template>
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

                <div>
                    <label>Moderation Status</label>
                    <div class="l-flex-row l-gap-m">
                        <div v-for="(label, status) in STATUS_LABELS" :key="status">
                            <input
                                :id="`ai-usage-log-moderation-${status}`"
                                v-model="moderationStatusFilter"
                                type="checkbox"
                                :value="status"
                            />
                            <label :for="`ai-usage-log-moderation-${status}`" class="checkbox-label">
                                {{ label }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>
    </section>

    <div class="row">
        <div class="small-12 columns">
            <button class="button submit" @click="emit('applyFilters')">Filter</button>
        </div>
    </div>
</template>
