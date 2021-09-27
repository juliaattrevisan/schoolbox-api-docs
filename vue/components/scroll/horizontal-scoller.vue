<script>

/**
 * To scroll a list of items horizontally
 */

import HorizontalScroller from '@js/scroll/HorizontalScroller';

const BADGE_ANIMATE_DURATION = 0.3;

export default {
    
    // components: {
        
    // },

    props: {
        hasButtons: { //whether to show prev/next buttons
            type: Boolean,
            required: true
        },
        // selectedStudents: { //to clear a students selection when cleared by badge-report
        //     type: Array,
        //     required: true
        // }
    },

    computed: {

        showButtons () {
            return this.hasButtons;
        }

    },

    mounted () {
        this.initHorizontalScroller();
    },

    methods: {

        /**
         * To horizontally scroll the badge list
         */
        initHorizontalScroller () {
            this.horizontalScroller = new HorizontalScroller(this.$el, this.hasButtons); 
        },

    }

}
</script>

<template>
    <div class="scroller" data-scroller>
        <div v-show="showButtons" class="scroller__button-container">
            <button class="button" data-scroller-previous-button>&lt;</button>
        </div>
        <div class="scroller__outer-container" data-scroller-outer-container>
            <div class="scroller__inner-container" data-scroller-inner-container>
                <slot></slot>
            </div>
            <div class="scroller-bar" data-scroller-scroll-bar></div>
        </div>
        <div v-show="showButtons" class="scroller__button-container">
            <button class="button" data-scroller-next-button>&gt;</button>
        </div>
    </div>
</template>

<style lang="scss" scoped>

@import 'www/static/css/foundation/utils/_variables.scss';

$rowHeight: 150px;

.scroller {
    display: flex;
    align-items: flex-start;
    justify-content: space-around;
    height: $rowHeight;
}

.scroller__button-container {
    flex: 0 0 50px;
    height: 100%;
}

.button {
    width: 100%;
    height: 100%;
    margin: 0px auto;
    background: none;
    font-weight: bold;
    font-size: 1.5rem;
    color: #4f4f4f;
}

.scroller__outer-container {
    flex: 1 1 auto;
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    margin: 0;
    padding: 0;
    white-space: nowrap;
}

.scroller__inner-container {
    height: 100%;
    margin: 0;
    padding: 0;
}

.scroller-bar {
    position: absolute;
    left: 5px;
    bottom: 0px;
    width: 120px; //will be updated relative to scroll amount by horizontalScoller.js
    height: 4px;
    background: $content-ui-foreground;
}

</style>