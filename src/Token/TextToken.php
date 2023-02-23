<?php

/*
 * SPDX-License-Identifier: MIT or Apache-2.0
 */

declare(strict_types=1);

namespace MRR\PrettyPrinter\Token;

use mb_strlen;
use MRR\PrettyPrinter\Token;
use MRR\PrettyPrinter\TokenType;

/**
 * A text token
 */
final class TextToken extends Token
{
    /**
     * The computed length of the text, in codepoints
     */
    public readonly int $length;

    public function __construct(
        /**
         * The text data
         */
        public readonly string $text,
    ) {
        $this->length = mb_strlen($text);
    }
}
