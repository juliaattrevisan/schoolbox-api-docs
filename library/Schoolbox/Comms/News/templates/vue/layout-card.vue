<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import articleListMixin from "./article-list-mixin";
import MediaArticle from "@schoolboxVue/components/media-article";

export default {
    name: "layout-card",
    mixins: [articleListMixin],
    props: [
        "query-string-referrer",
        "query-string-topic",
        "change-tab-on-meta",
        "referrer",
        "compact-mode",
        "fallback-image",
        "get-index-route",
        "config-save-for-later",
        "locale-news-plural",
        "saved-article-ids",
        "articles-loading",
        "current-articles",
    ],
    components: { MediaArticle },
    data() {
        return {};
    },
};
</script>

<template>
    <div>
        <div class="v-card-list">
            <div class="v-card card skeleton" v-if="articlesLoading" v-for="n in 3">
                <div class="img"></div>
                <h3></h3>
                <p class="meta"></p>
            </div>
        </div>

        <div class="v-card-list">
            <div
                class="v-card card wrap-down"
                v-bind:class="{ sticky: article.sticky, unread: !article.viewedAt, read: article.viewedAt }"
                v-for="article in currentArticles"
            >
                <template v-if="article.featureImage">
                    <div class="card-feature-image">
                        <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer">
                            <img :src="article.featureImage._links.image.href + '&size=constrain300'" />
                        </a>
                    </div>
                </template>
                <template v-else-if="fallbackImage === 'author'">
                    <div class="card-feature-avatar">
                        <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer">
                            <img :src="article.author._links.avatar.href + '&size=square64'" />
                        </a>
                    </div>
                </template>
                <template v-else-if="fallbackImage === 'school-logo'">
                    <div class="card-feature-image">
                        <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer">
                            <img :src="'/images/logo.php?logo=skin_logo_large&size=normal'" />
                        </a>
                    </div>
                </template>
                <div class="card-content">
                    <h3>
                        <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer">{{
                            article.title
                        }}</a>
                    </h3>

                    <p class="meta">
                        <span>
                            <template v-if="article.sticky"><span class="icon-pin"></span>Pinned by&nbsp;</template>
                            <template v-else>By&nbsp;</template>
                            <a
                                v-if="article.author._links && article.author._links.profile"
                                :href="article.author._links.profile.href"
                                >{{ article.author.fullname }}</a
                            >
                            <template v-else>{{ article.author.fullname }}</template>
                        </span>
                        <span>
                            &mdash;
                            <time :title="article.publishAt.long">{{ article.publishAt.relativeTime }}</time></span
                        >
                        <span v-if="article.topics.length">
                            in
                            <template v-for="(topic, index) in article.topics">
                                <template v-if="index">, </template>
                                <!-- Tab change event is triggered on parent article-list, as that has the full list of articles -->
                                <a
                                    v-if="changeTabOnMeta"
                                    @click="$parent.onTabChange(topic.slug, false, false, true)"
                                    >{{ topic.name }}</a
                                >
                                <a v-else :href="getIndexRoute + '?' + queryStringTopic + '=' + topic.slug">{{
                                    topic.name
                                }}</a>
                            </template>
                        </span>
                        <span v-if="article.metadata && article.metadata.commentCount > 0">
                            |
                            <a
                                :href="
                                    article._links.self.href +
                                    '?' +
                                    queryStringReferrer +
                                    '=' +
                                    referrer +
                                    '#discussion'
                                "
                                >{{ formatCommentCountMeta(article.metadata.commentCount) }}</a
                            >
                        </span>

                        <!-- Attachment Count -->
                        <template v-if="article.attachments">
                            <span>
                                |
                                <a
                                    :href="
                                        article._links.self.href +
                                        '?' +
                                        queryStringReferrer +
                                        '=' +
                                        referrer +
                                        '#article-attachments'
                                    "
                                    >{{ article.attachments }} attachments</a
                                >
                            </span>
                        </template>
                    </p>

                    <media-article v-if="!isCompactMode" :content="article.blurb"></media-article>
                </div>
                <div class="card-actions">
                    <template v-if="article.readMore.available">
                        <a
                            :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer"
                            class="article-read-more"
                            >{{ article.readMore.string }}</a
                        >
                    </template>

                    <div class="card-spacer"></div>
                    <a
                        v-if="article._links.update.auth"
                        :href="article._links.update.href"
                        class="icon-modify no-text"
                        :title="'Modify this ' + localeNewsPlural"
                    ></a>
                    <a
                        v-if="configSaveForLater"
                        @click="saveForLater(article)"
                        :class="[savedArticleIds.indexOf(article.id) >= 0 ? 'icon-favourite' : 'icon-favourite-hollow']"
                        title="Save for later"
                    ></a>
                </div>
            </div>
        </div>
    </div>
</template>
