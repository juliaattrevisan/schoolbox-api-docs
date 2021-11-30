<script>

/**
 * To add comments to an existing pastoral record
 */

import AddCommentInput from '@vue/components/ckeditor/add-comment-input';
import MediaArticle from '@vue/components/media-article';
import Toast from '@vue/components/toast';
import RevealModal from '@vue/components/reveal-modal';
import cdnPrefixMixin from '@vue/components/cdn-prefix-mixin';


export default {

    components: {
        AddCommentInput,
        MediaArticle,
        Toast,
        RevealModal,
    },

    mixins: [
        cdnPrefixMixin,
    ],

    props: {
        commentHeading: {
            type: String,
            required: true,
        },
        commentInsertRoute: {
            type: String,
            required: true,
        },
        initialComments: {
            type: Array,
            required: true,
        },
        record: {
            type: Object,
            required: true,
        },
        recordType: {
            type: String,
            required: true,
        },
        siteVersion: {
            type: String,
            required: true
        }
    },

    data () {
        return {
            comments: [],
            isProcessing: false,
            commentIndexToDelete: null
        }
    },

    mounted () {
        this.comments = [...this.initialComments];
    },

    methods: {

        /**
         * Comment input failed to load, so show error
         */
        onLoadError () {
            this.$refs.toast.timeout('Loading comment box failed', false, 5000);
        },

        /**
         * Post a comment
         *
         * @param { String }
         */
        async postComment(newComment) {
            this.isProcessing = true;
            try {
                this.$refs.toast.activate('Saving...');
                const response = await fetch(this.commentInsertRoute, {
                    method: 'POST',
                    body: this.getPostCommentFormData(newComment),
                });
                //put the new comment into the top of the list
                this.$refs.toast.timeout('Comment posted', true, 2000);
            } catch (error) {
                console.warn(error);
                this.$refs.toast.timeout('Unable to post comment', false, 2000);
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         *
         *
         * @returns { FormData }
         */
        getPostCommentFormData (newComment) {
            const formData = new FormData();
            formData.append('comment_body', newComment);
            formData.append('pastoral_id', '6');
            formData.append('student_id', '13');
            if (this.recordType === 'private') {
               formData.append('comment_private', '1');
            }
            return formData;
        },

        /**
         * Opens the delete modal: pressing the Confirm button in the modal will
         * delete that comment.
         *
         * @param { Number }
         */
        showDeleteModal (index) {
            this.commentIndexToDelete = index;
            this.$refs.deleteModal.open();
        },

        /**
         * Deletes the comment at the given index within the thread.
         *
         * @param { Number }
         */
        deleteComment (index) {
            // $('[data-delete-comment]').click(function (event) {
            //     event.preventDefault();
            //     deleteCommentRoute = '{{ routeLookup('pastoral.postCommentDelete', {
            //         'recordId': 'recordId',
            //         'commentId': 'commentId'
            //     }) }}';
            //     $(this).confirmDialog({
            //         text: 'Are you sure you want to delete this comment?',
            //         confirmLabel: 'Delete',
            //         confirmEvent: function () {
            //             $.post(
            //                 deleteCommentRoute
            //                     .replace(/recordId/g, {{ record.id }})
            //                     .replace(/commentId/g, $(event.target).attr('data-delete-comment'))
            //             ).done(function () {
            //                 $(event.target).closest('li').remove();
            //             });
            //         }
            //     });
            // });
        },

        /**
         * Return the date from now,
         *
         * @param { Date }
         *
         * @returns { String } ie: 2 hours ago
         */
        fromNow (date) {
            return dayjs(date).fromNow();
        },

        /**
         * Dispaly the full date
         *
         * @param { Date }
         *
         * @returns { String }
         */
        fullString (date) {
            return dayjs(date).format(dateDisplayFormats.dayjsDatetime);
        }

    },

}

</script>

<template>
    <div id="comments">

        <div class="row">

            <div class="small-12 island">
                <section class="content no-margin hide-for-print">
                    <label>{{ commentHeading }} Comments ({{ comments.length }})</label>
                    <add-comment-input
                        :cdn-prefix="cdnPrefix"
                        :is-processing="isProcessing"
                        :site-version="siteVersion"
                        :preset-unique-id="recordType"
                        @on-load-error="onLoadError"
                        @on-post-comment="postComment"
                    />
                </section>

                <template v-if="comments.length > 0">
                <hr class="no-margin" />

                <section class="discussion">
                    <ul class="information-list">
                        <li v-for="(comment, index) in comments" :key="comment.id" class="actions-small-1">
                           <div class="list-item">
                                <div class="card compact small-12">
                                    <a :href="comment.owner._links.profile" title="View Profile">
                                        <img :src="comment.owner._links.avatar" title="profile photo">
                                        {{ comment.owner.fullName }}
                                        <span class="pipe meta" :title="fullString(comment.updatedAt)">Posted {{ fromNow(comment.updatedAt) }}</span>
                                    </a>
                                </div>
                            </div>
                            <nav>
                                <!-- <a v-if="comment._links.delete.auth" @click.prevent="showDeleteModal(index)" href="#delete-comment" class="icon-delete no-text" title="Delete comment"></a> -->
                                <a @click.prevent="showDeleteModal(index)" href="#delete-comment" class="icon-delete no-text" title="Delete comment"></a>
                            </nav>
                            <media-article v-html="comment.body"></media-article>
                        </li>
                    </ul>
                </section>

                </template>

            </div>

            <toast ref="toast"></toast>

            <reveal-modal ref="deleteModal">
                <template>
                    <h2>Please confirm</h2>
                    <p>Are you sure you want to delete this comment?</p>
                    <ul class="flex-list buttons">
                        <li><a @click.prevent="deleteComment(commentIndexToDelete)" class="button submit">Delete</a></li>
                        <li><a @click.prevent="$refs.deleteModal.close" class="button">Cancel</a></li>
                    </ul>
                </template>
            </reveal-modal>

        </div>

    </div>
    <!-- <div id="comments">
        <div class="row">
            <div class="small-12 island">
                <section class="content no-margin hide-for-print">
                    <label>{{ commentHeading }} Comments ({{ comments|length }})</label>
                    <form method="post" action="{{ commentInsertRoute }}">
                        <input type="hidden" name="pastoral_id" value="{{ record.id }}"> <input type="hidden" name="student_id" value="{{ record.student.id }}">
                        <textarea id="comment-body-{{ recordType }}" rows="3" cols="50" name="comment_body" placeholder="Add a comment"></textarea>
                        {% if recordType == 'private' %}
                            <input type="hidden" name="comment_private" value="1"/>
                        {% endif %}
                        <input disabled data-comment-insert-{{ recordType }} type="submit" value="Post comment">
                    </form>
                </section>

                {% if comments | length > 0 %}
                <hr class="no-margin" />

                <section class="discussion">
                    <ul class="information-list">
                        {% for comment in comments %}
                            <li class="actions-small-1">
                                <div class="list-item">
                                    <div class="small-12 compact card">
                                        {% if comment.owner.isDeleted() %}
                                            {{ profile.imageSquare64(comment.owner) }}
                                            <p>
                                                {{ comment.owner.fullName }}
                                            </p>
                                            <span class="pipe meta" title="{{ comment.createdAt.short }}">{{ comment.createdAt.relativeDay }}</span>
                                        {% else %}
                                            <a href="{{ profile.link(comment.owner) }}" title="View Profile">
                                                {{ profile.imageSquare64(comment.owner) }}
                                                <p>
                                                    {{ comment.owner.fullName }}
                                                </p>
                                                <span class="pipe meta" title="{{ comment.createdAt.short }}">{{ comment.createdAt.relativeDay }}</span>
                                            </a>
                                        {%  endif %}
                                    </div>
                                    <article>{{ comment.body | purify }}</article>
                                </div>
                                <nav>
                                    {% set commentDeleteRoute = routeLookupIfAuthorized('pastoral.postCommentDelete', {'recordId': record.id, 'commentId': comment.id}) %}
                                    {% if commentDeleteRoute %}
                                        <a data-delete-comment="{{ comment.id }}" class="icon-delete" href="#" title="Delete Comment"></a>
                                    {% endif %}
                                </nav>
                            </li>
                        {% endfor %}
                    </ul>
                </section>
                {% endif %}
            </div>
        </div>
    </div> -->
</template>

<style scoped>

</style>
