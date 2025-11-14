<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { getRelativeTime } from "@js/util/dateUtils";
import type { DateRFC3339 } from "@js/util/dateUtils";
import type { UserCC } from "@schoolboxLibrary/types/User";
import UserLink from "@schoolboxVue/components/user/user-link.vue";
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";
import { STATUS_LABELS, STATUS_TICKS } from "@schoolboxAssessment/Types/dialogAgent";
import type { DialogAgentResponseStatus } from "@schoolboxAssessment/Types/dialogAgent";
import DasFilters from "./das-filters.vue";

type DialogAgentScenarioCriteria = {
    id: string;
    title: string;
};

interface Props {
    criteria: DialogAgentScenarioCriteria[];
    participants: {
        id: number;
        user: UserCC;
        status: DialogAgentResponseStatus;
        assistanceRequestedAt: DateRFC3339 | null;
        submittedAt: DateRFC3339 | null;
        feedback: Record<DialogAgentScenarioCriteria["id"], string>;
        _routes: {
            summary?: string | null;
            view?: string | null;
            sobo?: string | null;
            reset?: string | null;
            reopen?: string | null;
            resolved?: string | null;
        };
    }[];
}

const props = defineProps<Props>();

/**
 * Retrieve the title to show for a progress tick for a participant
 * @param participant The participant to get the title for
 * @returns The title string
 */
function getProgressTickTitle(participant: Props["participants"][number]): string {
    if (participant.assistanceRequestedAt !== null) {
        return `Assistance requested ${getRelativeTime(participant.assistanceRequestedAt, true)}`;
    }

    if (participant.submittedAt !== null) {
        return `Submitted ${getRelativeTime(participant.submittedAt, true)}`;
    }

    return STATUS_LABELS[participant.status];
}
</script>

<template>
    <div class="row">
        <!-- Metadata about the activity -->
        <div class="small-12 large-4 columns">
            <slot name="meta"></slot>
        </div>

        <!-- Status filters -->
        <div class="small-12 large-8 columns text-right">
            <das-filters />
        </div>
    </div>

    <div class="row">
        <div class="small-12 island">
            <div class="dialog-agent-grid scrollable">
                <table>
                    <thead>
                        <tr>
                            <th class="u-sticky-left u-sticky-left--first"></th>
                            <th class="u-sticky-left u-sticky-left--offset-1 u-border-right"></th>
                            <th v-for="field in props.criteria" :key="field.id" class="field-col">{{ field.title }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="participant in props.participants" :key="participant.id">
                            <td class="u-sticky-left u-sticky-left--first actions-small-1" style="padding-bottom: 0">
                                <div class="small-12 card">
                                    <user-link :user="participant.user">
                                        <user-photo :user="participant.user" render="small" />
                                        <h3 class="l-mar-xl--right">{{ participant.user.fullName }}</h3>
                                    </user-link>
                                    <p>
                                        <span
                                            class="meta"
                                            :title="getProgressTickTitle(participant)"
                                            :data-tooltip="getProgressTickTitle(participant) || undefined"
                                        >
                                            {{ STATUS_LABELS[participant.status] }}
                                        </span>
                                    </p>
                                </div>
                                <nav>
                                    <span
                                        class="progress-tick readonly l-mar-s--top l-mar-s--right"
                                        :class="STATUS_TICKS[participant.status]"
                                        :title="getProgressTickTitle(participant)"
                                        :data-tooltip="getProgressTickTitle(participant) || undefined"
                                    />
                                </nav>
                            </td>

                            <td class="u-sticky-left u-sticky-left--offset-1 u-border-right actions-small-1">
                                <nav>
                                    <div class="l-mar-xs--top">
                                        <sbx-pop-out
                                            :content-id="`response-options-${participant.user.id}`"
                                            move-to="#dialog-agent-status-dropdown-container"
                                        ></sbx-pop-out>
                                    </div>
                                </nav>
                                <ul
                                    :id="`response-options-${participant.user.id}`"
                                    class="f-dropdown"
                                    data-dropdown-content
                                >
                                    <li v-if="participant._routes.view">
                                        <a
                                            :href="participant._routes.view"
                                            class="icon-task"
                                            :title="`View ${locale.dialogAgent.proper()}`"
                                        >
                                            View {{ locale.dialogAgent.proper() }}
                                        </a>
                                    </li>
                                    <li v-if="participant._routes.summary">
                                        <a
                                            :href="participant._routes.summary"
                                            class="icon-history"
                                            :title="`${locale.dialogAgent.proper()} history`"
                                        >
                                            {{ locale.dialogAgent.proper() }} History
                                        </a>
                                    </li>
                                    <!-- TODO -->
                                </ul>
                            </td>
                            <td v-for="field in props.criteria" :key="field.id" class="u-sticky-left--row">
                                {{ participant.feedback[field.id] || "" }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="dialog-agent-status-dropdown-container"></div>
        </div>
    </div>
</template>

<style scoped lang="scss">
.dialog-agent-grid {
    --sbx-table-u-sticky-width-first: 16.5rem;
    max-height: 90vh;

    .field-col {
        min-width: 18rem;
    }
}
</style>
