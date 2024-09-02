/**
 * Spin the badge in and throw some stars outwards as it spins around
 */

import { gsap, Physics2DPlugin } from "gsap/all";
import Star from "./Star";
import { getCanvasDimensions, getContext } from "@js/util/canvasUtils";
import { getStarCount } from "../animateBadgeImage";

gsap.registerPlugin(Physics2DPlugin);

/**
 * Set up both the badge and the stars animations
 *
 * @param { DOMElement }
 * @param DOMElement
 */
export async function init(badgeImage, awardCanvas) {
    animateBadge(badgeImage);
    animateStars(awardCanvas);
}

/**
 * Animate the badge
 *
 * @param { DOMElement }
 */
function animateBadge(badgeImage) {
    const coinSpinTL = gsap.timeline({ repeat: 0 });
    coinSpinTL.set(badgeImage, { filter: "grayscale(100%)", rotationY: 90 });
    coinSpinTL.to(badgeImage, { duration: 1.5, rotationY: 360, ease: "power2.out", delay: 0.5 });
    coinSpinTL.to(badgeImage, { duration: 0.4, filter: "grayscale(0%)" }, "-=0.5");
}

/**
 * Get all the stars, add an animation to the gsap.timeline for each and ensure the Canvas is redrawn on each update.
 * @param DOMElement
 */
function animateStars(awardCanvas) {
    const stars = getStars(awardCanvas);
    const context = getContext(awardCanvas);
    const canvasDimensions = getCanvasDimensions(awardCanvas);
    const starsTimeline = gsap.timeline({
        //https://greensock.com/docs/v3/GSAP/gsap.timeline()
        delay: 0.5, //wait prior to animating the stars ot best sync to the badge animation
        repeat: 0,
        onUpdate: () => redrawStars(stars, context, canvasDimensions), //to draw the new position of the stars each tick
    });
    stars.forEach((star) => addStarAnimation(starsTimeline, star, canvasDimensions));
}

/**
 * Set up an array of Stars
 * Put them in the centre width of the Canvas and spread them along the vertical axis a bit so they can 'flick' out
 *
 * @return { Array }
 * @param DOMElement
 */
function getStars(canvas) {
    const stars = [];
    const canvasDimensions = getCanvasDimensions(canvas);
    let star; //reuse reference in loop
    for (let i = 0; i < getStarCount(canvas); i++) {
        star = new Star({
            x: canvasDimensions.width / 2,
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
 * @param { CanvasRenderingContext2D }
 * @param { width, height }
 */
function redrawStars(stars, context, canvasDimensions) {
    context.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
    stars.forEach((star) => star.draw(context));
}

/**
 * Each star has an animation, and the properties to animate are specified within
 * Through the onUpdate of the timeline, the properties are updated each tick
 *
 * @param { gsap.timeline }
 * @param { Star }
 * @param { width, height }
 */
function addStarAnimation(starsTimeline, star, canvasDimensions) {
    starsTimeline.to(
        star,
        {
            delay: "random(0, 0.2)",
            duration: "random(0.5, 3)",
            rotation: "random(0, 360)",
            x: Math.random() < 0.5 ? -100 : canvasDimensions.width + 100, //star moves either to the left or right edge
            physics2D: {
                //https://greensock.com/docs/v3/Plugins/Physics2DPlugin
                acceleration: "random(0, 10)",
                angle: "random(0, 360)",
                accelerationAngle: "random(0, 0.5)",
                friction: "random(0, 0.1)",
                gravity: "random(100, 200)",
                velocity: "random(800, 1000)",
            },
        },
        0, //0 so there's no delay between the animations, otherwise each star would only animate after the previous is complete
    );
}
