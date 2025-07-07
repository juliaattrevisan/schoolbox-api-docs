<script setup lang="ts">
/**
 * Pastoral Report Tabs Component
 */
<<<<<<< HEAD
import MediaArticle from "@schoolboxVue/components/media-article.vue";
import type { PastoralReportTab, SeverityColors, PastoralRecord } from "../js/types/pastoralReportTypes";
=======
import type { PastoralReportTab, SeverityColors, LocaleConfig, PastoralRecord } from "../js/types/pastoralReportTypes";
import PrTable from "./pr-table.vue";
>>>>>>> 38d2af497bc (Add in pr-table, disabling rest for testing for now [SA-19672])

const props = defineProps<{
    tabs: PastoralReportTab[];
    results: PastoralRecord[];
    showSeverity?: boolean;
    severityColors: SeverityColors;
    pastoralPointEnabled: boolean | number;
    pastoralActionEnable: boolean | number;
    localePastoral: string;
}>();

const selectedTab = window.Vue.ref(props.tabs[0]?.type ?? "");

const routeLookup = (route: string, params?: Record<string, string | number>): string => {
    const routes: Record<string, string> = {
        "pastoral.getStudentRecord": "/pastoral/student/{studentId}/record/{recordId}",
        "pastoralReportBuilder.getReportTags": "/pastoral/report/results/tag/{tagId}",
        "pastoralReportBuilder.getIndex": "/pastoral/report",
    };

    let url = routes[route] || "#";

    if (params) {
        Object.keys(params).forEach((key) => {
            url = url.replace(`{${key}}`, String(params[key]));
        });
    }

    return url;
};

const profile = {
    link: (user: { id: number }): string => `/search/user/${user.id}`,
    imageSquare64: (user: { id: number; fullName: string }): string =>
        `<img src="/portrait.php?id=${user.id}&size=square64" alt="${user.fullName}" />`,
    imageSquare32: (user: { id: number; fullName: string }): string =>
        `<img src="/portrait.php?id=${user.id}&size=square32" alt="${user.fullName}" />`,
};

/**
 * Formats a date string using Day.js
 * @param date - The date to format
 * @returns - The formatted date
 */
function getDateFormatted(date: string): string {
    return window.dayjs(date).format(window.dateDisplayFormats.dayjsDatetime);
}

const isTemporaryTesting = window.Vue.ref(true); // Temporary flag for testing purposes
</script>

<template>
    <pr-table v-if="isTemporaryTesting" />
    <template v-else>
        <dl v-if="props.tabs.length > 1" class="tabs">
            <dd
                v-for="tab in props.tabs"
                :key="tab.type"
                :class="{ active: selectedTab === tab.type }"
                @click="selectedTab = tab.type"
            >
                <a>{{ tab.title }}</a>
            </dd>
        </dl>
        <div v-if="selectedTab === 'list'" class="row">
            <div v-if="props.results.length > 0" class="small-12 island">
                <section v-for="record in props.results" :key="record.id">
                    <ul class="information-list threaded">
                        <li :class="{ faded: record.isArchived }">
                            <div :id="`record${record.id}`" class="small-12 card wrap-down">
                                <a :href="profile.link(record.student)">
                                    <img
                                        :src="`/portrait.php?id=${record.student.id}&size=square64`"
                                        :alt="record.student.fullName"
                                    />
                                </a>
                                <a
                                    :href="
                                        routeLookup('pastoral.getStudentRecord', {
                                            studentId: record.student.id,
                                            recordId: record.id,
                                        })
                                    "
                                >
                                    <h4 class="l-flex l-flex--align-center l-gap-s">
                                        {{ record.student.fullName }}: {{ record.heading }}
                                        <span
                                            v-if="
                                                props.showSeverity &&
                                                record.severity &&
                                                props.severityColors[record.severity.id]
                                            "
                                            class="circle l-mar--0"
                                            :style="`background-color:${props.severityColors[record.severity.id].back}`"
                                            :title="record.severity.name"
                                        ></span>
                                        <sbx-icon
                                            v-if="record.isArchived"
                                            name="archive"
                                            size="0.75rem"
                                            :title="`Record archived - ${record.expiresAt}`"
                                        ></sbx-icon>
                                        <sbx-label-pastoral-points
                                            v-if="props.pastoralPointEnabled && record.points"
                                            :points="record.points"
                                            :title="`${record.points} Points`"
                                        ></sbx-label-pastoral-points>
                                    </h4>
                                </a>
                                <p class="meta">
                                    {{ record.type.name }}
                                    <span v-if="record.subType.name">({{ record.subType.name }})</span>
                                    | Created by
                                    <a :href="`/search/user/${record.owner.id}`">{{ record.owner.fullName }}</a>
                                    {{ record.createdAt.short }}

                                    <span v-if="props.pastoralActionEnable && record.uncompletedActionCount > 0">
                                        | {{ record.uncompletedActionCount }} follow up action{{
                                            record.uncompletedActionCount === 1 ? "" : "s"
                                        }}
                                        remaining
                                    </span>
                                </p>
                            </div>
                            <article>
                                <media-article v-if="record.bodyPublic" :content="record.bodyPublic" />

                                <p v-if="record.tags.length > 0" class="spaced">
                                    <template v-for="tag in record.tags" :key="tag.id">
                                        <a
                                            class="neutral label hide-on-print"
                                            :href="
                                                routeLookup('pastoralReportBuilder.getReportTags', {
                                                    tagId: tag.id,
                                                })
                                            "
                                        >
                                            {{ tag.name }}
                                        </a>
                                        <b class="show-for-print"> {{ tag.name }}</b>
                                    </template>
                                </p>
                            </article>
                            <ul v-if="record.comments.length" class="information-list reply">
                                <li
                                    v-for="comment in record.comments.filter((c) => !c.private)"
                                    :key="comment.owner.id"
                                >
                                    <div class="card compact small-12">
                                        <a :href="profile.link(comment.owner)" title="View Profile">
                                            <img
                                                :src="`/portrait.php?id=${comment.owner.id}&size=square32`"
                                                :alt="comment.owner.fullName"
                                            />
                                            <p>
                                                {{ comment.owner.fullName }}
                                                <span class="pipe meta">{{ getDateFormatted(comment.createdAt) }}</span>
                                            </p>
                                        </a>
                                    </div>
                                    <media-article :content="comment.body" />
                                </li>
                            </ul>
                        </li>
                    </ul>
                </section>
            </div>
            <div v-else class="small-12 column">
                <section>
                    <div class="empty-state f-no-margins">
                        <i class="icon-pastoral"></i>
                        <p>No {{ props.localePastoral }} records matched your report criteria.</p>
                        <a :href="routeLookup('pastoralReportBuilder.getIndex')"> Select Another Report </a>
                    </div>
                </section>
            </div>
        </div>
    </template>
</template>

<style scoped></style>
