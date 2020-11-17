<script>

import axios  from 'axios';
// import moment from 'moment';

export default {
    props: {
        getBadgesRoute: {
            type: String,
            default: '',
        },
        postBadgeRoute: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            data:     [],
            metadata: {
                count:  null,
                cursor: null,
            },
            showSkeletonLoader: true,
        }
    },
    computed: {
        // config: () => window.learningEvidenceFeedConfig,
        // dateDisplayFormats: () => window.dateDisplayFormats,
        // groupedEvidence() {
        //     return this.groupEvidence(this.data);
        // },
        // currentCount () {
        //     return data.length;
        // },
        // ckeditorConfig () {
        //     // Use the same config as the editor in the news does
        //     return window.jQuery.alaressEditorConfig({
        //         removePlugins: 'uploadimage,link,schoolboxanchor',
        //         removeButtons: 'Subscript,Superscript,RemoveFormat,Table,HorizontalRule,SpecialChar,EqnEditor,Embed',
        //         toolbarGroups: [
        //             {name: 'basicstyles', groups: ['basicstyles', 'cleanup']},
        //             {name: 'paragraph', groups: ['list', 'blocks', 'bidi']},
        //             {name: 'links', groups: ['links']},
        //             {name: 'insert', groups: ['insert']},
        //             {name: 'schoolbox', groups: ['schoolboxlink']},
        //         ],
        //     });
        // },
    },
    mounted() {
        this.load();
    },
    methods: {
        /**
         * Load badges.
         */
        load() {
            this.data = [];
            this.append(null);
        },

        /**
         * Appends learning evidence to the data list.
         *
         * @param {String|null} cursor
         */
        append(cursor) {
            // This is very generic, a candidate to be factored into its own place
            let params = {};
            if (cursor) {
                params.cursor = cursor;
            }
            axios
                .get(this.getBadgesRoute, {params})
                .then(response => {
                    this.data            = this.data.concat(response?.data?.data ? response.data.data : [])
                    this.metadata.count  = response?.data?.metadata?.count  ? response.data.metadata.count  : 0;
                    this.metadata.cursor = response?.data?.metadata?.cursor ? response.data.metadata.cursor : null;
                })
                .finally(() => this.showSkeletonLoader = false);
        },

        /**
         * Opens a modal window for modifying this badge.
         *
         * @param {Object} badge
         */
        openModifyModal(badge) {
            //
        },
    },
}
</script>

<template>
    <div>
        <section v-if="showSkeletonLoader">
            <div v-for="n in 3" class="list-item">
                <div class="small-12 card skeleton">
                    <div class="img"></div>
                    <h3></h3>
                    <p class="meta"></p>
                </div>
            </div>
        </section>
        <section v-else class="content">
            <ul class="information-list">
                <li v-for="badge in data">
                    <div class="card small-12">
                        <img :src="badge.image._links.image" :alt="badge.name" />
                        <a @click.prevent="openModifyModal(badge)">
                            <p>{{ badge.name }}</p>
                        </a>
                    </div>
                </li>
            </ul>
        </section>
    </div>

</template>
