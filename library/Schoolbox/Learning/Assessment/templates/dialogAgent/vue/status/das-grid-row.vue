<script setup lang="ts">
import { getRelativeTime } from "@js/util/dateUtils";
import UserLink from "@schoolboxVue/components/user/user-link.vue";
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";
import { STATUS_LABELS, STATUS_TICKS } from "@schoolboxAssessment/Types/dialogAgent";
import type { DialogAgentParticipant, DialogAgentScenarioCriteria } from "./dialogAgentStatusTypes";
import DasActions from "./das-actions.vue";

interface Props {
    criteria: DialogAgentScenarioCriteria[];
    participant: DialogAgentParticipant;
}

const props = defineProps<Props>();

/**
 * Retrieve the title to show for a progress tick for a participant
 * @param participant The participant to get the title for
 * @returns The title string
 */
function getProgressTickTitle(participant: DialogAgentParticipant): string {
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
    <tr>
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
                    class="progress-tick readonly l-mar-m--top l-mar-s--right"
                    :class="STATUS_TICKS[participant.status]"
                    :title="getProgressTickTitle(participant)"
                    :data-tooltip="getProgressTickTitle(participant) || undefined"
                />
            </nav>
        </td>

        <td class="u-sticky-left u-sticky-left--offset-1 u-border-right actions-small-1">
            <nav>
                <div class="l-mar-s--top l-pad-xs--top">
                    <sbx-pop-out
                        :content-id="`response-options-${participant.user.id}`"
                        move-to="#dialog-agent-status-dropdown-container"
                        is-close-on-content-click
                    />
                </div>
            </nav>
            <ul :id="`response-options-${participant.user.id}`" class="f-dropdown" data-dropdown-content>
                <das-actions :participant="participant" />
            </ul>
        </td>
        <td v-for="field in props.criteria" :key="field.id" class="u-sticky-left--row">
            {{ participant.feedback[field.id] || "" }}
        </td>
    </tr>
</template>

<style scoped lang="scss"></style>
