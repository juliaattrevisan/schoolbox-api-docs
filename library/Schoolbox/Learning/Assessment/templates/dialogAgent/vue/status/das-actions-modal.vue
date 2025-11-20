<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { assertUnreachable } from "@js/util/errorUtils";
import { DialogAgentResponseAction } from "@schoolboxAssessment/Types/dialogAgent";
import type { DialogAgentParticipant } from "./dialogAgentStatusTypes";
import type { ConfirmableAction } from "./das-actions.vue";

interface Props {
    participant: DialogAgentParticipant;
    action: ConfirmableAction;
}

const props = defineProps<Props>();
const emit = defineEmits(["confirm", "cancel"]);
</script>

<template>
    <h2>
        Confirm
        <template v-if="props.action === DialogAgentResponseAction.ACTION_SOBO"> Submission </template>
        <template v-else-if="props.action === DialogAgentResponseAction.ACTION_RESET"> Reset </template>
        <template v-else-if="props.action === DialogAgentResponseAction.ACTION_REOPEN"> Reopen </template>
        <template v-else>{{ assertUnreachable(props.action) }}</template>
    </h2>

    <p>
        <template v-if="props.action === DialogAgentResponseAction.ACTION_SOBO">
            You are about to submit the active {{ locale.dialogAgent.singular() }} on behalf of
            {{ props.participant.user.fullName }}.
        </template>
        <template v-else-if="props.action === DialogAgentResponseAction.ACTION_RESET">
            Are you sure you want to reset the {{ locale.dialogAgent.singular() }} for
            {{ props.participant.user.fullName }}? This will end the current response and create a new blank one to
            start again from.
        </template>
        <template v-else-if="props.action === DialogAgentResponseAction.ACTION_REOPEN">
            This will unsubmit the current {{ locale.dialogAgent.singular() }} and allow
            {{ props.participant.user.fullName }} to continue from where they left off.
        </template>
        <template v-else>{{ assertUnreachable(props.action) }}</template>
    </p>

    <ul class="flex-list buttons">
        <li><a class="button submit" @click="emit('confirm')">Proceed</a></li>
        <li><a class="button" @click="emit('cancel')">Cancel</a></li>
    </ul>
</template>
