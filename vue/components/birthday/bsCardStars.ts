/**
 * animate the birthday card stars
 */

import { gsap, Physics2DPlugin } from "gsap/all";
import { getCanvasDimensions, getContext } from "@js/util/canvasUtils";
import type { CanvasDimensions } from "@js/util/canvasUtils";
import type GSAPTimeline from "gsap";
import Star, { redrawStars } from "../../../library/Schoolbox/Learning/Badge/Award/js/animations/Star";

gsap.registerPlugin(Physics2DPlugin); // register the physics2D plugin, otherwise the physics2D property won't be recognised

let timeline: GSAPTimeline; // to be able to kill the timeline on destroy

/**
 * Set up an array of Stars
 * Put them in the centre width of the Canvas and spread them along the vertical axis a bit so they can 'flick' out
 * @param canvas - the canvas element to draw the stars on
 * @returns - the stars to animate
 */
function getStars(canvas: HTMLCanvasElement): Star[] {
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
 * Each star has an animation, and the properties to animate are specified within
 * Through the onUpdate of the timeline, the properties are updated each tick
 * @param star - the star to animate
 * @param canvasDimensions - the dimensions of the canvas
 */
function addStarAnimation(star: Star, canvasDimensions: CanvasDimensions): void {
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
 * @param canvas - the canvas element to draw the stars on
 */
function animateStars(canvas: HTMLCanvasElement): void {
    const stars = getStars(canvas);
    const context = getContext(canvas) as CanvasRenderingContext2D;
    const canvasDimensions = getCanvasDimensions(canvas);
    timeline = gsap.timeline({
        // https://greensock.com/docs/v3/GSAP/gsap.timeline()
        repeat: 0,
        onUpdate: () => redrawStars(stars, context, canvasDimensions), // to draw the new position of the stars each tick
    });
    stars.forEach((star) => addStarAnimation(star, canvasDimensions));
}

/**
 * Set up both the badge and the stars animations
 * @param canvas - the canvas element to draw the stars on
 */
export function init(canvas: HTMLCanvasElement): void {
    if (timeline !== undefined) {
        // could be called multiple times on canvas resize, so kill the previous timeline
        timeline.kill();
    }
    animateStars(canvas);
}

/**
 * Set up both the badge and the stars animations
 * @param canvas - the canvas element to draw the stars on
 */
export function destroy(canvas: HTMLCanvasElement): void {
    timeline.kill();
    const context = getContext(canvas) as CanvasRenderingContext2D;
    context.clearRect(0, 0, canvas.width, canvas.height);
}
