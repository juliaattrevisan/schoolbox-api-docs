<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import type { DialogAgentParticipant } from "./dialogAgentStatusTypes";

interface Props {
    participant: DialogAgentParticipant;
}

const props = defineProps<Props>();
</script>

<template>
    <li v-if="props.participant._routes.view">
        <a :href="props.participant._routes.view" class="icon-view">
            {{ `View ${locale.dialogAgent.proper()}` }}
        </a>
    </li>
    <li v-if="props.participant._routes.summary">
        <a :href="props.participant._routes.summary" class="icon-history">
            {{ `${locale.dialogAgent.proper()} History` }}
        </a>
    </li>
    <li v-if="props.participant._routes.sobo">
        <button class="icon-approve" @click="">
            {{ `Submit on behalf of ${props.participant.user.fullName}` }}
        </button>
    </li>
    <li v-if="props.participant._routes.reset">
        <button class="icon-refresh" @click="">
            {{ `Reset ${locale.dialogAgent.proper()}` }}
        </button>
    </li>
    <li v-if="props.participant._routes.reopen">
        <button class="icon-undo" @click="">
            {{ `Reopen ${locale.dialogAgent.proper()}` }}
        </button>
    </li>
    <li v-if="props.participant._routes.resolved">
        <button class="icon-medical" @click="">
            {{ `Mark as assisted` }}
        </button>
    </li>
</template>
