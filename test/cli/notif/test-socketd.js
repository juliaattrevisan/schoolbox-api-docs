#!/usr/bin/env nodejs

/**
 * This is a black-box test of the socket daemon. It fakes interactions of other daemons to test it in isolation.
 * To use:
 *  - Set up (or choose) a user with some listed messages to fetch ({id})
 *  - Create a remember-me session with that user and extract the remember token ({token})
 *  - Usage: REMEMBER={token} USER_ID={id} ./test-socket.js
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

"use strict";

// dependencies
const redis = require("redis").createClient();
const WebSocket = require("ws");
const JSON = require("jsonify");
const http = require("http");
const sleep = require("sleep");

// config constants
const remember = process.env.REMEMBER || "";
const subscript = `msg:vagrantx:${process.env.USER_ID || "230"}:push`;

const repeat = process.env.REPEAT || 500;
const delay = process.env.DELAY || 16; // 75/sec

let c = repeat;

for (let i = 0; i < repeat; ++i) {
    setTimeout(() => {
        ws();
    }, i * delay);
}

// main execution
function ws() {
    // connect to our own sock
    let ws = new WebSocket("ws://localhost:9081", {
        perMessageDeflate: false,
        headers: {
            cookie: `remember=${remember}`,
        },
    });

    let seen = {};
    let ackd = {};

    ws.on("open", () => {
        console.log("Connection established");
        ws.send(JSON.stringify({ subscribe: true }));
    });

    ws.on("message", (data) => {
        const payload = JSON.parse(data);
        const action = Object.keys(payload)[0];

        console.log(`Received ${action}`);

        if (payload.subscribe) {
            // STEP 1: subscription handshake
            ws.send(JSON.stringify({ fetch: 5 }));
        } else if (payload.fetch) {
            // STEP 2: anything we receive in fetch, broadcast it via redis for push
            seen = broadcastPush(payload.fetch, seen);
        } else if (payload.push) {
            // STEP 3: acknowledge the pushed messages
            ackd = acknowledgePush(payload.push, seen, ackd);
        } else if (payload.ack) {
            // STEP 4: verify the ACKs we have received are the ones we sent
            verifyAck(payload.ack, ackd);
        }
    });

    ws.on("close", () => {
        console.log("Connection terminated");
        subC();
    });

    ws.on("error", () => {});
}

/**
 * @param {object} messages
 * @param {object} seen
 * @return {object}
 */
function broadcastPush(messages, seen) {
    for (let msgId in messages) {
        if (messages.hasOwnProperty(msgId)) {
            console.log(`Faking a push of ${msgId}: ${messages[msgId].href}  "${messages[msgId].body}"`);

            redis.publish(
                subscript,
                JSON.stringify({
                    message: JSON.stringify(messages[msgId]),
                }),
            );

            seen[msgId] = true;
        }
    }

    return seen;
}

/**
 * @param {object} messages
 * @param {object} seen
 * @param {object} ackd
 * @return {object}
 */
function acknowledgePush(messages, seen, ackd) {
    for (let msgId in messages) {
        if (msgId in seen) {
            if (messages.hasOwnProperty(msgId)) {
                console.log(`Acknowledging ${msgId}: ${messages[msgId].href}  "${messages[msgId].body}"`);

                ws.send(
                    JSON.stringify({
                        ack: { msgId },
                    }),
                );

                ackd[msgId] = true;
            }
        } else {
            console.error(`Not acknowledging ${msgId}: we haven't seen it before...`);
            subC();
        }
    }

    return ackd;
}

/**
 * @param {object} messages
 * @param {object} ackd
 */
function verifyAck(messages, ackd) {
    for (let msgId in messages) {
        if (msgId in ackd) {
            if (messages.hasOwnProperty(msgId)) {
                console.log(`ACK of ${msgId} received`);
            }
        } else {
            console.log(`ACK of ${msgId} unexpected`);
        }
    }
}

function subC() {
    --c;
    console.log("c is now %d", c);
    if (c <= 0) {
        process.exit(1);
    }
}
