<script>

/**
 * An individual student within the badge-report
 */

import BadgeImage from '../../vue/badge-image';
import HorizontalScroller from '@js/scroll/horizontalScroller';

export default {
    
    components: {
        BadgeImage,
    },

    props: {
        student: { //contains all the data needed to display
            type: Object,
            required: true
        },
    },

    mounted () {

        const scroller = new HorizontalScroller(this.$el.querySelector('[data-scroller]')); 

    },

    methods: {

        /**
         * Open the selected award detail modal
         * 
         * @params { Object } 
         */
        awardSelected (award) {
            this.$emit('on-award-selected', award);
        },

    }

}
</script>

<template>
    <li class="action-small-1 awards student">
        <div class="small-12 medium-1 column">
            <p>Checkbox</p>
        </div>
        <div class="small-12 medium-2 column">
            <a :href="`/search/user/${student.id}`">
                <img :src="`/portrait.php?id=${student.id}&size=square64`" :alt="student.fullName">
                {{ student.fullName }}
            </a>
        </div>
        <div class="small-12 medium-9 column">
            <div class="scroller-container" data-scroller>
                <div class="scroller-button-container">
                    <button class="previous-button" data-scroller-previous-button>&lt;</button>
                </div>
                <div class="scroller-flex-container">
                    <div class="scroller-outer-container" data-scroller-outer-container>
                        <transition-group
                            name="fade-in-award"
                            tag="ul"
                            class="scroller-inner-container"
                            data-scroller-inner-container
                        >      
                            <li v-for="award in student.awards" :key="award.id" class="award" data-scroller-item>
                                <a href="#open-award-modal" @click.prevent="awardSelected(award)" :title="award.badge.name">
                                    <badge-image :badge="award.badge" :css="award.isGoal ? 'badge goal' : 'badge'"></badge-image>
                                    <span>{{ award.badge.name }}</span>
                                </a>
                            </li>
                        </transition-group>
                        <div class="scroller-status" data-scroller-scroll-status></div>
                    </div>
                </div>
                <div class="scroller-button-container">
                    <button class="next-button" data-scroller-next-button>&gt;</button>
                </div>
            </div>
        </div>
    </li>
</template>

<style lang="scss" scoped>

@import 'www/static/css/foundation/utils/_variables.scss';

.student {
    height: 140px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.scroller-container {
    display: flex;
    align-items: center;
    justify-content: space-around;
}

.scroller-button-container {
    flex: 0 0 50px;
    height: 100%;
    // background: green;
    button {
        height: 100%;
        margin: 0px auto;
    }
}

.scroller-flex-container {
    // position: relative;
    // display: inline-block;
    // box-sizing: border-box;
    // width: 1000px;
    flex: 1 1 auto;
    // height: 140px;
    overflow: hidden;
    white-space: nowrap;
}

.scroller-outer-container {
    position: relative;
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
}

.scroller-inner-container {
    height: 100%;
    margin: 0;
    padding: 0;
}

.award::v-deep {
    display: inline-block;
    width: 140px;
    margin: 0px 0.5rem;
    vertical-align: top;
    text-align: center;
    transition: all 0.3s;
    img {
        width: 60px;
        height: auto;
        margin-bottom: 5px;
    }
    span {
        display: inline-block;
        font-size: 0.75rem;
        line-height: 1rem;
        white-space: normal;
    }
}

.scroller-status {
    position: absolute;
    left: 5px;
    bottom: 5px;
    width: 120px; //will be updated relative to scroll amount by horizontalScoller.js
    height: 4px;
    background: $content-ui-foreground;
}

// button {
//     display: inline-block;
//     padding: 10px;
// }

.fade-in-award-enter, .fade-in-award-leave-to {
    opacity: 0;
    transform: translateY(10px);
}
.fade-in-award-leave-active {
    position: absolute;
}




</style>