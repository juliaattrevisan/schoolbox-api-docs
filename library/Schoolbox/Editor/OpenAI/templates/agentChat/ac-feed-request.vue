<script lang="ts" setup>
import FileDrop from "@js/resource/file-drop.vue";
import { getDayjsDatetimeLong } from "@js/util/dateUtils";
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";
import type { AgentChatFeedMessage, AgentChatMessageType } from "./agentChatTypes";
import { formatTimestamp } from "./agentChatUtils";

interface Props {
    message: AgentChatFeedMessage<AgentChatMessageType.REQUEST>;
    /** If timestamps should be shown for each message */
    showTimestamps?: boolean;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="ac-feed-request" :data-test="`ac-feed-request-list-item-${props.message.entryKey}`">
        <div class="ac-feed-message-container l-pad-s">
            <file-drop
                v-if="props.message.meta.file"
                :id="`ig-message-file-drop-${props.message.entryKey}`"
                :current-file="props.message.meta.file"
                disabled
            />
            <p class="ac-feed-message-content l-mar--0" data-test="ac-feed-message-content">{{ props.message.data }}</p>
            <p v-if="props.showTimestamps" class="meta l-mar--0">
                <time :datetime="props.message.createdAt" :title="getDayjsDatetimeLong(props.message.createdAt)">
                    {{ formatTimestamp(props.message) }}
                </time>
            </p>
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
        min-width: 20%;
    }

    .ac-feed-message-content {
        white-space: pre-wrap;
    }

    .ac-feed-message-portrait {
        align-self: end;
    }
}
</style>
