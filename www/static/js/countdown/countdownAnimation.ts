/**
 * To play the finishing animation of the countdown
 */

import { gsap, Physics2DPlugin } from "gsap/all";
import { getCanvasDimensions, getContext } from "@js/util/canvasUtils";
import type { CanvasDimensions } from "@js/util/canvasUtils";
import type GSAPTimeline from "gsap";
import Star from "../../../../library/Schoolbox/Learning/Badge/Award/js/animations/Star";

gsap.registerPlugin(Physics2DPlugin); // register the physics2D plugin, otherwise the physics2D property won't be recognised

let timeline: GSAPTimeline; // to be able to kill the timeline on destroy

/**
 * Set up an array of Stars
 * Put them all in the middle of the canvas, so they can 'explode' out from the centre
 * @param canvas - the canvas element to draw the stars on
 * @returns - the stars to animate
 */
function getStars(canvas: HTMLCanvasElement): Star[] {
    const stars = [];
    const canvasDimensions = getCanvasDimensions(canvas);
    let star; // reuse reference in loop
    // for (let i = 0; i < getStarCount(canvas); i += 1) {
    for (let i = 0; i < 200; i += 1) {
        star = new Star({
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
 * @param stars - the stars to redraw
 * @param context - the context to draw the stars on
 * @param canvasDimensions - the dimensions of the canvas
 */
function redrawStars(stars: Star[], context: CanvasRenderingContext2D, canvasDimensions: CanvasDimensions): void {
    context.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
    stars.forEach((star) => star.draw(context));
}

/**
 * Each star has an animation, and the properties to animate are specified within
 * Through the onUpdate of the timeline, the properties are updated each tick
 * @param star - the star to animate
 */
function addStarAnimation(star: Star): void {
    timeline.to(
        star,
        {
            delay: "random(0, 0.25)",
            duration: 3,
            radius: "random(0, 0.3)",
            rotation: "random(0, 360)",
            physics2D: {
                // https://greensock.com/docs/v3/Plugins/Physics2DPlugin
                acceleration: 0,
                angle: "random(0, 360)",
                accelerationAngle: "random(0, 0.1)",
                friction: "random(0, 0.1)",
                gravity: "random(300, 500)",
                velocity: "random(500, 1550)",
            },
        },
        0, // 0 so there's no delay between the animations, otherwise each star would only animate after the previous is complete
    );
}

/**
 * Set up both the badge and the stars animations
 * @param canvas - the canvas element to draw the stars on
 */
function destroy(canvas: HTMLCanvasElement): void {
    timeline.kill();
    const context = getContext(canvas) as CanvasRenderingContext2D;
    context.clearRect(0, 0, canvas.width, canvas.height);
}

/**
 * Get all the stars, add an animation to the gsap.timeline for each and ensure the Canvas is redrawn on each update.
 * @param canvas - the canvas element to draw the stars on
 */
function animateStars(canvas: HTMLCanvasElement): void {
    const stars = getStars(canvas);
    const context = getContext(canvas);
    if (!context) {
        return;
    }
    const canvasDimensions = getCanvasDimensions(canvas);
    timeline = gsap.timeline({
        // https://greensock.com/docs/v3/GSAP/gsap.timeline()
        repeat: 0,
        onUpdate: () => redrawStars(stars, context, canvasDimensions), // to draw the new position of the stars each tick
        onComplete: () => destroy(canvas),
    });
    stars.forEach((star) => addStarAnimation(star));
}

/**
 * Play the finishing animation of the countdown
 * @param  domRect - The bounding client rectangle of the target element
 */
export function play(domRect: DOMRect): void {
    // create a canvas element to draw the stars on, using the position of the domRect
    const canvas = document.createElement("canvas");
    canvas.width = domRect.width;
    canvas.height = domRect.height;
    canvas.style.position = "absolute";
    canvas.style.left = `${domRect.left}px`;
    canvas.style.top = `${domRect.top}px`;
    canvas.style.zIndex = "var(--sbx-depth-pop-over)";
    document.body.appendChild(canvas);
    animateStars(canvas);
    // origin: {
    //         x: (boundRect.left + boundRect.width / 2) / window.innerWidth,
    //         y: (boundRect.top + boundRect.height / 2) / window.innerHeight,
    //     },
}
