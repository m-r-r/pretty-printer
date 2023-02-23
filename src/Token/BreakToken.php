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
 * An optional line-break
 */
final class BreakToken extends Token
{
    public function __construct(
        public readonly int $blankSpace = 1,
        public readonly int $offset = 0,
    ) {
    }
}
