<script setup lang="ts">
/**
 * Pastoral Report Tabs Component
 */
import type {
    PastoralReportTab,
    SeverityColors,
    PastoralConfig,
    LocaleConfig,
    PastoralRecord,
} from "../js/types/pastoralReportTypes";

const props = defineProps<{
    tabs: PastoralReportTab[];
    results: PastoralRecord[];
    showSeverity?: boolean;
    severityColors: SeverityColors;
    config: PastoralConfig;
    locale: LocaleConfig;
}>();

const selectedTab = window.Vue.ref(props.tabs[0]?.type ?? "");

const routeLookup = (route: string, params?: Record<string, any>) => {
    const routes: Record<string, string> = {
        "pastoral.getStudentRecord": "/pastoral/student/{studentId}/record/{recordId}",
        "pastoralReportBuilder.getReportTags": "/pastoral/report/tags/{tagId}",
        "pastoralReportBuilder.getIndex": "/pastoral/report",
    };

    let url = routes[route] || "#";

    if (params) {
        Object.keys(params).forEach((key) => {
            url = url.replace(`{${key}}`, params[key]);
        });
    }

    return url;
};

const profile = {
    link: (user: any) => `/search/user/${user.id}`,
    imageSquare64: (user: any) => `<img src="/portrait.php?id=${user.id}&size=square64" alt="${user.fullName}" />`,
    imageSquare32: (user: any) => `<img src="/portrait.php?id=${user.id}&size=square32" alt="${user.fullName}" />`,
};
</script>

<template>
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
                                    <!--<sbx-label-pastoral-points
                                    v-if="props.config.pastoral_point_enabled && record.points"
                                    :points="record.points"
                                ></sbx-label-pastoral-points> -->
                                </h4>
                            </a>
                            <!--<p class="meta">
                            {{ record.type.name }}
                            <span v-if="record.subtype.name">({{ record.subtype.name }})</span>
                            | Created by
                            <a :href="`/search/user/${record.owner.id}`">{{ record.owner.fullName }}</a>
                            {{ record.createdAt.short }}

                            <span v-if="props.config.pastoral_action_enable && record.uncompletedActionCount > 0">
                                | {{ record.uncompletedActionCount }} follow up action{{
                                    record.uncompletedActionCount === 1 ? "" : "s"
                                }}
                                remaining
                            </span>
                        </p>-->
                            <article>
                                <div v-if="record.bodyPublic" v-html="record.bodyPublic"></div>

                                <p v-if="record.tags.length > 0" class="spaced">
                                    <template v-for="tag in record.tags" :key="tag.id">
                                        <a
                                            class="neutral label hide-on-print"
                                            :href="
                                                routeLookup('pastoralReportBuilder.getReportTags', { tagId: tag.id })
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
                                        <a
                                            :href="profile.link(comment.owner)"
                                            title="View Profile"
                                            v-html="profile.imageSquare32(comment.owner)"
                                        ></a>
                                        <p>
                                            {{ comment.owner.fullName }}
                                            <span class="pipe meta">{{ comment.createdAt.short }}</span>
                                        </p>
                                    </div>
                                    comment
                                    <article v-html="comment.body"></article>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </section>

            <div v-if="props.results.length === 0" class="small-12 column">
                <div class="empty-state">
                    <i class="icon-pastoral"></i>
                    <p>No {{ props.locale.pastoral }} records matched your report criteria.</p>
                </div>

                <a class="button submit" :href="routeLookup('pastoralReportBuilder.getIndex')">
                    Select Another Report
                </a>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
