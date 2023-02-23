<?php

/*
 * SPDX-License-Identifier: MIT or Apache-2.0
 */

declare(strict_types=1);

namespace MRR\PrettyPrinter\Token;

use MRR\PrettyPrinter\BreakType;
use MRR\PrettyPrinter\Token;
use MRR\PrettyPrinter\TokenType;

/**
 * The opening of a token group
 */
final class OpenToken extends Token
{
    public function __construct(
        /**
         * Indicate how the break should be rendered inside the group
         */
        public readonly BreakType $breakType = BreakType::Inconsistent,

        /**
         * Number of spaces of indentation
         */
        public readonly int $offset = 0,
    ) {
    }
}
