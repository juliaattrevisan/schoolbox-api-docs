<script>
import MediaArticle   from '@vue/components/media-article';
import Storage        from '@vue/components/storage';
import RelativeTime   from '@schoolboxLearningMomentEvidence/relative-time';
import CommentThread  from './commentThread';
import cdnPrefixMixin from '@vue/components/cdn-prefix-mixin';

export default {
    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "evidence-detail",

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [cdnPrefixMixin],

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: [
        'evidence',
        'returnUrl',
        'canComment',
        'siteVersion',
    ],

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {
        MediaArticle,
        Storage,
        RelativeTime,
        CommentThread,
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {
        }
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted: function () {
        debugger;
    },

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     */
    computed: {
    },

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {

        /**
         * Decide whether to show the tot menu - only provide when it has at least one
         * accessible route
         */
        hasRoutes: function() {

            /* For now there are only two options, so tot is fairly
             * straightforward to calculate this way
             */
            return this.evidence._links.modify || this.evidence._links.delete;
        },

        /**
         * Create a modify form URL for this evidence, consisting of the
         * evidence's existing modify form link and a return URL query string.
         *
         * @return {String}
         */
        createModifyFormUrl() {
            if (this.evidence._links.modifyForm) {
                let baseModifyLink = this.evidence._links.modifyForm;
                if (this.returnUrl.name && this.returnUrl.current) {
                    baseModifyLink = baseModifyLink
                            + '?' + encodeURIComponent(this.returnUrl.name) + '=' + encodeURIComponent(this.returnUrl.current);
                }
                return baseModifyLink;
            }
            return '';
        },
    }
}
</script>
<template>
    <div class="evidence-detail">
        <template v-if="this.hasRoutes()">
            <div class="row">
                <div class="small-12 columns">
                    <div class="actions-small-1">
                        <nav>
                            <template v-if="this.evidence._links.delete">
                                <a class="icon-more" title="Actions" data-dropdown="header-options"></a>
                            </template>
                        </nav>
                        <ul id="header-options" class="f-dropdown" data-dropdown-content>
                            <li v-if="this.evidence._links.modifyForm">
                                <a class="icon-modify" :href="this.createModifyFormUrl()">Modify</a>
                            </li>
                            <template v-if="this.evidence._links.delete">
                                <li><a class="icon-delete" :href="this.evidence._links.delete" data-delete>Delete</a></li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
        </template>
        <div class="news-post row">
            <div class="small-12 island">
                <section>
                    <div class="row">
                        <div class="small-12 island">
                            <section class="banner content">
                                <!-- Is there an attachment? Display it appropriately -->
                                <article v-if="this.evidence.filename && this.evidence.storage">
                                    <storage :storage="this.evidence.storage" :filename="this.evidence.filename"></storage>
                                </article>
                            </section>
                            <section class="detail content">
                                <p class="meta">By
                                    <a :href="evidence.creator._links.profile" title="View Profile">
                                        {{ this.evidence.creator.fullName }}
                                    </a>
                                    &mdash;
                                    <relative-time :datetime="this.evidence.createdAt"></relative-time>
                                    <!-- Display curriculum codes and tags -->
                                    <br>
                                    <template v-if="this.evidence.curriculumNodes.length > 0 || this.evidence.tags.length > 0">
                                      Tags:
                                      <template v-for="(curriculumNode, index) in this.evidence.curriculumNodes">
                                        <template v-if="index !== 0">, </template><span
                                          :title="curriculumNode.name">{{ curriculumNode.code }}</span>
                                      </template><template v-if="this.evidence.curriculumNodes.length > 0 && this.evidence.tags.length > 0">, </template>
                                      <template v-for="(tag, index) in this.evidence.tags">
                                        <template v-if="index !== 0">, </template><span>{{ tag.name }}</span>
                                      </template>
                                    </template>
                                </p>

                                <!-- Display evidence body, with media players initialised -->
                                <media-article v-if="this.evidence.body">
                                    <div v-html="this.evidence.body" />
                                </media-article>
                            </section>
                        </div>

                        <!-- Discussion thread -->
                        <comment-thread 
                            :thread="evidence.discussionThread"
                            :create-thread-endpoint="evidence._links.createThread"
                            :can-comment="this.canComment"
                            :cdn-prefix="cdnPrefix"
                            :site-version="siteVersion"
                        />
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
