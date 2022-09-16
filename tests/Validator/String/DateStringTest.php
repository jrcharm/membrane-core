<?php

declare(strict_types=1);

namespace Validator\String;

use Membrane\Result\Message;
use Membrane\Result\MessageSet;
use Membrane\Result\Result;
use Membrane\Validator\String\DateString;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Membrane\Validator\String\DateString
 * @uses   \Membrane\Result\Result
 * @uses   \Membrane\Result\MessageSet
 * @uses   \Membrane\Result\Message
 */
class DateStringTest extends TestCase
{
    public function DataSetsThatPass(): array
    {
        return [
            ['', ''],
            ['Y-m-d', '1970-01-01'],
            ['d-M-y', '20-feb-22'],
        ];
    }

    /**
     * @test
     * @dataProvider DataSetsThatPass
     */
    public function StringsThatMatchFormatReturnValid(string $format, string $input): void
    {
        $dateString = new DateString($format);
        $expected = Result::valid($input);

        $result = $dateString->validate($input);

        self::assertEquals($expected, $result);
    }

    public function DataSetsThatFail(): array
    {
        return [
            ['Y-m-d', '1990 June 15'],
            ['Y-m', '01-April'],
            ['Y', ''],
        ];
    }

    /**
     * @test
     * @dataProvider DataSetsThatFail
     */
    public function StringsThatDoNotMatchFormatReturnInvalid(string $format, string $input): void
    {
        $dateString = new DateString($format);
        $expectedMessage = new Message('String does not match the required format %s', [$format]);
        $expected = Result::invalid($input, new MessageSet(null, $expectedMessage));

        $result = $dateString->validate($input);

        self::assertEquals($expected, $result);
    }
}
