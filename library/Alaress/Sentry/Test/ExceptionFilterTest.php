<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Test;

use Alaress\Sentry\ExceptionFilter;
use Imagine\Exception\OutOfBoundsException;
use InvalidArgumentException;
use LogicException;
use OverflowException;
use PHPUnit\Framework\TestCase;
use React\Promise\Exception\LengthException;
use Sentry\Event;

/**
 * Class ExceptionFilterTest
 */
class ExceptionFilterTest extends TestCase
{
    public function getTestExclusionData(): iterable
    {
        // Either event or filter is empty: do not filter
        $event  = new Event();
        $filter = new ExceptionFilter();
        yield 'empty event, empty filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter();
        $event->setExceptions([
            ['type' => InvalidArgumentException::class],
        ]);
        yield 'non-empty event, empty filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter(InvalidArgumentException::class);
        yield 'empty event, non-empty filter' => [$event, $filter, false];
        unset($event, $filter);


        // Any event class matches any filter class: filter
        $event  = new Event();
        $filter = new ExceptionFilter(InvalidArgumentException::class);
        $event->setExceptions([
            ['type' => InvalidArgumentException::class],
        ]);
        yield 'single-class event matches single-class filter' => [$event, $filter, true];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter(InvalidArgumentException::class, OutOfBoundsException::class);
        $event->setExceptions([
            ['type' => InvalidArgumentException::class],
        ]);
        yield 'single-class event matches multi-class filter' => [$event, $filter, true];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter(InvalidArgumentException::class);
        $event->setExceptions([
            ['type' => InvalidArgumentException::class],
            ['type' => OutOfBoundsException::class],
        ]);
        yield 'multi-class event matches multi-class filter' => [$event, $filter, true];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter(InvalidArgumentException::class, OutOfBoundsException::class);
        $event->setExceptions([
            ['type' => InvalidArgumentException::class],
            ['type' => OutOfBoundsException::class],
        ]);
        yield 'multi-class event matches multi-class filter' => [$event, $filter, true];
        unset($event, $filter);


        // No event classes match: do not filter
        $event  = new Event();
        $filter = new ExceptionFilter(InvalidArgumentException::class);
        $event->setExceptions([
            ['type' => LengthException::class],
        ]);
        yield 'single-class event does not match single-class filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter(InvalidArgumentException::class, OutOfBoundsException::class);
        $event->setExceptions([
            ['type' => LengthException::class],
        ]);
        yield 'single-class event does not match multi-class filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter(InvalidArgumentException::class);
        $event->setExceptions([
            ['type' => LengthException::class],
            ['type' => OutOfBoundsException::class],
        ]);
        yield 'multi-class event does not match multi-class filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter(InvalidArgumentException::class, OutOfBoundsException::class);
        $event->setExceptions([
            ['type' => LengthException::class],
            ['type' => OverflowException::class],
        ]);
        yield 'multi-class event does not match multi-class filter' => [$event, $filter, false];
        unset($event, $filter);


        // Subclass and interface matching:
        // If the event has an exception which is a subclass of a filtered
        // exception, or implements a filtered interface, filter the event
        $event  = new Event();
        $filter = new ExceptionFilter(LogicException::class);
        $event->setExceptions([
            ['type' => InvalidArgumentException::class],
        ]);
        yield 'event matches subclass of filter' => [$event, $filter, true];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter(InvalidArgumentException::class);
        $event->setExceptions([
            ['type' => LogicException::class],
        ]);
        yield 'event matches superclass of filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter(IException::class);
        $event->setExceptions([
            ['type' => Exception::class],
        ]);
        yield 'event implements interface of filter' => [$event, $filter, true];
        unset($event, $filter);

        // An event's exceptions should always be concrete classes and not
        // interfaces, but test anyhow.
        $event  = new Event();
        $filter = new ExceptionFilter(Exception::class);
        $event->setExceptions([
            ['type' => IException::class],
        ]);
        yield 'event contains interface implemented by filter' => [$event, $filter, false];
        unset($event, $filter);


        // Compare normalized class names (some provide with a leading \, others not)
        $className           = Exception::class;
        $normalizedClassName = trim($className, '\\');
        $event               = new Event();
        $filter              = new ExceptionFilter($className);
        $event->setExceptions([
            ['type' => $normalizedClassName],
        ]);
        yield 'event trimmed class name matches untrimmed filter class name' => [$event, $filter, true];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter($normalizedClassName);
        $event->setExceptions([
            ['type' => $className],
        ]);
        yield 'event untrimmed class name matches trimmed filter class name' => [$event, $filter, true];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter($normalizedClassName);
        $event->setExceptions([
            ['type' => $normalizedClassName],
        ]);
        yield 'event trimmed class name matches trimmed filter class name' => [$event, $filter, true];
        unset($event, $filter);


        // Compare things that aren't class names
        $nonExistentClassName1 = $className.'DoesNotExist1';
        $nonExistentClassName2 = $className.'DoesNotExist2';
        $event                 = new Event();
        $filter                = new ExceptionFilter($nonExistentClassName1);
        $event->setExceptions([
            ['type' => $className],
        ]);
        yield 'filter class does not exist' => [$event, $filter, false];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter($className);
        $event->setExceptions([
            ['type' => $nonExistentClassName1],
        ]);
        yield 'event class does not exist' => [$event, $filter, false];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter($nonExistentClassName1);
        $event->setExceptions([
            ['type' => $nonExistentClassName2],
        ]);
        yield 'event and filter class do not exist' => [$event, $filter, false];
        unset($event, $filter);

        $event  = new Event();
        $filter = new ExceptionFilter($nonExistentClassName1);
        $event->setExceptions([
            ['type' => $nonExistentClassName1],
        ]);
        yield 'event and filter class are same and do not exist' => [$event, $filter, false];
        unset($event, $filter);
    }


    /**
     * @covers \Alaress\Sentry\ExceptionFilter::__invoke
     * @dataProvider getTestExclusionData
     * @param Event           $event
     * @param ExceptionFilter $filter
     * @param bool            $expectedFiltered
     */
    public function testExclusion(Event $event, ExceptionFilter $filter, bool $expectedFiltered): void
    {
        $actual = $filter($event);
        if ($expectedFiltered) {
            $this->assertNull($actual);
        } else {
            $this->assertSame($event, $actual);
        }
    }
}
