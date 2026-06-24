<script setup lang="ts">
import { getDayjsDatetimeLong } from "@js/util/dateUtils";
import { AiModerationStatus, STATUS_LABELS } from "./moderationEvent";
import type { AiModerationEvent } from "./moderationEvent";
import { RISK_LEVEL_LABELS, CATEGORY_LABELS } from "./moderationResult";
import AulSessionInfoModerationEventActions from "./aul-session-info-moderation-event-actions.vue";

interface Props {
    event: AiModerationEvent;
}

const props = defineProps<Props>();

const componentId = window.Vue.useId();

const timestamp = window.Vue.computed(() => {
    const date = window.dayjs(props.event.createdAt);
    const time = date.format(window.dateDisplayFormats.dayjsTime);
    return `${date.format(window.dateDisplayFormats.dayjsDateShort)} ${time}`;
});

const STATUS_LABEL_CLASSES: Record<AiModerationStatus, string> = {
    [AiModerationStatus.NO_BEHAVIOUR_FLAGGED]: "neutral",
    [AiModerationStatus.REQUIRES_ATTENTION]: "warning",
    [AiModerationStatus.NO_ACTION_REQUIRED]: "neutral",
    [AiModerationStatus.FURTHER_ACTION_REQUIRED]: "warning",
    [AiModerationStatus.FURTHER_ACTION_TAKEN]: "highlight",
};
</script>

<template>
    <li class="actions-small-2">
        <div class="list-item">
            <div class="card small-12">
                <h3>
                    {{ RISK_LEVEL_LABELS[props.event.result.overall_risk_level] }} Risk
                    <span class="label right l-mar--0" :class="STATUS_LABEL_CLASSES[props.event.status]">
                        {{ STATUS_LABELS[props.event.status] }}
                    </span>
                </h3>
                <p class="meta">
                    <time :datetime="props.event.createdAt" :title="getDayjsDatetimeLong(props.event.createdAt)">
                        {{ timestamp }}
                    </time>
                </p>
            </div>
        </div>

        <nav>
            <!-- TODO: expand/collapse -->
            <a class="sbx-icon-only-button">
                <sbx-icon name="up-arrow"></sbx-icon>
            </a>
            <sbx-pop-out :content-id="`aul-session-info-moderation-event-${componentId}`"></sbx-pop-out>
        </nav>
        <ul :id="`aul-session-info-moderation-event-${componentId}`" class="f-dropdown" data-dropdown-content>
            <aul-session-info-moderation-event-actions :event="props.event" />
        </ul>

        <article>{{ props.event.result.summary }}</article>
    </li>

    <li v-for="category in props.event.result.categories" :key="category.category" class="child">
        <div class="content">
            <h3>{{ CATEGORY_LABELS[category.category] }}</h3>
            <p v-for="(evidence, evidenceIdx) in category.evidence" :key="evidenceIdx" class="meta">
                "{{ evidence.quote }}"
            </p>
        </div>
    </li>
</template>
