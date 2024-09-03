/**
 * 'Pop up' the badge and throw some stars from the bottom
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
    const flipUpTL = gsap.timeline({ repeat: 0 });
    flipUpTL.set(badgeImage, {
        transformOrigin: "50% 100%",
        transformStyle: "preserve-3d",
        rotationX: 90,
        scaleX: 0.4,
        filter: "grayscale(100%)",
    });
    flipUpTL.to(badgeImage, {
        duration: 1.5,
        rotationX: 0,
        scaleX: 1,
        ease: "back.out(3.5)",
        transformOrigin: "50% 100%",
        delay: 0.5,
    });
    flipUpTL.to(badgeImage, { duration: 0.8, filter: "grayscale(0%)" }, "-=0.7");
}

/**
 * Get all the stars, add an animation to the gsap.timeline for each and ensure the Canvas is redrawn on each update.
 * @param DOMElement
 */
function animateStars(canvas) {
    const stars = getStars(canvas);
    const context = getContext(canvas);
    const canvasDimensions = getCanvasDimensions(canvas);
    const starsTimeline = gsap.timeline({
        //https://greensock.com/docs/v3/GSAP/gsap.timeline()
        delay: 0.48, //wait prior to animating the stars ot best sync to the badge animation
        repeat: 0,
        onUpdate: () => redrawStars(stars, context, canvasDimensions), //to draw the new position of the stars each tick
    });
    stars.forEach((star) => addStarAnimation(starsTimeline, star));
}

/**
 * Set up an array of Stars
 * Put them in the horizontal middle, but near the bottom, so they 'explode' linked to the flip up animation
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
            y: canvasDimensions.height - 50,
            radius: gsap.utils.random(20, 50),
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
 */
function addStarAnimation(starsTimeline, star) {
    starsTimeline.to(
        star,
        {
            delay: "random(0, 0.25)",
            duration: 3,
            radius: "random(0, 0.3)",
            rotation: "random(0, 360)",
            physics2D: {
                //https://greensock.com/docs/v3/Plugins/Physics2DPlugin
                acceleration: 0,
                angle: "random(180, 360)",
                accelerationAngle: "random(0, 0.6)",
                friction: "random(0, 0.2)",
                gravity: "random(300, 400)",
                velocity: "random(600, 850)",
            },
        },
        0, //0 so there's no delay between the animations, otherwise each star would only animate after the previous is complete
    );
}
