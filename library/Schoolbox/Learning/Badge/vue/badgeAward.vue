<script>

import axios from "axios";
import Toast from "@schoolbox/toast";

// Vue-select boilerplate
// TODO consider wrapping in our own component
import vSelect from "vue-select";
import debounce from "lodash/debounce";
import "vue-select/dist/vue-select.css";

// Render a custom template for the deselect icon.
// Partially documented on https://github.com/sagalbot/vue-select/issues/994
import Deselect from '@schoolbox/selectize/Deselect';
vSelect.props.components.default = () => ({ Deselect });

const EVENTS = {
    done: 'done',
    cancel: 'cancel'
};

// The number of milliseconds to wait between the user's input and a search for
// users.
const SEARCH_RESULT_RETRIEVE_DELAY = 350;

// There's no official method for documenting a component's events,
// https://forum.vuejs.org/t/how-to-document-events-for-components/35541/3
// suggests the @emits tag.

/**
 * Award badge form.
 *
 * @emits done   The award badge action was successfully completed
 * @emits cancel The award badge action was cancelled
 */
export default {
    components: {Toast, vSelect},
    props: {
        badge: {
            type: Object,
            default: {},
        },
    },
    data() {
        return {
            options: [],
            selected: [],
        }
    },
    methods: {
        /**
         * Search callback for the selectize.
         *
         * This method is debounced so that the search doesn't trigger until
         * the user has stopped typing for `SEARCH_RESULT_RETRIEVE_DELAY`
         * milliseconds.
         *
         * @param {String}   query   The search string
         * @param {Function} loading A function to call when the loading state
         *                           changes (accepts a Boolean)
         */
        onSearch: debounce(function(query, loading) {
            loading(true);
            axios
                .get('/ajax/user.php', {
                    params: {
                        query: query,
                        groupID: 0,
                    }
                })
                .then(response => this.options = response.data)
                .finally(() => loading(false))
        }, SEARCH_RESULT_RETRIEVE_DELAY),


        /**
         * Callback for when the award form is submitted.
         *
         * Posts to the badge's award link, then triggers this component's
         * "done" event.
         *
         * @emits done if the badge was successfully awarded
         */
        onSubmit() {
            this.$refs.toast.activate('Awarding...');
            axios.post(this.badge._links.awardAsAchievement, {users: this.selected})
                .then(() => {
                    // Clear the user list, so they don't show in the next badge award
                    this.selected = [];
                    this.$emit(EVENTS.done);
                    this.$refs.toast.timeout('Awarded', true);
                })
                .catch(() => this.$refs.toast.timeout('Award failed', false))
        },


        /**
         * Callback for when the award form is cancelled.
         *
         * @emits cancel
         */
        onCancel() {
            this.$emit(EVENTS.cancel);
        },
    },
}
</script>
<template>
    <div>
        <h2>Awarding &lsquo;<i>{{ badge.name }}</i>&rsquo; as an Achievement</h2>
        <v-select class="schoolbox-vue-selectize"
                  :options="options"
                  :clear-search-on-blur="function() { return true; }"
                  :clear-search-on-select="false"
                  :close-on-select="false"
                  multiple
                  @search="onSearch"
                  label="name"
                  placeholder="Search for users to award achievement to"
                  v-model="selected">
            <template slot="option" slot-scope="option">
                <span class="d-center">
                    {{ option.name }}
                </span>
            </template>
            <template slot="selected-option" slot-scope="option">
                <span class="selected d-center">
                    {{ option.name }}
                </span>
            </template>
        </v-select>
        <ul class="flex-list buttons">
            <li><button @click.prevent="onSubmit" class="submit" :disabled="selected.length === 0">Award</button></li>
            <li><button @click.prevent="onCancel">Cancel</button></li>
        </ul>

        <toast ref="toast"></toast>
    </div>
</template>
