<script setup lang="ts">
import { getRelativeTime } from "@js/util/dateUtils";
import UserLink from "@schoolboxVue/components/user/user-link.vue";
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";
import { STATUS_LABELS, STATUS_TICKS } from "@schoolboxAssessment/Types/dialogAgent";
import { getResponseStatus } from "./dialogAgentStatusTypes";
import type {
    ApiDialogAgentActionResponse,
    DialogAgentParticipant,
    DialogAgentScenarioCriteria,
} from "./dialogAgentStatusTypes";
import DasActions from "./das-actions.vue";

interface Props {
    criteria: DialogAgentScenarioCriteria[];
    participant: DialogAgentParticipant;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: "updateResponse", data: ApiDialogAgentActionResponse): void;
}>();

/**
 * Retrieve the title to show for a progress tick for a participant
 * @param participant The participant to get the title for
 * @returns The title string
 */
function getProgressTickTitle(participant: DialogAgentParticipant): string {
    if (participant.response !== null) {
        if (participant.response.assistanceRequestedAt !== null) {
            return `Assistance requested ${getRelativeTime(participant.response.assistanceRequestedAt, true)}`;
        }

        if (participant.response.submittedAt !== null) {
            return `Submitted ${getRelativeTime(participant.response.submittedAt, true)}`;
        }
    }

    return STATUS_LABELS[getResponseStatus(participant.response)];
}
</script>

<template>
    <tr data-test="das-grid-row">
        <td class="u-sticky-left u-sticky-left--first u-border-right actions-small-1">
            <div class="small-12 card">
                <user-link :user="participant.user">
                    <user-photo :user="participant.user" render="small" />
                    <h3 class="l-mar-xl--right" data-test="das-grid-row-user-name">{{ participant.user.fullName }}</h3>
                </user-link>
                <p>
                    <span
                        class="progress-tick tiny no-text readonly"
                        :class="STATUS_TICKS[getResponseStatus(participant.response)]"
                    />
                    <span
                        class="meta"
                        :title="getProgressTickTitle(participant)"
                        :data-tooltip="getProgressTickTitle(participant) || undefined"
                        data-test="das-grid-row-status-meta"
                    >
                        {{ STATUS_LABELS[getResponseStatus(participant.response)] }}
                    </span>
                </p>
            </div>
            <nav>
                <sbx-pop-out
                    :content-id="`response-options-${participant.user.id}`"
                    move-to="#dialog-agent-status-dropdown-container"
                    is-close-on-content-click
                />
            </nav>
        </td>

        <td class="u-sticky-left u-sticky-left--offset-1 u-border-right actions-small-1">
            <span
                class="progress-tick readonly l-mar-s--top"
                :class="STATUS_TICKS[getResponseStatus(participant.response)]"
                :title="getProgressTickTitle(participant)"
                :data-tooltip="getProgressTickTitle(participant) || undefined"
            />
            <ul :id="`response-options-${participant.user.id}`" class="f-dropdown" data-dropdown-content>
                <das-actions
                    :user="participant.user"
                    :response="participant.response"
                    :summary-route="participant._routes.summary"
                    @update-response="(data) => emit('updateResponse', data)"
                />
            </ul>
        </td>
        <td v-for="field in props.criteria" :key="field.id" class="u-sticky-left--row">
            {{ participant.feedback[field.id] || "" }}
        </td>
    </tr>
</template>

<style scoped lang="scss">
/**
 * This to avoids extra padding showing up on the first column
 * due to the user photo inside the card having some negative margin schenanigans
 */
tr td:first-of-type {
    padding-bottom: 0;
}
</style>
