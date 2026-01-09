<script lang="ts" setup>
import FileDrop from "@js/resource/file-drop.vue";
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";
import type { AgentChatRequest } from "./agentChatTypes";

interface Props {
    message: AgentChatRequest;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="ac-feed-request">
        <div class="ac-feed-message-container snow l-pad-s" :class="{ 'l-mar-s--right': props.message.user }">
            <file-drop
                v-if="props.message.file"
                :id="`ig-message-file-drop-${props.message.entryKey}`"
                :current-file="props.message.file"
                disabled
            />
            <p class="ac-feed-message-content">{{ props.message.payload.userPrompt }}</p>
        </div>
        <user-photo
            v-if="props.message.user"
            :user="props.message.user"
            render="xsmall"
            :description="null"
            rounded-corners
        />
    </div>
</template>

<style scoped lang="scss">
.ac-feed-request {
    align-items: flex-start;
    display: flex;
    margin-left: 20%;
    width: 80%;

    .ac-feed-message-container {
        border: 1px solid #b3b3b3;
        flex-grow: 1;
    }

    .ac-feed-message-content {
        white-space: pre-wrap;
    }
}
</style>
