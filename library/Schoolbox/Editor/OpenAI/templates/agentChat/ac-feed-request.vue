<script lang="ts" setup>
import FileDrop from "@js/resource/file-drop.vue";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import type { AgentChatRequest } from "./agentChatTypes";

interface Props {
    message: AgentChatRequest;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="ac-feed-request snow">
        <div class="ac-feed-message-container l-mar-s">
            <file-drop
                v-if="props.message.file"
                :id="`ig-message-file-drop-${props.message.entryKey}`"
                :current-file="props.message.file"
                disabled
            />
            <p class="ac-feed-message-content l-mar--0">{{ props.message.payload.userPrompt }}</p>
        </div>
        <user-photo :user="props.message.user" render="xsmall" :description="null" />
    </div>
</template>

<style scoped lang="scss">
.ac-feed-request {
    align-items: flex-start;
    border: 1px solid #b3b3b3;
    display: flex;
    margin-left: 20%;
    width: 80%;

    .ac-feed-message-container {
        flex-grow: 1;
    }

    .ac-feed-message-content {
        white-space: pre-wrap;
    }
}
</style>
