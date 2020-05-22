<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Test;

use Alaress\Sentry\ExceptionFilter;
use OutOfBoundsException;
use InvalidArgumentException;
use LogicException;
use OverflowException;
use PHPUnit\Framework\TestCase;
use LengthException;
use Sentry\Event;

/**
 * Class ExceptionFilterTest
 */
class ExceptionFilterTest extends TestCase
{
    public function getTestExclusionData(): iterable
    {
        // Either event or filter is empty: do not filter
        $event  = $this->buildEvent();
        $filter = $this->buildFilter();
        yield 'empty event, empty filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent(InvalidArgumentException::class);
        $filter = $this->buildFilter();
        yield 'non-empty event, empty filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent();
        $filter = $this->buildFilter(InvalidArgumentException::class);
        yield 'empty event, non-empty filter' => [$event, $filter, false];
        unset($event, $filter);


        // Any event class matches any filter class: filter
        $event  = $this->buildEvent(InvalidArgumentException::class);
        $filter = $this->buildFilter(InvalidArgumentException::class);
        yield 'single-class event matches single-class filter' => [$event, $filter, true];
        unset($event, $filter);

        $event  = $this->buildEvent(InvalidArgumentException::class);
        $filter = $this->buildFilter(InvalidArgumentException::class, OutOfBoundsException::class);
        yield 'single-class event matches multi-class filter' => [$event, $filter, true];
        unset($event, $filter);

        $event  = $this->buildEvent(InvalidArgumentException::class, OutOfBoundsException::class);
        $filter = $this->buildFilter(InvalidArgumentException::class);
        yield 'multi-class event matches multi-class filter' => [$event, $filter, true];
        unset($event, $filter);

        $event  = $this->buildEvent(InvalidArgumentException::class, OutOfBoundsException::class);
        $filter = $this->buildFilter(InvalidArgumentException::class, OutOfBoundsException::class);
        yield 'multi-class event matches multi-class filter' => [$event, $filter, true];
        unset($event, $filter);


        // No event classes match: do not filter
        $event  = $this->buildEvent(LengthException::class);
        $filter = $this->buildFilter(InvalidArgumentException::class);
        yield 'single-class event does not match single-class filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent(LengthException::class);
        $filter = $this->buildFilter(InvalidArgumentException::class, OutOfBoundsException::class);
        yield 'single-class event does not match multi-class filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent(LengthException::class, OutOfBoundsException::class);
        $filter = $this->buildFilter(InvalidArgumentException::class);
        yield 'multi-class event does not match multi-class filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent(LengthException::class, OverflowException::class);
        $filter = $this->buildFilter(InvalidArgumentException::class, OutOfBoundsException::class);
        yield 'multi-class event does not match multi-class filter' => [$event, $filter, false];
        unset($event, $filter);


        // Subclass and interface matching:
        // If the event has an exception which is a subclass of a filtered
        // exception, or implements a filtered interface, filter the event
        $event  = $this->buildEvent(InvalidArgumentException::class);
        $filter = $this->buildFilter(LogicException::class);
        yield 'event matches subclass of filter' => [$event, $filter, true];
        unset($event, $filter);

        $event  = $this->buildEvent(LogicException::class);
        $filter = $this->buildFilter(InvalidArgumentException::class);
        yield 'event matches superclass of filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent(Exception::class);
        $filter = $this->buildFilter(IException::class);
        yield 'event implements interface of filter' => [$event, $filter, true];
        unset($event, $filter);

        // An event's exceptions should always be concrete classes and not
        // interfaces, but test anyhow.
        $event  = $this->buildEvent(IException::class);
        $filter = $this->buildFilter(Exception::class);
        yield 'event contains interface implemented by filter' => [$event, $filter, false];
        unset($event, $filter);


        // Compare normalized class names (some provide with a leading \, others not)
        $className           = Exception::class;
        $normalizedClassName = trim($className, '\\');
        $event               = $this->buildEvent($normalizedClassName);
        $filter              = $this->buildFilter($className);
        yield 'event trimmed class name matches untrimmed filter class name' => [$event, $filter, true];
        unset($event, $filter);

        $event  = $this->buildEvent($className);
        $filter = $this->buildFilter($normalizedClassName);
        yield 'event untrimmed class name matches trimmed filter class name' => [$event, $filter, true];
        unset($event, $filter);

        $event  = $this->buildEvent($normalizedClassName);
        $filter = $this->buildFilter($normalizedClassName);
        yield 'event trimmed class name matches trimmed filter class name' => [$event, $filter, true];
        unset($event, $filter);


        // Compare things that aren't class names
        $nonExistentClassName1 = $className.'DoesNotExist1';
        $nonExistentClassName2 = $className.'DoesNotExist2';
        $event                 = $this->buildEvent($className);
        $filter                = $this->buildFilter($nonExistentClassName1);
        yield 'filter class does not exist' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent($className);
        $filter = $this->buildFilter($nonExistentClassName1, $className);
        yield 'one filter class does not exist, but another matches' => [$event, $filter, true];
        unset($event, $filter);

        $event  = $this->buildEvent($nonExistentClassName1);
        $filter = $this->buildFilter($className);
        yield 'event class does not exist' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent($nonExistentClassName2);
        $filter = $this->buildFilter($nonExistentClassName1);
        yield 'event and filter class do not exist' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent($nonExistentClassName1);
        $filter = $this->buildFilter($nonExistentClassName1);
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


    /**
     * Helper for building data that the filter will check.
     *
     * @param string ...$exceptionClasses
     * @return Event
     */
    private function buildEvent(string... $exceptionClasses): Event
    {
        $event = new Event();
        if (count($exceptionClasses)) {
            foreach ($exceptionClasses as $class) {
                $exceptions[] = [
                    'type' => $class,
                ];
            }
            $event->setExceptions($exceptions);
        }
        return $event;
    }


    /**
     * Helper for building the filter itself.
     *
     * @param string ...$exceptionClasses
     * @return ExceptionFilter
     */
    private function buildFilter(string... $exceptionClasses): ExceptionFilter
    {
        return new ExceptionFilter(,..$exceptionClasses);
    }
}
