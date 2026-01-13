<script lang="ts" setup>
import FileDrop from "@js/resource/file-drop.vue";
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";
import type { AgentChatFeedMessage, AgentChatMessageType } from "./agentChatTypes";

interface Props {
    message: AgentChatFeedMessage<AgentChatMessageType.REQUEST>;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="ac-feed-request">
        <div class="ac-feed-message-container l-pad-s">
            <file-drop
                v-if="props.message.meta.file"
                :id="`ig-message-file-drop-${props.message.entryKey}`"
                :current-file="props.message.meta.file"
                disabled
            />
            <p class="ac-feed-message-content l-mar--0">{{ props.message.data }}</p>
        </div>
        <div v-if="props.message.meta.user" class="ac-feed-message-portrait l-mar-s--left">
            <user-photo :user="props.message.meta.user" render="xsmall" :description="null" rounded-corners />
        </div>
    </div>
</template>

<style scoped lang="scss">
.ac-feed-request {
    align-items: flex-start;
    display: flex;
    justify-content: flex-end;
    margin-left: 20%;
    width: 80%;

    .ac-feed-message-container {
        background-color: #c7dbff;
        flex-grow: 1;
    }

    .ac-feed-message-content {
        white-space: pre-wrap;
    }

    .ac-feed-message-portrait {
        align-self: end;
    }
}
</style>
