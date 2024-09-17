/**
 * The star used by the badge award animations
 * It's to be used within a <canvas></canvas> element
 */

import type { CanvasDimensions } from "@js/util/canvasUtils";

const colours = [
    "#ff7d00",
    "#ffd100",
    "#cce300",
    "#73c700",
    "#1c9e1c",
    "#009eb2",
    "#00e38f",
    "#4dc7eb",
    "#00aded",
    "#0080ff",
    "#0066b0",
    "#6900b0",
    "#c2009e",
    "#ff4a73",
    "#fa4a47",
    "#bf1f2e",
];

export type StarInitProps = {
    y: number;
    x: number;
    radius: number;
};

/**
 * Each star needs to be removed and redrawn each 'tick'
 * @param stars - the stars to redraw
 * @param context - the context to draw the stars on
 * @param canvasDimensions - the dimensions of the canvas
 */
export function redrawStars(
    stars: Star[],
    context: CanvasRenderingContext2D,
    canvasDimensions: CanvasDimensions,
): void {
    context.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
    stars.forEach((star) => star.draw(context));
}

export default class Star {
    x: number; // the current x position of the streamer

    y: number; // the current y position of the streamer

    radius: number; // the radius (size) of the streamer

    color: string; // the colour of the streamer

    rotation: number; // the rotation of the streamer

    /**
     * Store the properties for reference within draw
     * @param init - the properties of the streamer
     */
    constructor(init: StarInitProps) {
        this.x = init.x;
        this.y = init.y;
        this.radius = init.radius;
        this.color = Star.getRandomColour();
        this.rotation = 0;
    }

    /**
     * Get a random colour
     * @returns - the colour of the streamer
     */
    static getRandomColour(): string {
        return colours[Math.floor(Math.random() * colours.length)];
    }

    /**
     * Draw the star as specified by the current properties
     * @param context -see https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D
     */
    draw(context: CanvasRenderingContext2D): void {
        context.save(); // save the default state prior to rotation

        context.translate(this.x, this.y); // to rotate the canvas
        context.rotate((this.rotation * Math.PI) / 180);
        context.translate(-this.x, -this.y);

        context.beginPath();
        // 5 point star from: https://stackoverflow.com/questions/14580033/algorithm-for-drawing-a-5-point-star
        const alpha = (2 * Math.PI) / 10; // the angle of rotation to draw the line
        let rotation;
        let omega;
        for (let i = 11; i !== 0; i -= 1) {
            // there's 11 points to the star, so we draw 11 lines
            rotation = (this.radius * ((i % 2) + 1)) / 2;
            omega = alpha * i;
            context.lineTo(rotation * Math.sin(omega) + this.x, rotation * Math.cos(omega) + this.y);
        }
        context.closePath();

        context.fillStyle = this.color;
        context.fill(); // then fill it with the colour specified

        context.restore(); // restore to original top/left translation
    }
}
