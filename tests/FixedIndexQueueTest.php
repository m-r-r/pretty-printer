<?php

/*
 * SPDX-License-Identifier: MIT or Apache-2.0
 */

declare(strict_types=1);

namespace MRR\PrettyPrinter\Tests;

use MRR\PrettyPrinter\FixedIndexQueue;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers MRR\PrettyPrinter\FixedIndexQueue
 */
final class FixedIndexQueueTest extends TestCase
{
    public function testConstruct(): void
    {
        $queue = new FixedIndexQueue();
        $this->assertTrue($queue->isEmpty());
        $this->assertSame(0, $queue->count());
    }

    public function testPush(): void
    {
        $queue = new FixedIndexQueue();
        $i = $queue->push("foo");
        $j = $queue->push("bar");
        $k = $queue->push("baz");

        $this->assertFalse($queue->isEmpty());
        $this->assertSame(3, $queue->count());
        $this->assertSame("foo", $queue->first());
        $this->assertSame("foo", $queue[$i]);
        $this->assertSame("bar", $queue[$j]);
        $this->assertSame("baz", $queue[$k]);
        $this->assertSame("baz", $queue->last());
    }

    public function testPopFirst(): void
    {
        $queue = new FixedIndexQueue();
        $queue->push("foo");
        $j = $queue->push("bar");

        $this->assertSame("foo", $queue->popFirst());
        $this->assertSame(1, $queue->count());
        $this->assertSame("bar", $queue->first());
        $this->assertSame("bar", $queue[$j]);

        $this->assertSame("bar", $queue->popFirst());
        $this->assertSame(0, $queue->count());
        $this->assertTrue($queue->isEmpty());
    }

    public function testPopLast(): void
    {
        $queue = new FixedIndexQueue();
        $queue->push("foo");
        $queue->push("bar");

        $this->assertSame("bar", $queue->popLast());
        $this->assertSame(1, $queue->count());
        $this->assertSame("foo", $queue->last());

        $this->assertSame("foo", $queue->popLast());
        $this->assertSame(0, $queue->count());
        $this->assertTrue($queue->isEmpty());
    }

    public function testClear(): void
    {
        $queue = new FixedIndexQueue();
        $queue->push("foo");
        $queue->push("bar");
        $queue->clear();

        $this->assertTrue($queue->isEmpty());
        $this->assertSame(0, $queue->count());
    }

    public function testFirstIndex(): void
    {
        $queue = new FixedIndexQueue();
        $this->assertSame(0, $queue->firstIndex());

        $i = $queue->push("foo");
        $j = $queue->push("bar");
        $k = $queue->push("bar");

        $this->assertSame($i, $queue->firstIndex());
        $queue->popFirst();
        $this->assertSame($j, $queue->firstIndex());
        $queue->popFirst();
        $this->assertSame($k, $queue->firstIndex());
        $queue->popFirst();
        $this->assertSame($k + 1, $queue->firstIndex());
    }

    public function testOffsets(): void
    {
        $queue = new FixedIndexQueue();
        $queue->push("foo");
        $queue->push("bar");
        $queue->popFirst();

        $this->assertFalse($queue->offsetExists(0));
        $this->assertTrue($queue->offsetExists(1));
        $this->assertFalse($queue->offsetExists(2));

        $this->assertSame("bar", $queue->offsetGet(1));
        $queue->offsetSet(1, "baz");
        $this->assertSame("baz", $queue->offsetGet(1));
    }

    public function testOffsetUnsetException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^Using MRR\\\PrettyPrinter\\\FixedIndexQueue::offsetUnset is not supported\\./');
        $queue = new FixedIndexQueue();
        $queue->push("foo");
        $queue->offsetUnset(0);
    }

    public function testOffsetGetInvalidIndexTypeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Queue index must be an integer, got string');
        $queue = new FixedIndexQueue();
        $queue->push("foo");
        /** @psalm-suppress InvalidArgument */
        $queue->offsetGet((string) 0);
    }
}
