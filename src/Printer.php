<?php

/*
 * SPDX-License-Identifier: MIT or Apache-2.0
 */

declare(strict_types=1);

namespace MRR\PrettyPrinter;

use MRR\PrettyPrinter\FixedIndexQueue;
use MRR\PrettyPrinter\Token;
use MRR\PrettyPrinter\Token\BreakToken;
use MRR\PrettyPrinter\Token\CloseToken;
use MRR\PrettyPrinter\Token\OpenToken;
use MRR\PrettyPrinter\Token\TextToken;
use MRR\PrettyPrinter\TokenType;
use RuntimeException;
use SplDoublyLinkedList;
use SplQueue;
use SplStack;

/**
 * Renders a list of tokens as text.
 *
 * Adapted from <https://github.com/rust-lang/rust/blob/1.67.0/compiler/rustc_ast_pretty/src/pp.rs>
 */
final class Printer
{
    /**
     * Indicate that a token group fits in single line
     */
    private const FITS = 'FITS';

    private string $result = '';

    /** @var FixedIndexQueue<list{Token, int}> */
    private FixedIndexQueue $buffer;

    private int $leftTotal = 0;
    private int $rightTotal = 0;

    /** Amount of space remaining on the current line */
    private int $space;

    /** @var SplDoublyLinkedList<int> */
    private SplDoublyLinkedList $scanStack;

    /** @var SplStack<list{int, BreakType | self::FITS }> */
    private SplStack $printStack;

    private int $indent = 0;
    private int $pendingIndentation = 0;

    public function __construct(
        /**
         * Width of the document.
         */
        public readonly int $margin = 80,

        /**
         * Characters to use to render a line break.
         */
        public readonly string $endOfLine = "\n",
    ) {
        /** @var FixedIndexQueue<array{Token, int}> */
        $this->buffer = new FixedIndexQueue();
        $this->space = $margin;
        /** @var SplDoublyLinkedList<int> */
        $this->scanStack = new SplQueue();
        /** @var SplStack<list{int, BreakType | self::FITS}> */
        $this->printStack = new SplStack();
    }

    /**
     * Prints a list of token.
     *
     * The array *should* start with an `OpenToken` and end with a `CloseToken`.
     *
     * If this is not the case, an `OpenToken` with `BreakType::Consistent` will
     * added at the begining of the array, and the corresponding `CloseToken`
     * will be added at the end.
     *
     * @param Token[] $tokens
     * @return string The rendered tokens
     */
    public function print(array $tokens): string
    {
        if (!empty($tokens) && !(key($tokens) instanceof OpenToken)) {
            $tokens = [
                new OpenToken(BreakType::Consistent),
                ...$tokens,
                new CloseToken(),
            ];
        }
        foreach ($tokens as $token) {
            $this->scan($token);
        }
        return $this->finish();
    }

    /**
     * Print the tokens at the start of the buffer
     */
    private function advanceLeft(): void
    {
        while (!$this->buffer->isEmpty()) {
            // Skip the tokens with negative computed length
            [$token, $width] = $this->buffer->first();
            if ($width < 0) {
                break;
            }

            // Print the token
            $this->buffer->popFirst();

            // Update the number of characters written
            if ($token instanceof BreakToken) {
                $this->leftTotal += $token->blankSpace;
            } elseif ($token instanceof TextToken) {
                $this->leftTotal += $token->length;
            }

            $this->printToken($token, $width);
        }
    }

    /**
     * Empty the buffer
     */
    private function clearBuffer(): void
    {
        $this->leftTotal = $this->rightTotal = 1;
        $this->buffer->clear();
    }

    private function checkStack(): void
    {
        $depth = 0;
        while (!$this->scanStack->isEmpty()) {
            // Get the most recent item
            $index = $this->scanStack->top();

            [$token, $length] = $this->buffer[$index];

            switch ($token::class) {
                case OpenToken::class:
                    if ($depth === 0) {
                        return;
                    }

                    $this->scanStack->pop();
                    $this->buffer[$index] = [$token, $length + $this->rightTotal];
                    $depth --;
                    break;
                case CloseToken::class:
                    $this->scanStack->pop();
                    $this->buffer[$index] = [$token, 1];
                    $depth ++;
                    break;
                case BreakToken::class:
                    $this->scanStack->pop();
                    $this->buffer[$index] = [$token, $length + $this->rightTotal];
                    if ($depth === 0) {
                        return;
                    }
                    break;
                case TextToken::class:
                default:
                    throw new RuntimeException(sprintf("Unexpected token %s", $token::class));
            }
        }
    }

