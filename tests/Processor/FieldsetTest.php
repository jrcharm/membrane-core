<?php

declare(strict_types=1);

namespace Processor;

use Membrane\Filter;
use Membrane\Processor;
use Membrane\Processor\AfterSet;
use Membrane\Processor\BeforeSet;
use Membrane\Processor\Field;
use Membrane\Processor\Fieldset;
use Membrane\Result\Fieldname;
use Membrane\Result\Message;
use Membrane\Result\MessageSet;
use Membrane\Result\Result;
use Membrane\Validator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \Membrane\Processor\Fieldset
 * @uses   \Membrane\Processor\AfterSet
 * @uses   \Membrane\Processor\BeforeSet
 * @uses   \Membrane\Result\Result
 * @uses   \Membrane\Result\MessageSet
 * @uses   \Membrane\Result\Message
 * @uses   \Membrane\Processor\Field
 * @uses   \Membrane\Result\Fieldname
 */
class FieldsetTest extends TestCase
{
    public function DataSetsWithIncorrectValues(): array
    {
        $notArrayMessage = 'Value passed to FieldSet must be an array, %s passed instead';
        $listMessage = 'Value passed to FieldSet must be an array, list passed instead';
        return [
            [1, new Message($notArrayMessage, ['integer'])],
            [2.0, new Message($notArrayMessage, ['double'])],
            ['string', new Message($notArrayMessage, ['string'])],
            [true, new Message($notArrayMessage, ['boolean'])],
            [null, new Message($notArrayMessage, ['NULL'])],
            [['a', 'b', 'c'], new Message($listMessage, [])],
        ];
    }

