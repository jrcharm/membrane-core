<?php

declare(strict_types=1);

namespace Filter\Type;

use Membrane\Filter\Type\ToInt;
use Membrane\Result\Message;
use Membrane\Result\MessageSet;
use Membrane\Result\Result;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Membrane\Filter\Type\ToInt
 */
class ToIntTest extends TestCase
{
    public function DataSetsWithAcceptableInputs(): array
    {
        return [
            [1, 1],
            [1.23, 1],
            ['123', 123],
            [true, 1],
            [null, 0],
        ];
    }

    /**
     * @test
     * @dataProvider DataSetsWithAcceptableInputs
     */
    public function AcceptableTypesReturnIntegerValues($input, $expectedValue): void
    {
        $toInt = new ToInt();
        $expected = Result::noResult($expectedValue);

        $result = $toInt->filter($input);

        self::assertSame($expected->value, $result->value);
        self::assertEquals($expected->result, $result->result);
    }

    public function DataSetsWithUnacceptableInputs(): array
    {
        $message = 'ToInt filter only accepts null or scalar values, %s given';
        $class = new class () {
        };

        return [
            [
                'non-numeric string',
                new Message('ToInt filter only accepts numeric strings', [])
            ],
            [
                ['an', 'array'],
                new Message($message, ['array'])
            ],
            [
                ['a' => 'list'],
                new Message($message, ['array'])
            ],
            [
                $class,
                new Message($message, ['object'])
            ],
        ];
    }

    /**
     * @test
     * @dataProvider DataSetsWithUnacceptableInputs
     */
    public function UnacceptableTypesReturnInvalid($input, $expectedMessage): void
    {
        $toInt = new ToInt();
        $expected = Result::invalid($input, new MessageSet(null, $expectedMessage));

        $result = $toInt->filter($input);

        self::assertEquals($expected, $result);
    }
}