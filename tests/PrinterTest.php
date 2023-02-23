<?php

/*
 * SPDX-License-Identifier: MIT or Apache-2.0
 */

declare(strict_types=1);

namespace MRR\PrettyPrinter\Tests;

use MRR\PrettyPrinter\DocumentBuilder;
use MRR\PrettyPrinter\Printer;
use MRR\PrettyPrinter\Token;
use MRR\PrettyPrinter\Token\BreakToken;
use MRR\PrettyPrinter\Token\CloseToken;
use MRR\PrettyPrinter\Token\OpenToken;
use MRR\PrettyPrinter\Token\TextToken;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Spatie\Snapshots\MatchesSnapshots;

/**
 * @psalm-type CtorArgs = list{} | list{int} | list{int, string}
 * @covers MRR\PrettyPrinter\Printer
 * @covers MRR\PrettyPrinter\DocumentBuilder
 * @uses MRR\PrettyPrinter\FixedIndexQueue
 * @uses MRR\PrettyPrinter\Token\BreakToken
 * @uses MRR\PrettyPrinter\Token\CloseToken
 * @uses MRR\PrettyPrinter\Token\OpenToken
 * @uses MRR\PrettyPrinter\Token\TextToken
 */
final class PrinterTest extends TestCase
{
    use MatchesSnapshots;

    /**
     * @return iterable<string, list{Token[], string} | list{Token[], string, CtorArgs}>
     */
    public function provideTestPrint(): iterable
    {
        return [
            'empty document' => [[], ''],

            'text token' => [[new TextToken('foo')], 'foo'],
            'break token' => [[new BreakToken(), new TextToken('.')], ' .'],
            'trailing break token' => [[new BreakToken()], ''],

            'short line' => [
                [new TextToken('foo'), new BreakToken(), new TextToken('bar')],
                'foo bar',
                [8]
            ],
            'long line' => [
                [new TextToken('foo'), new BreakToken(), new TextToken('bar')],
                "foo\nbar",
                [6]
            ],
        ];
    }

    public function testFormatPhp(): void
    {
        $printer = new Printer();
        $tokens = (new DocumentBuilder())
            ->openConsistentGroup()
            ->text('function sumIntegers(int $a, int $b)')

            ->hardBreak()
            ->text('{')

            ->hardBreak(4)
            ->text('return $a + $b;')

            ->hardBreak()
            ->text('}')
            ->closeGroup()

            ->toArray();

        $this->assertMatchesTextSnapshot($printer->print($tokens));

        $tokens = (new DocumentBuilder())
            ->openConsistentGroup()
            ->text('protected function configure(): void')

            ->hardBreak()
            ->text('{')

            ->hardBreak(4)
            ->text('$this')

            ->openConsistentGroup(8)

            ->zeroBreak()
            ->text('->addArgument(')

            ->openConsistentGroup()
            ->zeroBreak(4)
            ->separateByBreaks([
                "'name',",
                "InputArgument::REQUIRED,",
                "'Who do you want to greet?'"
            ], 4)
            ->zeroBreak()
            ->text(')')
            ->closeGroup()

            ->zeroBreak()
            ->text('->addArgument(')
            ->openConsistentGroup()
            ->zeroBreak(4)
            ->separateByBreaks(["'last_name',", "InputArgument::OPTIONAL,", "'Your last name?'"], 4)
            ->zeroBreak()
            ->text(')')
            ->closeGroup()

            ->closeGroup()
            ->zeroBreak(4)
            ->text(';')

            ->hardBreak()
            ->text('}')
            ->closeGroup()

            ->toArray();

        $output = join("\n\n", array_map(
            fn ($width) => "// width : $width\n" . (new Printer($width))->print($tokens),
            [40, 80, 120],
        ));
        $this->assertMatchesTextSnapshot($output);
    }

