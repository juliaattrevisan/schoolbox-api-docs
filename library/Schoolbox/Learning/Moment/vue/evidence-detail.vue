<script setup lang="ts">
import MediaArticle from "@schoolboxVue/components/media-article.vue";
import StorageObject from "@schoolboxVue/components/storage-object.vue";
import RelativeTime from "@schoolboxVue/components/relativeTime/relative-time.vue";
import CodesList from "@schoolboxCurriculum/vue/codes-list.vue";
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import type { Node } from "@schoolboxCurriculum/types/Node";
import CommentThread from "./comment-thread.vue";
import type { LearningMoment } from "./evidenceTypes";

interface Props {
    evidence: LearningMoment;
    returnUrl: {
        name?: string;
        current?: string;
    };
    canComment: boolean;
    cdnPrefix: string;
    siteVersion: string;
}

const props = defineProps<Props>();

/** the selected curriculum code to display further details for */
const selectedNodeId = window.Vue.ref<number | null>(null);

const nodeDetailsModalEl = window.Vue.useTemplateRef<InstanceType<typeof NodeDetails> | null>("nodeDetailsModalRef");

/**
 * @returns whether to show the tot menu - only provide when it has at least one
 * accessible route
 */
function hasRoutes(): boolean {
    /* For now there are only two options, so tot is fairly
     * straightforward to calculate this way
     */
    return props.evidence._links.modify || props.evidence._links.delete;
}

/**
 * Create a modify form URL for this evidence, consisting of the
 * evidence's existing modify form link and a return URL query string.
 * @returns the modify form URL, or an empty string if no modify form link exists
 */
function createModifyFormUrl(): string {
    if (props.evidence._links.modifyForm) {
        let baseModifyLink = props.evidence._links.modifyForm;
        if (props.returnUrl.name && props.returnUrl.current) {
            baseModifyLink =
                baseModifyLink +
                "?" +
                encodeURIComponent(props.returnUrl.name) +
                "=" +
                encodeURIComponent(props.returnUrl.current);
        }
        return baseModifyLink;
    }
    return "";
}

/**
 * Format each tag except last with a comma
 * @param index The index of the tag in the evidence tags array
 * @returns A comma separated tag name
 */
function tagNameWithComma(index: number): string {
    if (index !== props.evidence.tags.length - 1) {
        return `${props.evidence.tags[index].name}, `;
    } else {
        return props.evidence.tags[index].name;
    }
}

/**
 * to open up the node details modal
 * @param node The curriculum node that was selected
 */
function onCodeSelected(node: Node): void {
    selectedNodeId.value = node.id;
    nodeDetailsModalEl.value.open();
}
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
                                <!-- TODO: this is totally wrong -->
                                <div v-if="evidence.storage" class="feed-body small-12 columns">
                                    <article>
                                        <storage-object :storage="evidence.storage" />
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
                        />
                    </div>
                </section>
            </div>
        </div>
    </div>
    <reveal-modal ref="nodeDetailsModalRef">
        <node-details v-if="selectedNodeId !== null" :node-id="selectedNodeId" />
    </reveal-modal>
</template>
