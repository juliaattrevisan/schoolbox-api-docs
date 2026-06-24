<script setup lang="ts" generic="TFeature extends AiFeature, TLogFormat extends AiLogFormat">
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";
import RelativeTime from "@schoolboxVue/components/relativeTime/relative-time.vue";
import { AiLogFormat } from "./payload";
import type { AiFeature, AiSession, AiSessionLogApiResponse } from "./payload";
import AulAgentChat from "./aul-agent-chat.vue";

interface Props {
    session: AiSession<TLogFormat, TFeature>;
    sessionInfo: AiSessionLogApiResponse<TLogFormat> | null;
    features: Record<AiFeature, { title: string }>;
}

const UNKNOWN_FEATURE_TEXT = "Unknown Feature";

const props = defineProps<Props>();

// TODO: properly support sessions with multiple log formats, for now just filter out invalid logs
const filteredLogs = window.Vue.computed(() => {
    if (props.sessionInfo === null) {
        return [];
    }

    return props.sessionInfo.logs.filter((entry) => entry.logFormat !== null && entry.data !== null);
});

/**
 * Print the currently selected AI session log.
 */
function printSelectedSession(): void {
    window.print();
}
</script>

<template>
    <section>
        <div class="card small-12 actions-small-1">
            <div class="list-item">
                <span>
                    <user-photo
                        v-if="props.session.createdBy"
                        :user-id="props.session.createdBy.id || 0"
                        render="small"
                    />
                    <h3>
                        {{ props.features[props.session.feature]?.title ?? UNKNOWN_FEATURE_TEXT }}
                    </h3>
                </span>
                <p class="meta">
                    <span v-if="!props.session.createdBy"> Created </span>
                    <template v-else>
                        Created by
                        <component
                            :is="props.session.createdBy._links.profile ? 'a' : 'span'"
                            :href="props.session.createdBy._links.profile ?? undefined"
                        >
                            {{ props.session.createdBy.fullName }}
                        </component>
                    </template>
                    <relative-time :datetime="props.session.createdAt" full-diff></relative-time>
                </p>
            </div>
            <nav class="no-print">
                <sbx-pop-out content-id="ai-usage-log-selected-session-actions"></sbx-pop-out>
            </nav>
            <ul id="ai-usage-log-selected-session-actions" class="f-dropdown no-print" data-dropdown-content>
                <li>
                    <a
                        href="#"
                        class="sbx-button--has-icon"
                        data-test="ai-usage-log-print-session"
                        @click.prevent="printSelectedSession"
                    >
                        <sbx-icon name="print"></sbx-icon>
                        Print / Save as PDF
                    </a>
                </li>
            </ul>
        </div>
        <div class="clearfix"></div>

        <div v-if="props.sessionInfo === null" class="loader"></div>
        <div v-else-if="filteredLogs.length === 0" class="empty-state">
            <sbx-icon name="hidden" size="xl" />
            <p>
                The {{ props.features[props.session.feature].title }} feature does not support previews for this
                session.
            </p>
        </div>
        <div v-else class="l-mar-m">
            <aul-agent-chat
                v-if="
                    props.session.logFormat === AiLogFormat.AGENT_CHAT ||
                    props.session.logFormat === AiLogFormat.GUARDRAIL
                "
                :feature="props.session.feature"
                :session-logs="filteredLogs"
                :moderation-events="props.sessionInfo.moderationEvents"
            />
        </div>
    </section>
</template>

<style scoped lang="scss">
/* styles for printing (scoped) */
@media print {
    .no-print {
        display: none !important;
    }
}
</style>
