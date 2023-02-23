<?php

/*
 * SPDX-License-Identifier: MIT or Apache-2.0
 */

declare(strict_types=1);

namespace MRR\PrettyPrinter;

use ArrayAccess;
use Countable;
use RuntimeException;
use SplQueue;

/**
 * A FIFO queue with stable indices.
 *
 * @template T
 * @implements ArrayAccess<int, T>
 */
final class FixedIndexQueue implements Countable, ArrayAccess
{
    private int $offset = 0;

    /** @var SplQueue<T> */
    private readonly SplQueue $inner;

    public function __construct()
    {
        /** @var SplQueue<T> */
        $this->inner = new SplQueue();
    }

    /**
     * Checks whether the queue is empty.
     *
     * @psalm-assert-if-false T $this->first()
     * @psalm-assert-if-false T $this->last()
     */
    public function isEmpty(): bool
    {
        return $this->inner->isEmpty();
    }

    /**
     * Returs the first element of the queue
     * @return T
     */
    public function first(): mixed
    {
        return $this->inner->bottom();
    }

    /**
     * Returns the index of the first element of the queue
     */
    public function firstIndex(): int
    {
        return $this->offset;
    }

    /**
     * Returs the last element of the queue
     * @return T
     */
    public function last(): mixed
    {
        return $this->inner->top();
    }

    /**
     * Removes the first element element of the queue
     *
     * The indices of the following elements are conserved
     * @return T The removed element
     */
    public function popFirst(): mixed
    {
        $value = $this->inner->dequeue();
        $this->offset ++;
        return $value;
    }

    /**
     * Removes the last element element of the queue
     * @return T The removed element
     */
    public function popLast(): mixed
    {
        return $this->inner->pop();
    }

    /**
     * Pushes an element at the end of the queue
     * @param T $mixed
     * @return int The index of the inserted element
     */
    public function push(mixed $mixed): int
    {
        $this->inner->push($mixed);
        return $this->offset + $this->inner->count() - 1;
    }

    /**
     * Removes all the elements from the queue
     */
    public function clear(): void
    {
        while (!$this->isEmpty()) {
            $this->popFirst();
        }
        $this->offset = 0;
    }

    /**
     * @inheritDoc
     * @psalm-assert-if-true int $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->inner->offsetExists($offset - $this->offset);
    }

    /**
     * @inheritDoc
     *
     * @return T
     *
     * @param int|string $offset
     */
    public function offsetGet(mixed $offset): mixed
    {
        $this->assertIntIndex($offset);
        return $this->inner->offsetGet($offset - $this->offset);
    }

    /** @inheritDoc */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->assertIntIndex($offset);
        $this->inner->offsetSet($offset - $this->offset, $value);
    }

    /** @inheritDoc */
    public function offsetUnset(mixed $offset): void
    {
        throw new RuntimeException(sprintf(
            'Using %s is not supported. Use %2$s::popFirst(), %2$s::popLast() or %2$s::clear() instead.',
            __METHOD__,
            __CLASS__
        ));
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->inner);
    }

    /**
     * Throws an exception if the value is not an integer
     * @psalm-assert int $value
     * @throws RuntimeException
     */
    private function assertIntIndex(mixed $value): void
    {
        if (!is_int($value)) {
            throw new RuntimeException("Queue index must be an integer, got " . gettype($value));
        }
    }
}
