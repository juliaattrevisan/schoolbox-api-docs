<script setup lang="ts">
import type { AiModerationEvent } from "./moderationEvent";

interface Props {
    event: AiModerationEvent;
}

const props = defineProps<Props>();
</script>

<template>
    <li v-if="props.event._routes.postDismissEvent">
        <a class="sbx-button--has-icon" @click.prevent="">
            <sbx-icon name="close"></sbx-icon> Mark as No Action Required
        </a>
    </li>

    <li v-if="props.event._routes.postConfirmEvent">
        <a class="sbx-button--has-icon" @click.prevent="">
            <sbx-icon name="match"></sbx-icon> Request Further Action
        </a>
    </li>

    <li v-if="props.event._routes.postResolveEvent">
        <a class="sbx-button--has-icon" @click.prevent=""> <sbx-icon name="approve"></sbx-icon> Mark as Resolved </a>
    </li>
</template>
