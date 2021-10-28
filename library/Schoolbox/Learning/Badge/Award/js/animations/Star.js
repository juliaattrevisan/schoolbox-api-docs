
/**
 * The star used by the badge award animations
 * It's to be used within a <canvas></canvas> element 
 */

const alpha = (2 * Math.PI) / 10; //the angle of rotation to draw the line

export default class Star {

    /**
     * Store the properties for reference within draw
     * 
     * @param { Number }
     * @param { Number }
     * @param { String }
     * @param { Number }
     * @param { Number }
     */
    constructor (x, y, color, radius, rotation) {
        this.x = x;
        this.y = y;
        this.color = color;
        this.radius = radius;
        this.rotation = rotation;
    }

    /**
     * Draw the star as specified by the current properties
     * 
     * @param { CanvasRenderingContext2D } https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D
     */
    draw (context) {

        context.save(); //save the default state prior to rotation
  
        context.translate(this.x, this.y); //to rotate the canvas
        context.rotate(this.rotation * Math.PI / 180);
        context.translate(-this.x, -this.y);

        context.beginPath();
        for (var i = 11; i != 0; i--) { //there's 11 points to the star, so we draw 11 lines
            const r = this.radius*(i % 2 + 1)/2;
            const omega = alpha * i;
            context.lineTo((r * Math.sin(omega)) + this.x, (r * Math.cos(omega)) + this.y);
        }
        context.closePath();    
        context.fillStyle = this.color;
        context.fill(); //then fill it with the colour specified

        context.restore(); //restore to original top/left translation

    }

}