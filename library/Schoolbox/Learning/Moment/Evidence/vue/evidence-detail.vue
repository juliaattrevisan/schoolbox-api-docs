<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import MediaArticle from "@schoolboxVue/components/media-article";
import Storage from "@schoolboxVue/components/storage";
import RelativeTime from "@schoolboxLearningMomentEvidence/relative-time";
import CommentThread from "./comment-thread";
import cdnPrefixMixin from "@schoolboxVue/components/cdn-prefix-mixin";
import CodesList from "@schoolboxCurriculum/vue/codes-list";
import RevealModal from "@schoolboxVue/components/reveal-modal";
import NodeDetails from "@schoolboxCurriculum/vue/node-details";

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
    props: ["evidence", "returnUrl", "canComment", "siteVersion"],

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
        CodesList,
        RevealModal,
        NodeDetails,
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {
            selectedNodeId: null, // number|null, the selected curriculum code to display further details for
        };
    },

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     */
    computed: {},

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
        hasRoutes: function () {
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
                    baseModifyLink =
                        baseModifyLink +
                        "?" +
                        encodeURIComponent(this.returnUrl.name) +
                        "=" +
                        encodeURIComponent(this.returnUrl.current);
                }
                return baseModifyLink;
            }
            return "";
        },

        /**
         * Format each except last with a comma
         *
         * @param { Number }
         *
         * @return { String }
         */
        tagNameWithComma(index) {
            if (index !== this.evidence.tags.length - 1) {
                return `${this.evidence.tags[index].name}, `;
            } else {
                return this.evidence.tags[index].name;
            }
        },

        /**
         * to open up the node details modal
         *
         * @pararm { object }
         */
        onCodeSelected(node) {
            this.selectedNodeId = node.id;
            this.$refs.nodeDetailsModal.open();
        },
    },
};
</script>
<template>
    <div class="evidence-detail">
        <template v-if="hasRoutes()">
            <div class="row">
                <div class="small-12 columns">
                    <div class="actions-small-1">
                        <nav>
                            <template v-if="evidence._links.delete">
                                <sbx-pop-out content-id="header-options"></sbx-pop-out>
                            </template>
                        </nav>
                        <ul id="header-options" class="f-dropdown" data-dropdown-content>
                            <li v-if="evidence._links.modifyForm">
                                <a class="icon-modify" :href="createModifyFormUrl()">Modify</a>
                            </li>
                            <template v-if="evidence._links.delete">
                                <li><a class="icon-delete" :href="evidence._links.delete" data-delete>Delete</a></li>
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
                                <!-- The wrapping div is expected by mepUtils.fixVideoPlayerDimensions(). -->
                                <div v-if="evidence.filename && evidence.storage" class="feed-body small-12 columns">
                                    <article>
                                        <storage :storage="evidence.storage" :filename="evidence.filename"></storage>
                                    </article>
                                </div>
                            </section>
                            <section class="detail content">
                                <p class="meta">
                                    By
                                    <a :href="evidence.creator._links.profile" title="View Profile">
                                        {{ evidence.creator.fullName }}
                                    </a>
                                    &mdash;
                                    <relative-time :datetime="evidence.createdAt"></relative-time>
                                    <!-- Display curriculum codes and tags -->
                                    <br />
                                    <template v-if="evidence.curriculumNodes.length > 0 || evidence.tags.length > 0">
                                        Tags:
                                        <codes-list
                                            :nodes="evidence.curriculumNodes"
                                            @selected="onCodeSelected"
                                        /><template
                                            v-if="evidence.curriculumNodes.length > 0 && evidence.tags.length > 0"
                                            >,
                                        </template>
                                        <span v-for="(tag, index) in evidence.tags" :key="tag.name">
                                            {{ tagNameWithComma(index) }}</span
                                        >
                                    </template>
                                </p>

                                <!-- Display evidence body, with media players initialised -->
                                <media-article v-if="evidence.body" :content="evidence.body"></media-article>
                            </section>
                        </div>

                        <!-- Discussion thread -->
                        <comment-thread
                            :thread="evidence.discussionThread"
                            :create-thread-endpoint="evidence._links.createThread"
                            :can-comment="canComment"
                            :cdn-prefix="cdnPrefix"
                            :site-version="siteVersion"
                        />
                    </div>
                </section>
            </div>
        </div>
    </div>
    <reveal-modal ref="nodeDetailsModal">
        <node-details v-if="selectedNodeId !== null" :node-id="selectedNodeId" />
    </reveal-modal>
</template>
