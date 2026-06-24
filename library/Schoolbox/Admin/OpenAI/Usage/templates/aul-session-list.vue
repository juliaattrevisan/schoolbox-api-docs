<script setup lang="ts">
import type { AiFeature, AiSession, AiSessionId } from "./payload";
import AulModerationIndicator from "./aul-moderation-indicator.vue";

interface Props {
    features: Record<AiFeature, { title: string }>;
    sessions: AiSession[];
    selectedSessionId: AiSessionId | null;
}

const UNKNOWN_FEATURE_TEXT = "Unknown Feature";

const props = defineProps<Props>();
const emit = defineEmits(["openSessionDetails"]);

/**
 * Determine if it is possible to view a session's logs
 * @param session The session to check
 * @returns true if the session's logs can be viewed
 */
function canViewSessionLogs(session: AiSession): boolean {
    return session.logFormat !== null && session._routes.getSessionLogs !== undefined;
}

/**
 * Get the formatted createdAt date for a given AI session
 * @param session the given session
 * @returns The formatted createdAt date
 */
function formatSessionDate(session: AiSession): string {
    return window.dayjs(session.createdAt).format(window.dateDisplayFormats.dayjsDatetime);
}
</script>

<template>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Feature</th>
                <th>User</th>
                <th></th>
                <th></th>
            </tr>
        </thead>

        <tbody>
            <tr v-for="session in props.sessions" :key="session.id">
                <td>
                    {{ formatSessionDate(session) }}
                </td>
                <td>
                    {{ props.features[session.feature]?.title ?? UNKNOWN_FEATURE_TEXT }}
                </td>
                <td>
                    <span v-if="!session.createdBy">System</span>
                    <component
                        :is="session.createdBy._links.profile ? 'a' : 'span'"
                        v-else
                        :href="session.createdBy._links.profile ?? undefined"
                        :title="session.createdBy._links.profile ? 'View Profile' : undefined"
                    >
                        {{ session.createdBy.fullName }}
                    </component>
                </td>
                <td>{{ session.hasError ? "Error" : "" }}</td>
                <td :class="`actions-small-${session.moderationStatus && canViewSessionLogs(session) ? 2 : 1}`">
                    <nav v-if="session.moderationStatus || canViewSessionLogs(session)">
                        <!--
                        Although this does handle a non-viewable session which has a moderation status,
                        it is not actually possible to end up in that state normally, since only sessions with
                        a logFormat (and therefore viewable) can have a moderation status set.
                        -->
                        <aul-moderation-indicator v-if="session.moderationStatus" :status="session.moderationStatus" />
                        <button
                            v-if="canViewSessionLogs(session)"
                            type="button"
                            class="sbx-icon-only-button f-no-margins"
                            title="View session details"
                            aria-label="View session details"
                            :disabled="
                                props.selectedSessionId !== null &&
                                session.id.toString() === props.selectedSessionId?.toString()
                            "
                            @click="() => emit('openSessionDetails', session)"
                        >
                            <sbx-icon name="view" />
                        </button>
                    </nav>
                </td>
            </tr>
        </tbody>
    </table>
</template>
