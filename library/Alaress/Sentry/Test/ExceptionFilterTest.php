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
use Sentry\ExceptionDataBag;
use Throwable;

/**
 * Class ExceptionFilterTest
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Alaress\Sentry\ExceptionFilter::class)]
class ExceptionFilterTest extends TestCase
{
    public function getTestExclusionData(): iterable
    {
        // Either event or filter is empty: do not filter
        $event  = $this->buildEvent();
        $filter = $this->buildFilter();
        yield 'empty event, empty filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent(new InvalidArgumentException());
        $filter = $this->buildFilter();
        yield 'non-empty event, empty filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent();
        $filter = $this->buildFilter(InvalidArgumentException::class);
        yield 'empty event, non-empty filter' => [$event, $filter, false];
        unset($event, $filter);


        // Any event class matches any filter class: filter
        $event  = $this->buildEvent(new InvalidArgumentException());
        $filter = $this->buildFilter(InvalidArgumentException::class);
        yield 'single-class event matches single-class filter' => [$event, $filter, true];
        unset($event, $filter);

        $event  = $this->buildEvent(new InvalidArgumentException());
        $filter = $this->buildFilter(InvalidArgumentException::class, OutOfBoundsException::class);
        yield 'single-class event matches multi-class filter' => [$event, $filter, true];
        unset($event, $filter);

        $event  = $this->buildEvent(new InvalidArgumentException(), new OutOfBoundsException());
        $filter = $this->buildFilter(InvalidArgumentException::class);
        yield 'multi-class event matches multi-class filter' => [$event, $filter, true];
        unset($event, $filter);

        $event  = $this->buildEvent(new InvalidArgumentException(), new OutOfBoundsException());
        $filter = $this->buildFilter(InvalidArgumentException::class, OutOfBoundsException::class);
        yield 'multi-class event matches multi-class filter2' => [$event, $filter, true];
        unset($event, $filter);


        // No event classes match: do not filter
        $event  = $this->buildEvent(new LengthException());
        $filter = $this->buildFilter(InvalidArgumentException::class);
        yield 'single-class event does not match single-class filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent(new LengthException());
        $filter = $this->buildFilter(InvalidArgumentException::class, OutOfBoundsException::class);
        yield 'single-class event does not match multi-class filter2' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent(new LengthException(), new OutOfBoundsException());
        $filter = $this->buildFilter(InvalidArgumentException::class);
        yield 'multi-class event does not match multi-class filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent(new LengthException(), new OverflowException());
        $filter = $this->buildFilter(InvalidArgumentException::class, OutOfBoundsException::class);
        yield 'multi-class event does not match multi-class filter2' => [$event, $filter, false];
        unset($event, $filter);


        // Subclass and interface matching:
        // If the event has an exception which is a subclass of a filtered
        // exception, or implements a filtered interface, filter the event
        $event  = $this->buildEvent(new InvalidArgumentException());
        $filter = $this->buildFilter(LogicException::class);
        yield 'event matches subclass of filter' => [$event, $filter, true];
        unset($event, $filter);

        $event  = $this->buildEvent(new LogicException());
        $filter = $this->buildFilter(InvalidArgumentException::class);
        yield 'event matches superclass of filter' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent(new Exception());
        $filter = $this->buildFilter(IException::class);
        yield 'event implements interface of filter' => [$event, $filter, true];
        unset($event, $filter);


        // Compare things that aren't class names
        $className             = Exception::class;
        $nonExistentClassName1 = $className . 'DoesNotExist1';
        $event                 = $this->buildEvent(new Exception());
        $filter                = $this->buildFilter($nonExistentClassName1);
        yield 'filter class does not exist' => [$event, $filter, false];
        unset($event, $filter);

        $event  = $this->buildEvent(new Exception());
        $filter = $this->buildFilter($nonExistentClassName1, $className);
        yield 'one filter class does not exist, but another matches' => [$event, $filter, true];
        unset($event, $filter);
    }


    /**
     * @param Event           $event
     * @param ExceptionFilter $filter
     * @param bool            $expectedFiltered
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestExclusionData')]
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
     * @param Throwable ...$exceptions
     * @return Event
     */
    private function buildEvent(Throwable ...$exceptions): Event
    {
        $event    = Event::createEvent();
        $dataBags = [];
        if (count($exceptions)) {
            foreach ($exceptions as $exception) {
                $dataBags[] = new ExceptionDataBag($exception);
            }
            $event->setExceptions($dataBags);
        }
        return $event;
    }


    /**
     * Helper for building the filter itself.
     *
     * @param string ...$exceptionClasses
     * @return ExceptionFilter
     */
    private function buildFilter(string ...$exceptionClasses): ExceptionFilter
    {
        return new ExceptionFilter(...$exceptionClasses);
    }
}
