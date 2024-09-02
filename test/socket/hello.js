#!/usr/bin/env nodejs

console.log("Hello Socket!");

var WebSocketClient = require("websocket").client;

var settings = {
    host: "127.0.0.1",
    port: "8080",
    path: "websocket",
    debug: false,
    prefetch: 50,
    sessionFile: "sessions.list",
    clientCount: 1,
    pauseAfter: 1,
    pauseFor: 0,
    busyTimeout: 2000,
    limit: 0,
};

process.argv.forEach(function (val, index, array) {
    if (index < 2) {
        return true;
    }

    var xpl = val.split("=");
    if (xpl.length == 2) {
        settings[xpl[0]] = xpl[1];
    }
    return true;
});

function attach(client, sessionId, start) {
    client.on("connectFailed", function (error) {
        console.log("CONNECTFAIL=" + sessionId + "|msg=" + error.toString());
    });

    client.on("connect", function (connection) {
        //    console.log('WebSocket client connected');

        var open = new Date();
        var session = sessionId;

        send({ subscribe: true });

        connection.on("error", function (error) {
            console.log("ERROR=" + sessionId + "|msg=" + error.toString());
        });

        connection.on("close", function () {
            console.log("DISCONNECT=" + sessionId);
        });

        connection.on("message", function (message) {
            if (message.type === "utf8") {
                data = JSON.parse(message.utf8Data);

                if (data.subscribe) {
                    pipeSubscribe(data.subscribe);
                }
                if (data.fetch) {
                    pipeFetch(data.fetch);
                }
                if (data.push) {
                    pipePush(data.push);
                }
                if (data.ack) {
                    pipeMarkAsRead(data.ack);
                }
                if (data.busy) {
                    pipeBusy(data.busy);
                }
                if (data.error) {
                    pipeError(data);
                }

                //          console.log("Received: '" + message.utf8Data + "'");
            }
        });

        function send(data) {
            if (client) {
                //                  if(settings.debug) { console.error('Sending data to server'); }
                connection.sendUTF(JSON.stringify(data));
            } else {
                if (settings.debug) {
                    console.error("No connection: not sending data");
                }
            }
        }

        /**
         * Response Event
         * @privileged
         * @param data
         */
        var pipeSubscribe = function (data) {
            if (settings.debug) {
                console.error("Message from server: parsing subscribe");
            }
            // don't try a fetch if the subscription came back false
            if (data === true) {
                send({ fetch: settings.prefetch });
            }
        };

        /**
         * Response Event
         * @privileged
         * @param data
         */
        var pipeFetch = function (data) {
            if (settings.debug) {
                console.error("Message from server: parsing fetch");
            }
            var close = new Date();

            console.log(
                "COMPLETE=" +
                    session +
                    "|wait=" +
                    (close - start) +
                    "|roundtrip=" +
                    (close - open) +
                    "|fetch=" +
                    Object.keys(data).length,
            );
        };

        /**
         * Response Event
         * @privileged
         * @param data
         */
        var pipePush = function (data) {
            if (settings.debug) {
                console.error("Message from server: parsing push");
                console.error(data);
            }
        };

        /**
         * Response Event
         * @privileged
         * @param data
         */
        var pipeMarkAsRead = function (data) {
            if (settings.debug) {
                console.error("Message from server: parsing mark_as_read");
            }
            console.log(data);
        };

        /**
         * Response Event
         * @privileged
         * @param data
         */
        var pipeBusy = function (data) {
            if (settings.debug) {
                console.error("Message from server: parsing busy");
            }

            var fn;

            switch (data) {
                case "subscribe":
                    fn = function () {
                        send({ subscribe: true });
                    };
                    break;
                case "fetch":
                    fn = function () {
                        send({ fetch: settings.prefetch });
                    };
                    break;
                case "ack":
                    fn = function () {};
                    break;
            }

            try {
                var t = parseInt(Math.random() * settings.busyTimeout);
                setTimeout(fn, parseInt(t));
            } catch (e) {
                if (settings.debug) {
                    console.error("Could not call " + fn);
                }
            }
        };

        /**
         * Response Event
         * @privileged
         * @param data
         */
        var pipeError = function (data) {
            if (settings.debug) {
                console.error("Message from server: parsing error");
            }
            switch (data.code) {
                case 401:
                    console.warn("Either no session, or does not match parent.");
                    attemptReconnect = false;
                    break;
                case 503:
                    console.warn("Service is unavailable: " + !!data.retry ? "cannot retry" : "can retry");
                    attemptReconnect = !!data.retry;
                    break;
            }
            connection.close();
        };
    });
}

var i;

var pauseAfter = parseInt(settings.pauseAfter);
var pauseFor = parseInt(settings.pauseFor);

var fs = require("fs"),
    readline = require("readline"),
    stream = require("stream");

var instream = fs.createReadStream(settings.sessionFile);
var outstream = new stream();
outstream.readable = true;
outstream.writable = true;

var rl = readline.createInterface({
    input: instream,
    output: outstream,
    terminal: false,
});

var j = 0;

rl.on("line", function (line) {
    ++j;

    if (settings.limit > 0 && j > settings.limit) {
        return true;
    }

    for (i = 0; i < settings.clientCount; i++) {
        var t = getTimeout((i + 1) * j, pauseFor, pauseAfter);
        setTimeout(function () {
            if (settings.debug) {
                console.error("Connection with session " + line);
            }
            var client = new WebSocketClient();
            attach(client, line, new Date());
            client.connect("ws://" + settings.host + ":" + settings.port + "/" + settings.path, null, null, {
                Cookie: "PHPSESSID=" + line,
            });
        }, t);
    }
});

function getTimeout(i, pauseFor, pauseAfter) {
    return i * pauseFor;
}
