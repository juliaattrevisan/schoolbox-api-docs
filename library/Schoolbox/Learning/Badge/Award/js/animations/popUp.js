
/**
 * 'Pop up' the badge and throw some stars
 */

import { gsap, Physics2DPlugin } from 'gsap/all';
import Star from './Star';
import { getCanvasDimensions, getContext, getStarCount } from '../animateBadgeImage';

gsap.registerPlugin(Physics2DPlugin);


/**
 * Set up both the badge and the stars animations
 * 
 * @param { DOMElement }
 */
export async function init (badgeImage) {
    animateBadge(badgeImage);
    animateStars();
}

/**
 * Pop up the badge from the center, with a bit of elastic 'bounce'
 * 
 * @param { DOMElement }
 */
function animateBadge (badgeImage) {
    const popUpTL = gsap.timeline( { repeat: 0 } ); 
    popUpTL.set(badgeImage, { scale: 0, transformOrigin: '50% 50%', filter: 'grayscale(100%)' } );
    popUpTL.to(badgeImage, { duration: 2, scale: 1, ease: 'elastic.out', delay: 0.5 } );
    popUpTL.to(badgeImage, { duration: 0.15, filter: 'grayscale(0%)' }, '-=1.75');
}

/**
 * Get all the stars, add an anmation for each, and ensure the Canvas is redrawn on each update
 */
function animateStars () {
    const stars = getStars(); 
    const starsTimeline = gsap.timeline( { //https://greensock.com/docs/v3/GSAP/gsap.timeline()
        delay: 0.48, //wait prior to animating the stars to link when the pop up has 'hit' the screen
        repeat: 0, 
        onUpdate: () => redrawStars(stars) //to draw the new position of the stars each tick
    } ); 
    stars.forEach((star) => addStarAnimation(starsTimeline, star));
}

/**
 * Set up an array of Stars with random bright colours
 * Put them in the middle of the canvas, ready to animate out from the centre
 * 
 * @return { Array }
 */
function getStars () {
    const stars = [];
    const starCount = getStarCount();
    const canvasDimensions = getCanvasDimensions();
    for (let i = 0; i < starCount; i++) { 
        const star = new Star({
            x: canvasDimensions.width / 2, 
            y: canvasDimensions.height / 2, 
            radius: gsap.utils.random(5, 50),
        });
        stars.push(star);
    }
    return stars;
}

/**
 * Each star needs to be removed and redrawn each 'tick'
 * 
 * @param { Array }
 */
function redrawStars (stars) {
    const canvasDimensions = getCanvasDimensions();
    const context = getContext();
    context.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
    stars.forEach((star) => star.draw(context));
}

/**
 * Each star has an animation, and the properties to animate are specified within
 * Through the onUpdate of the timeline, the properties are updated each tick
 * 
 * @param { gsap.timeline }
 * @param { Star }
 */
 function addStarAnimation (starsTimeline, star) {
    starsTimeline.to(star, {
        delay: 'random(0, 0.25)', 
        duration: 3,
        radius: 'random(0, 0.3)',
        rotation: 'random(0, 360)',
        physics2D: { //https://greensock.com/docs/v3/Plugins/Physics2DPlugin
            acceleration: 0,
            angle: 'random(0, 360)',
            accelerationAngle: 'random(0, 0.1)',
            friction: 'random(0, 0.1)',
            gravity: 'random(300, 500)',
            velocity: 'random(500, 1550)',
        },
    }, 0 //0 so there's no delay between the animations, otherwise each star would only animate after the previous is complete
    );
}