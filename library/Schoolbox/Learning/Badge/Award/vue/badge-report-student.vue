<script>

/**
 * An individual student within the badge-report
 */

import { gsap } from 'gsap/all';
import BadgeImage from '../../vue/badge-image';
import { getIndex } from '@js/util/domUtils';
import HorizontalScoller from '@vue/components/scroll/horizontal-scoller';

const BADGE_ANIMATE_DURATION = 0.3;

export default {
    
    components: {
        BadgeImage,
        HorizontalScoller,
    },

    props: {
        student: { //contains all the data needed to display
            type: Object,
            required: true
        },
        selectedStudents: { //to clear a students selection when cleared by badge-report
            type: Array,
            required: true
        }
    },

    data () {
        return {
            hasContentChange: false //to track badge report changes due to filtering
        }
    },

    computed: {

        /**
         * To check if there's badges to display
         * 
         * @returns { Boolean }
         */
        hasBadges () {
            if (this.student.badges) {
                return true;
            } else {
                return false;
            }
        },

        /**
         * To ensure it's unchecked when 'Clear Selection' is selected in #badge-report
         * 
         * @returns { Boolean }
         */
        isInSelectedStudents () {
            return this.selectedStudents.includes(this.student.id);
        }

    },

    watch: {

        /**
         * We need to wait for the filter animation to complete before we re-evaluate
         * the status of horizontal-scroller.
         * @TODO - consider using ResizeObserver once Safari 15 is released, as 
         * this could be handled much better by the feature
         */
        student: {
            deep: true,
            handler (newStudentData, oldStudentData) {
                if (newStudentData?.badges?.length !== oldStudentData?.badges?.length) {
                    setTimeout(() => { 
                        this.hasContentChange = true;
                        this.$nextTick(() => this.hasContentChange = false);
                    }, BADGE_ANIMATE_DURATION * 2100);
                }
            }
        }

    },

    methods: {

        /**
         * Open the selected award detail modal
         * 
         * @params { Object } 
         */
        badgeSelected (award) {
            this.$emit('on-badge-selected', award);
        },

        /**
         * refer to https://vuejs.org/v2/guide/transitions.html#Staggering-List-Transitions
         * what happens before shown
         *
         * @param {DOMElement} element - the element that's animating
         */
        onBeforeEnter (element) {
            gsap.set(element, { opacity: 0, y: 10 } );
        },

        /**
         * how the element animates on
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
         */
        onEnter (element, done) {
            gsap.to(element, { opacity: 1, y: 0, ease: 'power1.out', duration: BADGE_ANIMATE_DURATION, delay: BADGE_ANIMATE_DURATION, onComplete: done } );
        },

        /**
         * how the element animates off
         *
         * @param {DOMElement} element - the element that's animating
         */
        onBeforeLeave (element) {
            const leftTarget = (element.getBoundingClientRect().width + 16) * getIndex(element);
            element.style.position = 'absolute';
            element.style.left = `${leftTarget}px`;
        },

        /**
         * how the element animates off
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
         */
        onLeave (element, done) {
            gsap.to(element, { opacity: 0, y: 10, ease: 'power1.out', duration: BADGE_ANIMATE_DURATION, onComplete: done } );
        },

        /**
         * how the element animates off
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
         */
        onAfterLeave (element) {
            element.style.position = 'static';
        },

        /**
         * To be able to award badges to multiple students
         * 
         * @params { Boolean } 
         */
        onStudentSelected (isChecked) {
            this.$emit('on-student-selected', this.student.id, isChecked);
        },

    }

}
</script>

<template>
    <li class="awards student">
        <div class="student__select">
            <div class="checklist">
                <input 
                    :id="`checkbox-${student.id}`"
                    type="checkbox"
                    :value="student.id"
                    :checked="isInSelectedStudents"
                    @change="onStudentSelected($event.target.checked)"
                />
                <label :for="`checkbox-${student.id}`"></label>
            </div>
        </div>
        <div class="student__portrait-and-name">
            <img :src="`/portrait.php?id=${student.id}&size=square64`" :alt="`profile photo of ${student.fullName}`" class="portrait">
            <template v-if="student.id">
                <a :href="`/search/user/${student.id}`" title="View profile" class="name">
                    {{ student.lastName + ', ' + student.firstName }}
                </a>
            </template>
            <template v-else>
                <span class="name">{{ student.lastName + ', ' + student.firstName }}</span>
            </template> 
        </div>
        <div class="student__badges">
            <horizontal-scoller 
                v-if="hasBadges"
                :hasButtons="true"
                :height="150"
                :has-content-change="hasContentChange"
            >
                <transition-group
                    tag="ul"
                    class="award-list"
                    :css="false"
                    @before-enter="onBeforeEnter"
                    @enter="onEnter"
                    @before-leave="onBeforeLeave" 
                    @leave="onLeave" 
                    @after-leave="onAfterLeave" 
                >      
                    <li v-for="award in student.badges" :key="award.id" class="award" data-scroller-item :data-title="award.badge.name">
                        <a href="#open-award-modal" @click.prevent="badgeSelected(award)" @keyup.enter="badgeSelected(award)" :title="award.badge.name">
                            <div class="award__image-container">
                                <badge-image :badge="award.badge" :css="award.isGoal ? 'badge goal' : 'badge'"></badge-image>
                            </div>
                            <div class="award__title-container">
                                <p>{{ award.badge.name }}</p>
                            </div>
                        </a>
                    </li>
                </transition-group>
            </horizontal-scoller>
            <div v-else class="empty-state">
                <i class="icon-badge"></i>
                <p>No badges to display.</p>
            </div>
        </div>
    </li>
</template>

<style lang="scss" scoped>

@import 'www/static/css/foundation/utils/_variables.scss';

$rowHeight: 150px;
$collapseStudent: 1000px;


.student {
    display: flex;
    width: 100%;
    height: $rowHeight;
    overflow: hidden;
    margin: 0 0 0.625rem 0;
    padding: 0;
    @media (max-width: $collapseStudent) {
        flex-wrap: wrap;
        height: auto;
        overflow: visible;
    }
}

    .student__select {
        width: 40px;
        padding-bottom: 0;
    }

    .student__portrait-and-name {
        display: flex;
        align-items: flex-start;
        width: 260px;
        padding-bottom: 0;
        @media (max-width: $collapseStudent) {
            margin-bottom: 1rem;
        }
    }

        .portrait {
            margin-right: 1rem;
        }

    .student__badges {
        width: calc(100% - 300px); //minus the checkbox and name
        @media (max-width: $collapseStudent) {
            width: 100%;
        }
    }

.award-list {
    margin: 0;
}

.award {
    display: inline-block;
    width: 160px;
    height: $rowHeight - 10px; //allow some space at the bottom 
    border: 1px solid #EDEDED;
    box-shadow: 0 2px 3px 0 rgb(0 0 0 / 10%);
    margin: 0px 0.25rem;
    padding: 0;
    vertical-align: top;
}

    .award__image-container::v-deep { //v-deep is used to can target the img succesfully
        position: relative;
        padding: 0.25rem 0.5rem;
        background-color: #F6F6F6;
        text-align: center;
        img {
            width: 40px;
            height: auto;
        }
    }

    .award__title-container {
        padding: 0.25rem 0.5rem;
        p {
            display: inline-block;
            font-size: 0.75rem;
            line-height: 1rem;
            white-space: normal;
        }
    }

.empty-state {
    height: $rowHeight;
    margin: 0; //remove the white space at the bottom when there's no badges
    background: white; //the grey looks a bit dodgy in combination with rows with badges
}

</style>