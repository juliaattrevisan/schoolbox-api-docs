<script>
/* eslint-disable @eslint-community/eslint-comments/disable-enable-pair -- see below */
/* eslint-disable @eslint-community/eslint-comments/no-unlimited-disable -- see below */
/* eslint-disable -- Ignore this file for ESLint checks for now (SA-18376) */
/* eslint-enable vue/no-v-html */
import articleListMixin from "./article-list-mixin";
import MediaArticle from "@schoolboxVue/components/media-article";
import RelativeTime from "@schoolboxVue/components/relativeTime/relative-time.vue";

export default {
    name: "layout-list",
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
    components: { RelativeTime, MediaArticle },
    data() {
        return {};
    },
};
</script>

<template>
    <ul class="information-list">
        <li v-if="articlesLoading" v-for="n in 3">
            <div class="small-12 card skeleton news">
                <div class="img"></div>
                <h3></h3>
                <p class="meta"></p>
            </div>
        </li>

        <li
            class="actions-small-1"
            v-bind:class="{ sticky: article.sticky, unread: !article.viewedAt, read: article.viewedAt }"
            v-for="article in currentArticles"
        >
            <div class="list-item">
                <div class="small-12 card wrap-down">
                    <template v-if="article.featureImage">
                        <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer">
                            <img
                                v-if="isCompactMode"
                                :src="article.featureImage._links.image.href + '&size=square64'"
                                class="thumbnail"
                                alt="feature image"
                            />
                            <img
                                v-else
                                :srcset="
                                    article.featureImage._links.image.href +
                                    '&size=constrain200, ' +
                                    article.featureImage._links.image.href +
                                    '&size=constrain300 1.5x'
                                "
                                :src="article.featureImage._links.image.href + '&size=constrain200'"
                                alt="feature image"
                            />
                        </a>
                    </template>
                    <template v-else-if="fallbackImage === 'author'">
                        <div v-if="!isCompactMode" class="card-feature-avatar">
                            <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer">
                                <img
                                    :src="article.author._links.avatar.href + '&size=square64'"
                                    alt="author portrait"
                                />
                            </a>
                        </div>
                        <a
                            v-if="isCompactMode"
                            :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer"
                        >
                            <img
                                :src="article.author._links.avatar.href + '&size=square64'"
                                class="thumbnail"
                                alt="author portrait"
                            />
                        </a>
                    </template>
                    <template v-else-if="fallbackImage === 'school-logo'">
                        <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer">
                            <img
                                v-if="isCompactMode"
                                :src="'/images/logo.php?logo=skin_logo_large&size=favicon'"
                                class="thumbnail"
                                alt="school logo"
                            />
                            <img v-else :src="'/images/logo.php?logo=skin_logo_large&size=normal'" alt="school logo" />
                        </a>
                    </template>
                    <h3>
                        <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer">{{
                            article.title
                        }}</a>
                    </h3>

                    <p class="meta">
                        <span>
                            <template v-if="article.sticky"
                                ><sbx-icon class="icon-pin" name="pin"></sbx-icon>Pinned by
                            </template>
                            <template v-else>By </template>
                            <a
                                v-if="article.author._links && article.author._links.profile"
                                :href="article.author._links.profile.href"
                                >{{ article.author.fullname }}</a
                            >
                            <template v-else>{{ article.author.fullname }}</template>
                        </span>
                        <span>
                            &mdash;
                            <relative-time :datetime="article.publishAt" full-diff></relative-time>
                        </span>
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

                    <article v-if="!isCompactMode">
                        <media-article tag="div" :content="article.blurb"></media-article>

                        <template v-if="article.readMore.available">
                            <div class="article-read-more">
                                <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer">{{
                                    article.readMore.string
                                }}</a>
                            </div>
                        </template>
                    </article>
                </div>
            </div>

            <nav>
                <a
                    v-if="article._links.update.auth"
                    :href="article._links.update.href"
                    class="sbx-icon-only-button"
                    :title="'Modify this ' + localeNewsPlural"
                    :aria-label="'Modify this ' + localeNewsPlural"
                    ><sbx-icon name="modify"
                /></a>
                <a
                    v-if="configSaveForLater"
                    @click="saveForLater(article)"
                    class="sbx-icon-only-button"
                    title="Save for later"
                    aria-label="Save for later"
                >
                    <sbx-icon :name="savedArticleIds.indexOf(article.id) >= 0 ? 'favourite' : 'favourite-hollow'" />
                </a>
            </nav>
        </li>
    </ul>
</template>