    public function testHtml(): void
    {
        $builder = new DocumentBuilder();

        /**
         * @var Closure(string, ...mixed): void
         */
        $xml = function (
            string $tagName,
            mixed ...$children
        ) use (
            $builder,
            &$xml
        ): void {
            /** @var array<string, string> */
            $attrs = [];

            if (isset($children[0]) && is_array($children[0]) && is_string(array_key_first($children[0]))) {
                /** @var array<string, string> */
                $attrs = array_shift($children);
            }

            $builder->openConsistentGroup();
            $builder->openConsistentGroup();
            $builder->text("<$tagName", true);

            if ($attrs) {
                $attrPairs = [];
                foreach ($attrs as $name => $value) {
                    $attrPairs[] = "$name=\"$value\"";
                }
                $builder->break(1, 2);
                $builder->separateByBreaks($attrPairs, 2);
                $builder->zeroBreak();
            } elseif (!$children) {
                $builder->text(" ", true);
            }

            if (!$children) {
                $builder->text("/>", true);
            } else {
                $builder->text(">", true);
            }
            $builder->closeGroup();

            if ($children) {
                $builder->zeroBreak(2);
                $builder->openConsistentGroup(2);
                /** @var mixed $child */
                foreach (array_values($children) as $i => $child) {
                    if ($i > 0) {
                        $builder->zeroBreak();
                    }
                    if (is_string($child)) {
                        $builder->openInconsistentGroup();
                        $words = preg_split('/\s+/', $child);
                        $builder->separateByBreaks($words);
                        $builder->closeGroup();
                    } elseif (is_array($child) && count($child) >= 1) {
                        /** @psalm-suppress MixedFunctionCall */
                        $xml(...$child);
                    } else {
                        throw new RuntimeException("Invalid children");
                    }
                }
                $builder->closeGroup();
                $builder->zeroBreak();
                $builder->text("</$tagName>", true);
            }

            $builder->closeGroup();
        };

        $builder
            ->openConsistentGroup()
            ->text('<!DOCTYPE html>')
            ->hardBreak();

        $xml(
            'html',
            ['lang' => 'en'],
            ['head',
                ['meta', ['charset' => 'utf-8']],
                ['title', 'Title of the page'],
                ['link', [
                    'rel' => 'stylesheet',
                    'type' => 'text/css',
                    'href' => 'style.css',
                ]],
            ],
            ['body',
                ['header',
                    ['h1', 'Title of the page'],
                ],
                ['main',
                    ['p', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec venenatis cursus ante nec porttitor. Morbi quis gravida elit. Vivamus tristique vulputate libero, vel consequat ex faucibus ut. Vestibulum gravida orci vel porttitor finibus. Duis posuere euismod turpis, sed faucibus mauris condimentum feugiat. Proin mauris elit, venenatis sit amet molestie quis, commodo vitae turpis. Fusce porttitor malesuada enim. Quisque eget arcu ex. Sed eget nisi et mi commodo laoreet id id quam. Ut sodales consectetur purus, quis ultricies orci dignissim ut. Phasellus lobortis, ipsum rutrum ultricies tempor, felis augue imperdiet ipsum, eget tempor ex libero in lorem. Etiam aliquet mollis enim, id ultricies ipsum commodo eget. Quisque condimentum volutpat finibus. Ut velit arcu, maximus ac risus vel, cursus laoreet leo.'],

                    ['p', 'Duis vitae est felis. Maecenas ullamcorper sem orci, ac commodo enim cursus id. Sed ullamcorper vitae lorem vitae faucibus. Nulla facilisi. Quisque rutrum magna tellus, vitae pulvinar elit venenatis a. Aenean lectus quam, mollis non pretium eget, hendrerit eget ipsum. Fusce sed egestas elit. Ut malesuada venenatis euismod. Vestibulum mollis finibus laoreet. Integer et velit est. In nec faucibus mi. Maecenas ultrices, mi a feugiat sagittis, nibh nisi dictum dui, ac molestie dolor magna in urna.'],

                    ['p', 'Aliquam blandit turpis non lacinia sagittis. Duis gravida, dui quis mattis facilisis, felis augue placerat velit, non finibus dui mi non mauris. Donec volutpat quis urna sit amet ultrices. Morbi sed posuere lorem. Morbi convallis vel quam a pretium. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed sollicitudin facilisis leo, vitae lacinia risus sodales id. Aenean accumsan sit amet ligula eu volutpat. Aenean aliquet cursus quam, vitae vestibulum dolor pretium sit amet.'],

                    ['p', 'Donec fermentum imperdiet interdum. Nunc vitae mi sed lorem tristique placerat. Vestibulum at accumsan ante, non scelerisque orci. Curabitur vulputate nibh eu leo viverra hendrerit. Nam vitae massa quis mauris consectetur lobortis et non nunc. Vivamus sollicitudin varius faucibus. Sed suscipit in tellus a ornare. Maecenas rutrum dictum bibendum. Phasellus vel quam quam. Integer faucibus iaculis tristique. Praesent tempor pellentesque libero non congue. Donec ut risus suscipit, consequat neque vitae, ultricies quam. Maecenas non pellentesque ipsum. Praesent ac urna venenatis, consectetur erat in, facilisis tellus. Donec id tellus ac mauris sollicitudin dictum. Aenean sodales erat eu mi tincidunt ornare.'],
                    ['p', 'Etiam quis enim ac dolor fermentum feugiat. Maecenas a laoreet nisl, vitae mattis sapien. Phasellus consequat magna vel iaculis viverra. Vestibulum id hendrerit nunc, eget efficitur nibh. Sed at massa quis nisl sagittis feugiat. Donec massa nisi, tincidunt at sodales in, vehicula in massa. Pellentesque euismod dolor id fermentum ullamcorper. '],
                ]
            ],
            ['footer',
                ['p', 'Footer text']
            ]
        );

        $builder->closeGroup();

        $tokens = $builder->toArray();

        foreach ([10, 30, 80] as $width) {
            $printer = new Printer($width);
            $this->assertMatchesTextSnapshot($printer->print($tokens));
        }
    }

    /**
     * @dataProvider provideTestPrint
     * @param Token[] $tokens
     * @param string $expected
     * @param CtorArgs $args
     */
    public function testPrint(array $tokens, string $expected, array $args=[]): void
    {
        $printer = new Printer(...$args);
        $this->assertSame($expected, $printer->print($tokens));
    }
}
