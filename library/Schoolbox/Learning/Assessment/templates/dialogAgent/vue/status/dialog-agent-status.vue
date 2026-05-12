<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { fromEntriesOf } from "@js/util/arrayUtils";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { DialogAgentResponseStatus } from "@schoolboxAssessment/Types/dialogAgent";
import type { DialogAgentParticipant, DialogAgentScenarioCriteria } from "./dialogAgentStatusTypes";
import DasFilters from "./das-filters.vue";
import DasGrid from "./das-grid.vue";

interface Props {
    criteria: DialogAgentScenarioCriteria[];
    assessments: {
        id: number;
        folder: {
            id: number;
            title: string;
        };
        participants: DialogAgentParticipant[];
    }[];
    exportRoute: string;
    isSpecification?: boolean;
}

const props = defineProps<Props>();

const collapsedImpls = window.Vue.ref<number[]>([]);

const participantSearchFilter = window.Vue.ref<string>("");
const filters = window.Vue.ref<Record<DialogAgentResponseStatus, boolean>>(
    fromEntriesOf(Object.values(DialogAgentResponseStatus).map((status) => [status, true])),
);

const exportRouteFiltered = window.Vue.computed(() => {
    /** No export links on specification assessments, or when export route is unavailable */
    if (!props.exportRoute || props.isSpecification) {
        return null;
    }

    /** If all filters are enabled, just keep the base route */
    if (Object.values(filters.value).every((isEnabled) => isEnabled)) {
        return props.exportRoute;
    }

    /** Otherwise extend the export route so that that it respects the current filters */
    const url = new URL(props.exportRoute, window.location.origin);
    url.searchParams.set("participantSearch", participantSearchFilter.value);

    for (const [status, isEnabled] of Object.entries(filters.value)) {
        if (isEnabled) {
            url.searchParams.append(`status[]`, status);
        }
    }

    return url.toString();
});

/**
 * Reset all filters to show everything again
 */
function resetFilters(): void {
    for (const status of Object.values(DialogAgentResponseStatus)) {
        filters.value[status] = true;
    }
}

/**
 * Toggle the collapsed state of an assessment implementation
 * @param assessmentId The assessment implementation ID to toggle
 */
function toggleCollapse(assessmentId: number): void {
    const index = collapsedImpls.value.indexOf(assessmentId);
    if (index === -1) {
        collapsedImpls.value.push(assessmentId);
    } else {
        collapsedImpls.value.splice(index, 1);
    }
}
</script>

<template>
    <Teleport v-if="exportRouteFiltered" to="#header-options">
        <li>
            <a :href="exportRouteFiltered" class="sbx-button--has-icon"
                ><sbx-icon name="export"></sbx-icon>Export CSV</a
            >
        </li>
    </Teleport>

    <!-- TODO: bulk actions -->

    <div class="row">
        <!-- Metadata about the activity -->
        <div class="small-12 large-4 columns">
            <slot name="meta"></slot>
        </div>

        <!-- Status filters -->
        <div class="small-12 large-8 columns text-right">
            <das-filters v-model:filters="filters" />
        </div>
    </div>

    <!-- Search -->
    <div class="row">
        <div class="small-12 columns">
            <input v-model="participantSearchFilter" type="search" placeholder="Search for a student" />
        </div>
    </div>

    <div id="dialog-agent-status-dropdown-container"></div>
    <!-- Grid for each implementation -->
    <div v-for="assessment in props.assessments" :key="assessment.id" class="component-container">
        <div v-if="props.isSpecification" class="row">
            <div class="small-12 columns">
                <div class="component-titlebar">
                    <div class="actions-small-1">
                        <div class="list-item">
                            <h2>
                                <a :href="`/homepage/${assessment.folder.id}`">{{ assessment.folder.title }}</a>
                            </h2>
                        </div>
                        <nav>
                            <a
                                href="#"
                                class="collapse"
                                :class="collapsedImpls.includes(assessment.id) ? 'icon-down-arrow' : 'icon-up-arrow'"
                                title="Expand/collapse content"
                                @click="toggleCollapse(assessment.id)"
                            ></a>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
            <div v-show="!collapsedImpls.includes(assessment.id)" class="row">
                <div class="small-12 island">
                    <das-grid
                        :criteria="props.criteria"
                        :participants="assessment.participants"
                        :filters="filters"
                        :participant-search-filter="participantSearchFilter"
                        @reset-filters="resetFilters"
                    />
                </div>
            </div>
        </Transition>
    </div>
    <div v-if="props.assessments.length === 0" class="empty-state">
        <sbx-icon name="dialog-agent" size="2.5rem"></sbx-icon>
        <p>This {{ locale.activity.singular() }} has no students participating.</p>
    </div>
</template>

<li><a :href="exportRouteFiltered" class="sbx-button--has-icon"><sbx-icon name="export"></sbx-icon>Export CSV</a></li>
