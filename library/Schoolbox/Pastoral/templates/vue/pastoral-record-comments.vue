<script>

/**
 * To add comments to an existing pastoral record
 */

// import cKEditor from '@vue/mixins/ckeditor/mixinCKEditor.js';
// import { EDITOR_CONFIG_FOR_COMMENTS } from '@js/editor/editorConfigTypes.js';


export default {

    // mixins: [
    //     cKEditor,
    // ],

    props: {
        comments: {
            type: Array, 
            required: true,
        },
        // commentDeleteRoute: {
        //     type: String,
        //     required: true,
        // },
        commentHeading: {
            type: String,
            required: true,
        },
        commentInsertRoute: {
            type: String,
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
    },

    methods: {

        /**
         * To initialise the CKEditor, passing in the preset config options that exist globally on the $ jQuery object
         */
        onAddCommentSelected () {
            this.cKEditor_init(EDITOR_CONFIG_FOR_COMMENTS);
        },

    },

}

</script>

<template>
    <div></div>
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