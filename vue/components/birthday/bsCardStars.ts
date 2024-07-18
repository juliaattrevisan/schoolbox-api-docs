/**
 * animate the birthday modal stars
 */

import { gsap, Physics2DPlugin } from "gsap/all";
import { getCanvasDimensions, getContext } from "@js/util/canvasUtils";
import type { CanvasDimensions } from "@js/util/canvasUtils";
import type GSAPTimeline from "gsap";
import Star from "../../../library/Schoolbox/Learning/Badge/Award/js/animations/Star";

gsap.registerPlugin(Physics2DPlugin); // register the physics2D plugin, otherwise the physics2D property won't be recognised

/**
 * Set up an array of Stars
 * Put them in the centre width of the Canvas and spread them along the vertical axis a bit so they can 'flick' out
 * @param { HTMLCanvasElement} canvas - the canvas element to draw the stars on
 * @returns {Star[]} - the stars to animate
 */
function getStars(canvas: HTMLCanvasElement) {
    const stars = [];
    const canvasDimensions = getCanvasDimensions(canvas);
    let star; // reuse reference in loop
    for (let i = 0; i < 200; i += 1) {
        star = new Star({
            x: gsap.utils.random(0, canvasDimensions.width),
            y: -50,
            radius: gsap.utils.random(10, 30),
        });
        stars.push(star);
    }
    return stars;
}

/**
 * Each star needs to be removed and redrawn each 'tick'
 * @param {Star[]} stars - the stars to redraw
 * @param {CanvasRenderingContext2D} context - the context to draw the stars on
 * @param {CanvasDimensions} canvasDimensions - the dimensions of the canvas
 */
function redrawStars(stars: Star[], context: CanvasRenderingContext2D, canvasDimensions: CanvasDimensions) {
    context.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
    stars.forEach((star) => star.draw(context));
}

/**
 * Each star has an animation, and the properties to animate are specified within
 * Through the onUpdate of the timeline, the properties are updated each tick
 * @param {GSAPTimeline} timeline - the timeline to add the animations to
 * @param {Star} star - the star to animate
 * @param {CanvasDimensions} canvasDimensions - the dimensions of the canvas
 */
function addStarAnimation(timeline: GSAPTimeline, star: Star, canvasDimensions: CanvasDimensions) {
    timeline.to(
        star,
        {
            delay: "random(0, 9)",
            duration: "random(6, 9)",
            radius: "random(0, 0.5)",
            rotation: "random(360, 720)",
            repeat: -1,
            y: `random(${canvasDimensions.height + 100}, ${canvasDimensions.height + 200})`,
        },
        0, // 0 so there's no delay between the animations, otherwise each star would only animate after the previous is complete
    );
}

/**
 * Get all the stars, add an animation to the gsap.timeline for each and ensure the Canvas is redrawn on each update.
 * @param {HTMLCanvasElement} canvas - the canvas element to draw the stars on
 */
function animateStars(canvas: HTMLCanvasElement) {
    const stars = getStars(canvas);
    const context = getContext(canvas) as CanvasRenderingContext2D;
    const canvasDimensions = getCanvasDimensions(canvas);
    const timeline = gsap.timeline({
        // https://greensock.com/docs/v3/GSAP/gsap.timeline()
        repeat: 0,
        onUpdate: () => redrawStars(stars, context, canvasDimensions), // to draw the new position of the stars each tick
    });
    stars.forEach((star) => addStarAnimation(timeline, star, canvasDimensions));
}

/**
 * Set up both the badge and the stars animations
 * @param {HTMLCanvasElement} canvas - the canvas element to draw the stars on
 */
export function init(canvas: HTMLCanvasElement) {
    animateStars(canvas);
}