    private function checkBuffer(): void
    {
        while ((($this->rightTotal - $this->leftTotal) > $this->space) && !$this->buffer->isEmpty()) {
            if (!$this->scanStack->isEmpty()) {
                $index = $this->scanStack->bottom();
                if ($index === $this->buffer->firstIndex()) {
                    $this->scanStack->shift();
                    [$token] = $this->buffer[$index];
                    $this->buffer[$index] = [$token, PHP_INT_MAX];
                }
            }

            $this->advanceLeft();
        }
    }

    private function scan(Token $token): void
    {
        switch ($token::class) {
            case OpenToken::class:
                if ($this->scanStack->isEmpty()) {
                    $this->clearBuffer();
                }
                $index = $this->buffer->push([$token, - $this->rightTotal]);
                $this->scanStack->push($index);
                break;
            case CloseToken::class:
                if ($this->scanStack->isEmpty()) {
                    $this->printToken($token, 0);
                } else {
                    $index = $this->buffer->push([$token, -1]);
                    $this->scanStack->push($index);
                }
                break;
            case BreakToken::class:
                if ($this->scanStack->isEmpty()) {
                    $this->clearBuffer();
                } else {
                    $this->checkStack();
                }
                $index = $this->buffer->push([$token, - $this->rightTotal]);
                $this->scanStack->push($index);
                $this->rightTotal += $token->blankSpace;
                break;
            case TextToken::class:
                if ($this->scanStack->isEmpty()) {
                    $this->printToken($token, $token->length);
                } else {
                    $this->buffer->push([$token, $token->length]);
                    $this->rightTotal += $token->length;
                    $this->checkBuffer();
                }
                break;
            default:
                throw new RuntimeException(sprintf("Unsupported token class %s", $token::class));
        }
    }

    private function finish(): string
    {
        if (!$this->scanStack->isEmpty()) {
            $this->checkStack();
            $this->advanceLeft();
            $this->indent = $this->pendingIndentation = 0;
        }
        try {
            return $this->result;
        } finally {
            $this->result = '';
        }
    }

    /**
     * Prints a token of the computed length.
     */
    private function printToken(Token $token, int $length): void
    {
        switch ($token::class) {
            case OpenToken::class:
                if ($length > $this->space) {
                    // If the group doesn't fit the current line, push the current
                    // indentation level and the break type on the stack
                    $this->printStack->push([
                        $this->indent,
                        $token->breakType,
                    ]);
                    $this->indent += $token->offset;
                } else {
                    $this->printStack->push([0, self::FITS]);
                }
                break;
            case CloseToken::class:
                [$indent, $breakType] = $this->printStack->pop();
                if ($breakType !== self::FITS) {
                    $this->indent = $indent;
                }
                break;
            case BreakToken::class:
                $breakType = $this->printStack->top()[1];
                // Check whether the conditional break should be rendered as space
                if ($breakType === self::FITS || (
                    $breakType === BreakType::Inconsistent && $length <= $this->space
                )) {
                    // Output the right number of spaces and update the remaining space
                    $this->pendingIndentation += $token->blankSpace;
                    $this->space -= $token->blankSpace;
                } else {
                    // Otherwise, output a line break and the indentation
                    $this->pendingIndentation = $this->indent + $token->offset;
                    $this->space = $this->margin - $this->pendingIndentation;
                    $this->newLine();
                }
                break;
            case TextToken::class:
                assert($token->length >= $this->space, "The text doesn't fits the remaining space");
                $this->printIndent();
                $this->result .= $token->text;
                $this->space -= $token->length;
                break;
        }
    }

    /**
     * Outputs a newline followed by indentation.
     */
    private function newLine(): void
    {
        $this->result .= $this->endOfLine;
    }

    private function printIndent(): void
    {
        $this->result .= str_repeat(' ', $this->pendingIndentation);
        $this->pendingIndentation = 0;
    }
}
