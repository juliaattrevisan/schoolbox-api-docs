<script lang="ts" setup>
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import type { ImageRequest } from "./imageGeneratorTypes";

interface Props {
    message: ImageRequest;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="ig-feed-request snow">
        <p class="l-mar-s ig-feed-message-content">{{ props.message.payload.userPrompt }}</p>
        <user-photo :user="props.message.user" render="xsmall" />
    </div>
</template>

<style scoped lang="scss">
.ig-feed-request {
    align-items: flex-start;
    border: 1px solid #b3b3b3;
    display: flex;
    margin-left: 20%;
    width: 80%;

    p {
        flex-grow: 1;
    }

    .ig-feed-message-content {
        white-space: pre-wrap;
    }
}
</style>
