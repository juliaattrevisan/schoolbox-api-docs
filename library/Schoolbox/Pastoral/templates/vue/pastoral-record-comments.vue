<script>

/**
 * To add comments to an existing pastoral record
 */

import Toast from '@vue/components/toast';
import AddCommentInput from '@vue/components/ckeditor/add-comment-input';
import cdnPrefixMixin from '@vue/components/cdn-prefix-mixin';
import cloneDeep from 'lodash/cloneDeep';


export default {

    components: {
        Toast,
        AddCommentInput,
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
        }
    },

    mounted () {
        this.comments = cloneDeep(this.initialComments);
    },

    methods: {

        /**
         * Comment input failed to load, so show error
         */
        onLoadError () {
            console.warn('error');
            // this.$refs.toast.timeout('Loading comment box failed', false, 5000);
        },

        /**
         * Post a comment
         *
         *  @param { String }
         */
        postComment(newComment) {
            console.warn('postComment');
        }

    },

}

</script>

<template>
    <!-- <form method="post" :action="commentInsertRoute">
        <add-comment-input
            :cdn-prefix="cdnPrefix"
            :is-processing="isProcessing"
            :site-version="siteVersion"
            @on-load-error="onLoadError"
            @on-post-comment="postComment"
        />
        <input v-if="recordType === 'private'" type="hidden" name="comment_private" value="1" />
    </form> -->
    <div id="comments">
        <div class="row">
            <div class="small-12 island">
                <section class="content no-margin hide-for-print">
                    <label>{{ commentHeading }} Comments ({{ comments.length }})</label>
                    <!-- <form method="post" action="{{ commentInsertRoute }}"> -->
                        <!-- <input type="hidden" name="pastoral_id" value="{{ record.id }}"> <input type="hidden" name="student_id" value="{{ record.student.id }}"> -->
                        <add-comment-input
                            :cdn-prefix="cdnPrefix"
                            :is-processing="isProcessing"
                            :site-version="siteVersion"
                            @on-load-error="onLoadError"
                            @on-post-comment="postComment"
                        />
                        <!-- {% if recordType == 'private' %}
                            <input type="hidden" name="comment_private" value="1"/>
                        {% endif %} -->
                        <!-- <input disabled data-comment-insert-{{ recordType }} type="submit" value="Post comment"> -->
                    <!-- </form> -->
                </section>

                <template v-if="comments.length > 0">
                <hr class="no-margin" />

                <section class="discussion">
                    <ul class="information-list">
                        <li v-for="comment in comments" :key="comment.id" class="actions-small-1">
                            <div></div>
                            <!-- <div class="list-item">
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
                            </nav> -->
                        </li>
                    </ul>
                </section>

                </template>

            </div>
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