    /**
     * @test
     * @dataProvider DataSetsWithIncorrectValues
     */
    public function OnlyAcceptsArrayValues(mixed $input, Message $expectedMessage): void
    {
        $expected = Result::invalid($input, new MessageSet(null, $expectedMessage));
        $fieldname = 'field to process';
        $fieldset = new FieldSet($fieldname);

        $result = $fieldset->process(new Fieldname('parent field'), $input);

        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function OnlyAcceptsOneBeforeSet(): void
    {
        $beforeSet = new BeforeSet();
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Only allowed one BeforeSet');

        new Fieldset('field to process', $beforeSet, $beforeSet);
    }

    /**
     * @test
     */
    public function OnlyAcceptsOneAfterSet(): void
    {
        $afterSet = new AfterSet();
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Only allowed one AfterSet');

        new Fieldset('field to process', $afterSet, $afterSet);
    }

    /**
     * @test
     */
    public function ProcessesTest(): void
    {
        $fieldname = 'field to process';
        $fieldset = new FieldSet($fieldname);

        $output = $fieldset->processes();

        self::assertEquals($fieldname, $output);
    }

    /**
     * @test
     */
    public function ProcessMethodWithNoChainReturnsNoResult(): void
    {
        $value = [];
        $expected = Result::noResult($value);
        $fieldset = new FieldSet('field to process');

        $result = $fieldset->process(new Fieldname('Parent field'), $value);

        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function ProcessMethodCallsFieldProcessesMethod(): void
    {
        $input = ['a' => 1, 'b' => 2, 'c' => 3];
        $field = self::createMock(Field::class);
        $field->expects(self::once())
            ->method('processes');
        $fieldset = new FieldSet('field to process', $field);

        $fieldset->process(new Fieldname('Parent field'), $input);
    }

    /**
     * @test
     */
    public function ProcessCallsBeforeSetProcessOnceAndProcessesNever(): void
    {
        $input = ['a' => 1, 'b' => 2, 'c' => 3];
        $beforeSet = self::createMock(BeforeSet::class);
        $beforeSet->expects(self::never())
            ->method('processes');
        $beforeSet->expects(self::once())
            ->method('process')
            ->willReturn(Result::invalid($input));

        $fieldset = new FieldSet('field to process', $beforeSet);

        $fieldset->process(new Fieldname('Parent field'), $input);
    }

    /**
     * @test
     */
    public function ProcessCallsAfterSetProcessOnceAndProcessesNever(): void
    {
        $input = ['a' => 1, 'b' => 2, 'c' => 3];
        $afterSet = self::createMock(AfterSet::class);
        $afterSet->expects(self::never())
            ->method('processes');
        $afterSet->expects(self::once())
            ->method('process')
            ->willReturn(Result::invalid($input));

        $fieldset = new FieldSet('field to process', $afterSet);

        $fieldset->process(new Fieldname('Parent field'), $input);
    }

    public function DataSetsOfFields(): array
    {
        $incrementFilter = new class implements Filter {
            public function filter(mixed $value): Result
            {
                return Result::noResult(++$value);
            }
        };

        $evenArrayFilter = new class implements Filter {
            public function filter(mixed $value): Result
            {
                foreach (array_keys($value) as $key) {
                    $value[$key] *= 2;
                }
                return Result::noResult($value);
            }
        };

        $evenValidator = new class implements Validator {
            public function validate(mixed $value): Result
            {
                if ($value % 2 !== 0) {
                    return Result::invalid($value, new MessageSet(
                            null,
                            new Message('not even', []))
                    );
                }
                return Result::valid($value);
            }
        };

        $evenArrayValidator = new class implements Validator {
            public function validate(mixed $value): Result
            {
                foreach (array_keys($value) as $key) {
                    if ($value[$key] % 2 !== 0) {
                        return Result::invalid($value, new MessageSet(
                                null,
                                new Message('not even', []))
                        );
                    }
                }
                return Result::valid($value);
            }
        };

        return [
            'Field only performs processes on defined processes field' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::noResult(['a' => 2, 'b' => 2, 'c' => 3]),
                new Field('a', $incrementFilter),
            ],
            'Field processed values persist' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::noResult(['a' => 1, 'b' => 4, 'c' => 3]),
                new Field('b', $incrementFilter, $incrementFilter),
            ],
            'Field processed can return valid results' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::valid(['a' => 1, 'b' => 2, 'c' => 3]),
                new Field('b', $evenValidator),
            ],
            'Field processed can return invalid results' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::invalid(['a' => 1, 'b' => 2, 'c' => 3], new MessageSet(
                    new Fieldname('a', 'parent field', 'field to process'),
                    new Message('not even', []))),
                new Field('a', $evenValidator),
            ],
            'Multiple Fields are accepted' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::valid(['a' => 2, 'b' => 3, 'c' => 3]),
                new Field('a', $incrementFilter),
                new Field('a', $evenValidator),
                new Field('b', $incrementFilter),
            ],
            'BeforeSetProcessesBeforeField' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::valid(['a' => 2, 'b' => 4, 'c' => 6]),
                new BeforeSet($evenArrayFilter),
                new Field('c', $evenValidator),
            ],
            'BeforeSetProcessesBeforeAfterSet' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::valid(['a' => 2, 'b' => 4, 'c' => 6]),
                new BeforeSet($evenArrayFilter),
                new AfterSet($evenArrayValidator),
            ],
            'AfterSetProcessesAfterField' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::valid(['a' => 2, 'b' => 2, 'c' => 4]),
                new Field('a', $incrementFilter),
                new Field('c', $incrementFilter),
                new AfterSet($evenArrayValidator),
            ],
            'BeforeSetThenFieldThenAfterSet' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::invalid(['a' => 2, 'b' => 5, 'c' => 6], new MessageSet(
                    new Fieldname('', 'parent field', 'field to process'),
                    new Message('not even', []))),
                new BeforeSet($evenArrayFilter),
                new Field('b', $incrementFilter),
                new AfterSet($evenArrayValidator),
            ],
        ];
    }


    /**
     * @test
     * @dataProvider DataSetsOfFields
     */
    public function ProcessTest(array $input, Result $expected, Processor ...$chain): void
    {
        $fieldset = new FieldSet('field to process', ...$chain);

        $result = $fieldset->process(new Fieldname('parent field'), $input);

        self::assertEquals($expected, $result);
    }

}