<script>
    import articleListMixin from './article-list-mixin';

    export default {
        name: "layout-card",
        mixins: [articleListMixin],
        props: [
            'query-string-referrer',
            'query-string-topic',
            'change-tab-on-meta',
            'referrer',
            'compact-mode',
            'fallback-image',
            'get-index-route',
            'config-save-for-later',
            'locale-news-plural',
            'saved-article-ids',
            'articles-loading',
            'current-articles'
        ],
        components: {},
        data() {
            return {
                'fallbackImage': data.fallbackImage
            }
        }
    }
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
            <div class="v-card card wrap-down" v-bind:class="{sticky: article.sticky}" v-for="article in currentArticles">
                <template v-if="article.featureImage">
                    <div class="card-feature-image">
                        <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer">
                            <img :src="article.featureImage._links.image.href + '&size=vcardImageLarge'">
                        </a>
                    </div>
                </template>
                <template v-else>
                    <div v-bind:class="{hide: fallbackImage === 'none'}">
                        <div class="card-feature-avatar">
                            <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer">
                                <img :src="article.author._links.avatar.href + '&size=square64'">
                            </a>
                        </div>
                    </div>
                </template>
                <div class="card-content">
                    <h3><a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer">{{ article.title }}</a></h3>

                    <p class="meta">
                        <span>
                            <template v-if="article.sticky"><span class="icon-pin"></span>Pinned by</template>
                            <template v-else>By</template>
                            <a v-if="article.author._links && article.author._links.profile" :href="article.author._links.profile.href">{{ article.author.fullname }}</a>
                            <template v-else>{{ article.author.fullname }}</template>
                        </span>
                        <span> &mdash; <time :title="article.publishAt.long">{{ article.publishAt.relativeTime }}</time></span>
                        <span v-if="article.topics.length">
                            in
                            <template v-for="(topic, index) in article.topics">
                                <template v-if="index">, </template>
                                <a v-if="changeTabOnMeta" @click="onTabChange(topic.slug)">{{ topic.name }}</a>
                                <a v-else :href="getIndexRoute + '?' + queryStringTopic + '=' + topic.slug">{{ topic.name }}</a>
                            </template>
                        </span>
                        <span v-if="article.metadata && article.metadata.commentCount > 0">
                            | <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer + '#discussion'">{{ article.metadata.commentCount | formatCommentCountMeta }}</a>
                        </span>
                    </p>

                    <article v-if="!compactMode" >
                        <div v-html="article.blurb"></div>
                    </article>
                </div>
                <div class="card-actions">
                    <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer" v-html="article.readmore.string"></a>
                    <div class="card-spacer"></div>
                    <a v-if="article._links.update.auth" :href="article._links.update.href" class="icon-modify no-text" :title="'Modify this ' + localeNewsPlural"></a>
                    <a v-if="configSaveForLater" @click="saveForLater(article)"
                       :class="[ savedArticleIds.indexOf(article.id) >= 0 ? 'icon-favourite': 'icon-favourite-hollow' ]"
                       title="Save for later"></a>
                </div>
            </div>
        </div>
    </div>
</template>
