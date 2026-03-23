<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { assertUnreachable } from "@js/util/errorUtils";
import type { UserAuthor, UserCC } from "@schoolboxLibrary/types/User";
import { DialogAgentResponseAction } from "@schoolboxAssessment/Types/dialogAgent";
import type { ConfirmableAction } from "./das-actions.vue";

interface Props {
    user: UserCC | UserAuthor;
    action: ConfirmableAction;
}

const props = defineProps<Props>();
const emit = defineEmits(["confirm", "cancel"]);

const isSameUser = window.Vue.computed(() => props.user.id === window.schoolboxUser.id);
</script>

<template>
    <h2>
        Confirm
        <template v-if="props.action === DialogAgentResponseAction.ACTION_SUBMIT"> Submission </template>
        <template v-else-if="props.action === DialogAgentResponseAction.ACTION_RESET"> Reset </template>
        <template v-else-if="props.action === DialogAgentResponseAction.ACTION_REOPEN"> Reopen </template>
        <template v-else>{{ assertUnreachable(props.action) }}</template>
    </h2>

    <p>
        <template v-if="props.action === DialogAgentResponseAction.ACTION_SUBMIT">
            <template v-if="isSameUser">
                Are you sure you want to finalise this {{ locale.dialogAgent.singular() }}? Once submitted, you will not
                be able to make further changes.
            </template>
            <template v-else>
                You are about to submit the active {{ locale.dialogAgent.singular() }} on behalf of
                {{ props.user.fullName }}.
            </template>
        </template>
        <template v-else-if="props.action === DialogAgentResponseAction.ACTION_RESET">
            Are you sure you want to reset the {{ locale.dialogAgent.singular() }} for {{ props.user.fullName }}? This
            will end the current response and create a new blank one to start again from.
        </template>
        <template v-else-if="props.action === DialogAgentResponseAction.ACTION_REOPEN">
            This will unsubmit the current {{ locale.dialogAgent.singular() }} and allow {{ props.user.fullName }} to
            continue from where they left off.
        </template>
        <template v-else>{{ assertUnreachable(props.action) }}</template>
    </p>

    <ul class="flex-list buttons">
        <li><a class="button submit" data-test="das-actions-modal-confirm" @click="emit('confirm')">Proceed</a></li>
        <li><a class="button" @click="emit('cancel')">Cancel</a></li>
    </ul>
</template>
