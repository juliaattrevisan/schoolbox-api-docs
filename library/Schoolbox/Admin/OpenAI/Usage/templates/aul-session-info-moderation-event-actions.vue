<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { AiModerationEvent, AiModerationEventActionResponse } from "./moderationEvent";

interface Props {
    event: AiModerationEvent;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: "updateEvent", event: AiModerationEvent): void;
}>();

/**
 * Trigger a state transition action for a moderation event and update the stored event data accordingly.
 * Whether each transition is permitted is enforced by the route check on the backend.
 * @param action - The moderation event action to trigger
 * @param waitingMessage - Toast shown while the request is in flight
 * @param successMessage - Toast shown when the transition succeeds
 * @param failureMessage - Toast shown when the transition fails
 */
async function triggerAction(
    action: keyof AiModerationEvent["_routes"],
    waitingMessage: string,
    successMessage: string,
    failureMessage: string,
): Promise<void> {
    const url = props.event._routes[action];
    if (!url) {
        throw new Error(`${action} not permitted for this event`); // shouldn't get here
    }

    const toast = window.sbProps.toastActivate(waitingMessage);
    const response = await apiRequest<AiModerationEventActionResponse>({
        method: "POST",
        url: url,
    });

    if (!response.success) {
        toast.timeout({ text: failureMessage, isSuccess: false });
        return;
    }

    toast.timeout({ text: successMessage, isSuccess: true });

    // Update the stored moderation event data
    emit("updateEvent", response.data.event);
}

/**
 * Mark the event as reviewed with no further action required.
 */
async function onDismiss(): Promise<void> {
    await triggerAction(
        "postDismissEvent",
        `Dismissing moderation event...`,
        "Moderation event dismissed",
        "Failed to dismiss moderation event",
    );
}

/**
 * Mark the event as reviewed and requiring further action.
 */
async function onConfirm(): Promise<void> {
    await triggerAction(
        "postConfirmEvent",
        "Confirming moderation event...",
        "Moderation event confirmed",
        "Failed to confirm moderation event",
    );
}

/**
 * Mark the event's requested further action as resolved.
 */
async function onResolve(): Promise<void> {
    await triggerAction(
        "postResolveEvent",
        "Marking moderation event as resolved...",
        "Moderation event resolved",
        "Failed to resolve moderation event",
    );
}
</script>

<template>
    <li v-if="props.event._routes.postDismissEvent">
        <a class="sbx-button--has-icon" @click.prevent="onDismiss">
            <sbx-icon name="close"></sbx-icon> Mark as No Action Required
        </a>
    </li>

    <li v-if="props.event._routes.postConfirmEvent">
        <a class="sbx-button--has-icon" @click.prevent="onConfirm">
            <sbx-icon name="match"></sbx-icon> Request Further Action
        </a>
    </li>

    <li v-if="props.event._routes.postResolveEvent">
        <a class="sbx-button--has-icon" @click.prevent="onResolve">
            <sbx-icon name="approve"></sbx-icon> Mark as Resolved
        </a>
    </li>
</template>
