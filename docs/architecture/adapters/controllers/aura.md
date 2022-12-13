# Aura Controller

Schoolbox's controllers are created by [modules](../../configuration/modules.md),
in the [Aura variant of the standard web kernel](../kernels/web/standard.md#aura).

All controllers inherit the `\Alaress\Web\Controller\Base` abstract class.

## Controller Actions

A controller action is any method which may be invoked via an Aura request:
technically, any public method of the controller may be an action.

Controller actions should be as lightweight as possible: they should only
parse data from the request, and pass off any work to another component, e.g.:
- a rendering engine for outputting content
- a mapper for persisting data
- a more sophisticated class structure for validating input, translating to
  another format, etc.

In other words: controllers should pass off actions to other components, which:
- may interact with
  [other inputs and outputs](../../README.md#other-inputs-and-outputs)
- will create some sort of [HTTP response](../stdout/http-response/README.md)
  output
