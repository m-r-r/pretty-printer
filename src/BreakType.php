<?php

/*
 * SPDX-License-Identifier: MIT or Apache-2.0
 */

declare(strict_types=1);

namespace MRR\PrettyPrinter;

/**
 * Consistency of line breaks
 *
 * Controls how the `BreakToken` tokens of the group will be rendered if the group
 * doesn't in a single line.
 */
enum BreakType
{
    /**
     * Each `BreakToken` in the group will be rendered as a line break.
     */
    case Consistent;

    /**
     * As much `BreakToken` as possible will be rendered as spaces on each line.
     */
    case Inconsistent;
}
