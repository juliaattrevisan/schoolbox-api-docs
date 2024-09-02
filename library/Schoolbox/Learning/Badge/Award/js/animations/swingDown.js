/**
 * Swing down the badge, with stars swinging down as well, kind of like snow
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
    const swingDownTL = gsap.timeline({ repeat: 0 });
    swingDownTL.set(badgeImage, { rotationZ: 180, y: -50, transformOrigin: "50% 0%", filter: "grayscale(100%)" });
    swingDownTL.to(badgeImage, { duration: 2.3, y: 0, rotationZ: 0, ease: "elastic", delay: 0.5 });
    swingDownTL.to(badgeImage, { duration: 0.4, filter: "grayscale(0%)" }, "-=0.6");
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
        delay: 0.2, //wait prior to animating the stars ot best sync to the badge animation
        repeat: 0,
        onUpdate: () => redrawStars(stars, context, canvasDimensions), //to draw the new position of the stars each tick
    });
    stars.forEach((star) => addStarAnimation(starsTimeline, star));
}

/**
 * Set up an array of Stars
 * Spread them across the width of the canvas and put them above view, so they will drop down from below
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
            x: gsap.utils.random(0, canvasDimensions.width),
            y: -200,
            radius: gsap.utils.random(20, 40),
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
            delay: "random(0, 1.5)",
            duration: "random(0.5, 2.5)",
            radius: 0,
            rotation: "random(0, 90)",
            physics2D: {
                //https://greensock.com/docs/v3/Plugins/Physics2DPlugin
                acceleration: "random(0, 10)",
                angle: "random(0, 180)",
                accelerationAngle: "random(0, 0.5)",
                friction: "random(0, 0.1)",
                gravity: "random(400, 600)",
                velocity: "random(500, 700)",
            },
        },
        0, //0 so there's no delay between the animations, otherwise each star would only animate after the previous is complete
    );
}
