# Schoolbox Load Testing

Schoolbox load tests currently uses Apache JMeter, with its websocket plugin.

## Getting Started

### Installing JMeter and plugins

Download the latest JMeter from https://jmeter.apache.org/download_jmeter.cgi,
and extract to a folder somewhere (from now on, this folder will be referred to
as `$JMETER_DIR`).

Download the JMeter Plugins Manager plugin from
https://jmeter-plugins.org/wiki/PluginsManager/ (direct link:
https://jmeter-plugins.org/get/), and copy the downloaded .jar file into the
`$JMETER_DIR/lib/ext` directory.

Start JMeter by executing `$JMETER_DIR/bin/jmeter` (either in an explorer
window or command line).

Select the "Options > Plugins Manager" menu option: this will open the plugins
manager.

Under "Available Plugins": search for "websocket", then select and install
"WebSocket Samplers by Peter Doornbosch"

> Why not the other one (WebSocket Sampler by Maciej Zaleski)?
> I just chose this one because it seems better documented?

## Configuring load test

Create file `$SCHOOLBOX_DIR/test/load/websocket/users.csv`, and enter a valid
username+password combination for https://vagrant.dev.schoolbox.cloud/,
separated by a comma; e.g.:

```
myUsername,myPassword
```

> This file is created so that passwords are not hardcoded within the JMeter
> test description.

## Running load test

From a command line (i.e. _not_ within JMeter):
```shell
$JMETER_DIR/bin/jmeter -n \
  -t $SCHOOLBOX_DIR/test/load/websocket/websocket.jmx \
  -l log.jtl
```
