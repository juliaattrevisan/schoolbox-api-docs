
/**
 * 'Pop up' the badge and throw some stars
 */

import { gsap, Physics2DPlugin } from 'gsap/all';
import Star from './Star';
import { getCanvasDimensions, getContext } from '../animateBadgeImage';

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
    const coinSpinTL = gsap.timeline( { repeat: 0 } ); 
    coinSpinTL.set(badgeImage, { filter: 'grayscale(100%)', rotationY: 90 } );
    coinSpinTL.to(badgeImage, { duration: 1.5, rotationY: 360, ease: 'power2.out', delay: 0.5 } );
    coinSpinTL.to(badgeImage, { duration: 0.4, filter: 'grayscale(0%)' }, '-=0.5' );
}

/**
 * Get all the stars, add an anmation for each, and ensure the Canvas is redrawn on each update
 */
function animateStars () {
    const stars = getStars(); 
    const starsTimeline = gsap.timeline( { //https://greensock.com/docs/v3/GSAP/gsap.timeline()
        delay: 0.5, //wait prior to animating the stars to link when the pop up has 'hit' the screen
        repeat: 0, 
        onUpdate: () => redrawStars(stars) //to draw the new position of the stars each tick
    } ); 
    stars.forEach((star) => addStarAnimation(starsTimeline, star));
}

/**
 * Set up an array of Stars with random bright colours
 * Put them in the middle bottom of the canvas, ready to animate out from the middle bottom
 * 
 * @return { Array }
 */
function getStars () {
    const stars = [];
    const starCount = 500;
    const canvasDimensions = getCanvasDimensions();
    for (let i = 0; i < starCount; i++) { 
        const star = new Star({
            x: canvasDimensions.width/2, 
            y: gsap.utils.random(50, 300), 
            radius: gsap.utils.random(5, 30),
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
    const canvasDimensions = getCanvasDimensions();
    starsTimeline.to(star, {
        delay: 'random(0, 0.2)', 
        duration: 'random(0.5, 3)',
        rotation: 'random(0, 360)',
        x: Math.random() < 0.5 ? -100 : canvasDimensions.width + 100, //star moves either to the left or right edge
        physics2D: { //https://greensock.com/docs/v3/Plugins/Physics2DPlugin
            acceleration: 'random(0, 10)',
            angle: 'random(0, 360)',
            accelerationAngle: 'random(0, 0.5)',
            friction: 'random(0, 0.1)',
            gravity: 'random(100, 200)',
            velocity: 'random(800, 1000)',
        },
    }, 0 //0 so there's no delay between the animations, otherwise each star would only animate after the previous is complete
    );
}