<?php

/*
 * SPDX-License-Identifier: MIT or Apache-2.0
 */

declare(strict_types=1);

namespace MRR\PrettyPrinter;

use MRR\PrettyPrinter\Printer;
use MRR\PrettyPrinter\Token;
use MRR\PrettyPrinter\Token\BreakToken;
use MRR\PrettyPrinter\Token\CloseToken;
use MRR\PrettyPrinter\Token\OpenToken;
use MRR\PrettyPrinter\Token\TextToken;

use const PHP_INT_MAX;

use RuntimeException;
use UnitEnum;

final class DocumentBuilder
{
    /** @var Token[] */
    private array $buffer = [];

    /** @type class-string-map<T as Token, array<string,Token>> */
    private array $cache = [];

    private int $openGroups = 0;

    /**
     * Create a text token
     * @return self
     */
    public function text(string $text, bool $cache = false): self
    {
        if ($cache) {
            $token = $this->cached(TextToken::class, $text);
        } else {
            $token = new TextToken($text);
        }
        return $this->push($token);
    }

    /**
     * @param string[] $parts
     * @return self
     */
    public function separateByBreaks(array $parts, int $offset=0): self
    {
        $last = count($parts) - 1;

        foreach (array_values($parts) as $i => $part) {
            $this->text($part);
            if ($i !== $last) {
                $this->break(1, $offset);
            }
        }

        return $this;
    }

    /**
     * Open a group of tokens
     * @param BreakType $breakType
     * @param int $indent
     * @return self
     */
    public function openGroup(BreakType $breakType = BreakType::Inconsistent, int $indent = 0): self
    {
        return $this->push($this->cached(OpenToken::class, $breakType, $indent));
    }

    public function closeGroup(): self
    {
        return $this->push($this->cached(CloseToken::class));
    }

    /**
     * @param int $indent
     * @return self
     */
    public function openConsistentGroup(int $indent = 0): self
    {
        return $this->openGroup(BreakType::Consistent, $indent);
    }

    /**
     * @param int $indent
     * @return self
     */
    public function openInconsistentGroup(int $indent = 0): self
    {
        return $this->openGroup(BreakType::Inconsistent, $indent);
    }

    public function break(int $blankSpace = 1, int $offset = 0): self
    {
        return $this->push($this->cached(BreakToken::class, $blankSpace, $offset));
    }

    public function zeroBreak(int $offset=0): self
    {
        return $this->break(0, $offset);
    }

    public function noBreakSpace(): self
    {
        return $this->text(' ', true);
    }

    public function hardBreak(int $offset = 0): self
    {
        return $this->break(1 << 16, $offset);
    }

    public function clearCache(): self
    {
        $this->cache = [];
        return $this;
    }

    /**
     * @return Token[]
     */
    public function toArray(): array
    {
        if ($this->openGroups !== 0) {
            throw new RuntimeException(sprintf(
                "Unbalanced groups : token %s is missing",
                $this->openGroups > 0
                    ? CloseToken::class
                    : OpenToken::class
            ));
        }
        return $this->buffer;
    }

    public function __clone()
    {
        $clone = new self();
        $clone->buffer = $this->buffer;
        $clone->cache = $this->cache;
        return $clone;
    }

    /**
     * Push the token at the end of the buffer
     * @return self
     */
    private function push(Token $token): self
    {
        $this->buffer[] = $token;
        if ($token instanceof OpenToken) {
            $this->openGroups ++;
        } elseif ($token instanceof CloseToken) {
            $this->openGroups --;
        }
        return $this;
    }

    /**
     * @psalm-template T as Token
     * @psalm-param class-string<T> $class
     * @psalm-param array<string|int|UnitEnum> $args
     * @psalm-suppress MixedInferredReturnType
     * @psalm-return T
     */
    private function cached(string $class, mixed ...$args): Token
    {
        $key = $args ? implode('-', array_map([self::class, 'valueToString'], $args)) : '';

        /** @type T $instance */
        /** @psalm-suppress MixedArrayAccess */
        $instance =& $this->cache[$class][$key];

        if (is_null($instance)) {
            /** @psalm-suppress UnsafeInstantiation */
            $instance = new $class(...$args);
        }

        /** @psalm-suppress MixedReturnStatement */
        return $instance;
    }

    /**
     * Convert a value into a string
     * @param string|int|UnitEnum $value
     */
    private static function valueToString(mixed $value): string
    {
        if ($value instanceof UnitEnum) {
            return (string) array_search($value, $value::cases(), true) ?: '0';
        } else {
            return (string)$value;
        }
    }
}
