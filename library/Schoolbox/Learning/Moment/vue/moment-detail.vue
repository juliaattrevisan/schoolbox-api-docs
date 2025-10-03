<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import MediaArticle from "@schoolboxVue/components/media-article.vue";
import StorageObject from "@schoolboxVue/components/storage-object.vue";
import RelativeTime from "@schoolboxVue/components/relativeTime/relative-time.vue";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import CodesList from "@schoolboxCurriculum/vue/codes-list.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import CommentThread from "./comment-thread.vue";
import type { LearningMoment } from "./evidenceTypes";

interface Props {
    moment: LearningMoment;
    canComment: boolean;
}

const props = defineProps<Props>();

/** the selected curriculum code to display further details for */
const selectedNodeId = window.Vue.ref<number | null>(null);

const isNodeDetailsModalOpen = window.Vue.computed({
    get: () => selectedNodeId.value !== null,
    set: (val: boolean) => {
        if (!val) selectedNodeId.value = null;
    },
});

const isDeleteModalOpen = window.Vue.ref(false);

/**
 * Delete this learning moment
 */
async function deleteMoment(): Promise<void> {
    if (!props.moment._links.delete) {
        throw new Error("Missing access to delete this Learning Moment");
    }
    isDeleteModalOpen.value = false;

    const toast = window.sbProps.toastActivate("Deleting Learning Moment...");

    const response = await apiRequest({
        method: "DELETE",
        url: props.moment._links.delete,
    });

    if (!response.success) {
        toast.timeout({ text: "Delete failed", isSuccess: false });
        return;
    }

    toast.timeout({ text: "Learning Moment deleted", isSuccess: true });

    // Redirect to the dashboard, i guess
    window.location.href = "/";
}
</script>
<template>
    <div v-if="props.moment._links.modifyForm || props.moment._links.delete" class="actions-small-1 row">
        <div class="list-item">
            <div class="small-12 column">
                <h1>&nbsp;</h1>
            </div>
        </div>

        <nav>
            <sbx-pop-out content-id="mf-pop-out"></sbx-pop-out>
        </nav>
        <ul id="mf-pop-out" class="f-dropdown" data-dropdown-content>
            <li v-if="props.moment._links.modifyForm">
                <a class="icon-modify" :href="getReturnUrl(props.moment._links.modifyForm)"> Modify </a>
            </li>
            <li v-if="props.moment._links.delete">
                <a class="icon-delete" :href="props.moment._links.delete" @click.prevent="isDeleteModalOpen = true">
                    Delete
                </a>
            </li>
        </ul>
    </div>

    <div class="evidence-detail news-post row">
        <div class="small-12 island">
            <section>
                <div class="row">
                    <div class="small-12 island">
                        <section v-if="props.moment.evidence.length" class="banner content">
                            <div class="feed-body small-12 columns">
                                <!-- Display evidence -->
                                <article v-for="e in props.moment.evidence" :key="e.storage.hash">
                                    <storage-object
                                        :storage="e.storage"
                                        :filename="e.filename"
                                        data-test="learning-moment-evidence-media"
                                    />
                                </article>
                            </div>
                        </section>
                        <section class="detail content">
                            <p class="meta">
                                <template v-if="props.moment.creator">
                                    By
                                    <a :href="props.moment.creator._links.profile" title="View Profile">
                                        {{ props.moment.creator.fullName }}
                                    </a>
                                    &mdash;
                                    <relative-time :datetime="props.moment.createdAt"></relative-time>
                                </template>
                                <!-- Display curriculum codes and tags -->
                                <template
                                    v-if="
                                        (props.moment.curriculumNodes !== null &&
                                            props.moment.curriculumNodes.length > 0) ||
                                        (props.moment.tags !== null && props.moment.tags.length > 0)
                                    "
                                >
                                    <br />
                                    Tags:
                                    <template v-if="props.moment.curriculumNodes !== null">
                                        <codes-list
                                            :nodes="props.moment.curriculumNodes"
                                            @selected="(node) => (selectedNodeId = node.id)"
                                        /><template
                                            v-if="
                                                props.moment.curriculumNodes.length > 0 &&
                                                props.moment.tags &&
                                                props.moment.tags.length > 0
                                            "
                                            >,
                                        </template>
                                    </template>
                                    <span v-if="props.moment.tags !== null && props.moment.tags.length > 0">
                                        {{ props.moment.tags.map((tag) => tag.name).join(", ") }}
                                    </span>
                                </template>
                            </p>

                            <!-- Display evidence body, with media players initialised -->
                            <media-article v-if="props.moment.body" :content="props.moment.body"></media-article>
                        </section>
                    </div>

                    <!-- Discussion thread -->
                    <comment-thread
                        :thread="props.moment.discussionThread ?? undefined"
                        :create-thread-endpoint="props.moment._links.createThread ?? undefined"
                        :can-comment="canComment"
                    />
                </div>
            </section>
        </div>
    </div>

    <basic-modal v-model:is-open="isNodeDetailsModalOpen" :has-background-click="true">
        <node-details v-if="selectedNodeId !== null" :node-id="selectedNodeId" />
    </basic-modal>

    <basic-modal v-model:is-open="isDeleteModalOpen" :has-background-click="true">
        <h2>Please confirm</h2>
        <p>Are you sure you wish to delete this learning moment?</p>
        <ul class="flex-list buttons">
            <li><a class="button submit" @click="deleteMoment">Delete</a></li>
            <li><a class="button" @click="isDeleteModalOpen = false">Cancel</a></li>
        </ul>
    </basic-modal>
</template>
