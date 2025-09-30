<script setup lang="ts">
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import MediaArticle from "@schoolboxVue/components/media-article.vue";
import StorageObject from "@schoolboxVue/components/storage-object.vue";
import RelativeTime from "@schoolboxVue/components/relativeTime/relative-time.vue";
import CodesList from "@schoolboxCurriculum/vue/codes-list.vue";
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import CommentThread from "./comment-thread.vue";
import type { LearningMoment } from "./evidenceTypes";

interface Props {
    evidence: LearningMoment;
    canComment: boolean;
}

const props = defineProps<Props>();

/** the selected curriculum code to display further details for */
const selectedNodeId = window.Vue.ref<number | null>(null);

const nodeDetailsModalEl = window.Vue.useTemplateRef<InstanceType<typeof RevealModal> | null>("nodeDetailsModalRef");

/**
 * to open up the node details modal
 * @param nodeId The ID of the curriculum node that was selected
 */
function onCodeSelected(nodeId: number): void {
    if (nodeDetailsModalEl.value === null) {
        throw new Error("nodeDetailsModalEl is null");
    }

    selectedNodeId.value = nodeId;
    nodeDetailsModalEl.value.open();
}
</script>
<template>
    <div class="evidence-detail">
        <div v-if="evidence._links.modifyForm || evidence._links.delete" class="row">
            <div class="small-12 columns">
                <div class="actions-small-1">
                    <nav>
                        <template v-if="evidence._links.delete">
                            <sbx-pop-out content-id="header-options"></sbx-pop-out>
                        </template>
                    </nav>
                    <ul id="header-options" class="f-dropdown" data-dropdown-content>
                        <li v-if="evidence._links.modifyForm">
                            <a class="icon-modify" :href="getReturnUrl(evidence._links.modifyForm)">Modify</a>
                        </li>
                        <template v-if="evidence._links.delete">
                            <li><a class="icon-delete" :href="evidence._links.delete" data-delete>Delete</a></li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>
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
                                    <template
                                        v-if="
                                            (evidence.curriculumNodes !== null &&
                                                evidence.curriculumNodes.length > 0) ||
                                            (evidence.tags !== null && evidence.tags.length > 0)
                                        "
                                    >
                                        Tags:
                                        <template v-if="evidence.curriculumNodes !== null">
                                            <codes-list
                                                v-if="evidence.curriculumNodes !== null"
                                                :nodes="evidence.curriculumNodes"
                                                @selected="(node) => onCodeSelected(node.id)"
                                            /><template
                                                v-if="
                                                    evidence.curriculumNodes.length > 0 &&
                                                    evidence.tags &&
                                                    evidence.tags.length > 0
                                                "
                                                >,
                                            </template>
                                        </template>
                                        <span v-if="evidence.tags !== null && evidence.tags.length > 0">
                                            {{ evidence.tags.map((tag) => tag.name).join(", ") }}
                                        </span>
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
