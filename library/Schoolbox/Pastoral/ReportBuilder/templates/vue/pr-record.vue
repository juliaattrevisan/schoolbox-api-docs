<script setup lang="ts">
/**
 * To display a single pastoral record in a detailed view
 */

import MediaArticle from "@schoolboxVue/components/media-article.vue";
import { getDayjsDatetime } from "@js/util/dateUtils";
import type { PastoralRecord } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import type { Config, Routes, SeverityColors } from "../js/types/pastoralReportTypes";

const props = defineProps<{
    record: PastoralRecord;
    config: Config;
    routes: Routes;
    localePastoral: string;
    showSeverity: boolean;
    severityColors: SeverityColors;
}>();
</script>

<template>
    <li :class="{ faded: props.record.isArchived }">
        <div :id="`record${props.record.id}`" class="small-12 card wrap-down">
            <a v-if="props.record.student._links?.profile" :href="props.record.student._links.profile">
                <img :src="props.record.student._links?.avatar" :alt="props.record.student.fullName" />
            </a>
            <img v-else :src="props.record.student._links?.avatar" :alt="props.record.student.fullName" />
            <a
                :href="
                    props.routes.getStudentRecord
                        .replace('{studentId}', props.record.student.id.toString())
                        .replace('{recordId}', props.record.id.toString())
                "
            >
                <h4 class="l-flex l-flex--align-center l-gap-s">
                    {{ props.record.student.fullName }}: {{ props.record.heading }}
                    <span
                        v-if="
                            props.showSeverity &&
                            props.record.severity &&
                            props.severityColors[props.record.severity.id]
                        "
                        class="circle l-mar--0"
                        :style="`background-color:${props.severityColors[props.record.severity.id].back}`"
                        :title="props.record.severity.name"
                    ></span>
                    <sbx-icon
                        v-if="props.record.isArchived && props.record.expiresAt"
                        name="archive"
                        size="0.75rem"
                        :title="`Record archived - ${props.record.expiresAt}`"
                    ></sbx-icon>
                    <sbx-label-pastoral-points
                        v-if="props.config.pastoralPointEnabled && props.record.points"
                        :points="props.record.points"
                        :title="`${props.record.points} Points`"
                    ></sbx-label-pastoral-points>
                </h4>
            </a>
            <p class="meta">
                {{ props.record.type.name }}
                <span v-if="props.record.subType && props.record.subType.name">({{ props.record.subType.name }})</span>
                | Created by
                <a :href="`/search/user/${props.record.owner.id}`">{{ props.record.owner.fullName }}</a>
                {{ props.record.createdAt.short }}
                <span v-if="props.config.pastoralActionEnable && props.record.uncompletedActionCount > 0">
                    | {{ props.record.uncompletedActionCount }} follow up action{{
                        props.record.uncompletedActionCount === 1 ? "" : "s"
                    }}
                    remaining
                </span>
            </p>
        </div>
        <article>
            <media-article v-if="props.record.bodyPublic" :content="props.record.bodyPublic" />
            <p v-if="props.record.tags.length > 0" class="spaced">
                <template v-for="tag in props.record.tags" :key="tag.id">
                    <a
                        class="neutral label hide-on-print"
                        :href="props.routes.getReportTags.replace('{tagId}', tag.id.toString())"
                    >
                        {{ tag.name }}
                    </a>
                    <b class="show-for-print"> {{ tag.name }}</b>
                </template>
            </p>
        </article>
        <ul v-if="props.record.comments.length" class="information-list reply">
            <li v-for="comment in props.record.comments.filter((c) => !c.private)" :key="comment.owner.id">
                <div class="card compact small-12">
                    <component
                        :is="comment.owner._links?.profile ? 'a' : 'div'"
                        v-bind="
                            comment.owner._links?.profile
                                ? { href: comment.owner._links.profile, title: 'View Profile' }
                                : {}
                        "
                    >
                        <img
                            :src="`/portrait.php?id=${comment.owner.id}&size=square32`"
                            :alt="comment.owner.fullName"
                        />
                        <p>
                            {{ comment.owner.fullName }}
                            <span class="pipe meta">{{ getDayjsDatetime(comment.createdAt) }}</span>
                        </p>
                    </component>
                </div>
                <media-article :content="comment.body" />
            </li>
        </ul>
    </li>
</template>

<style scoped></style>
