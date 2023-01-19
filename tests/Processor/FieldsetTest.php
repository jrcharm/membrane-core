<?php

declare(strict_types=1);

namespace Processor;

use Membrane\Exception\InvalidProcessorArguments;
use Membrane\Filter\Shape\Rename;
use Membrane\Filter\Type\ToFloat;
use Membrane\Filter\Type\ToInt;
use Membrane\Filter\Type\ToString;
use Membrane\Processor;
use Membrane\Processor\AfterSet;
use Membrane\Processor\BeforeSet;
use Membrane\Processor\DefaultProcessor;
use Membrane\Processor\Field;
use Membrane\Processor\FieldSet;
use Membrane\Result\FieldName;
use Membrane\Result\Message;
use Membrane\Result\MessageSet;
use Membrane\Result\Result;
use Membrane\Validator\Collection\Identical;
use Membrane\Validator\FieldSet\RequiredFields;
use Membrane\Validator\Type\IsFloat;
use Membrane\Validator\Utility\Fails;
use Membrane\Validator\Utility\Indifferent;
use Membrane\Validator\Utility\Passes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Membrane\Processor\FieldSet
 * @covers \Membrane\Exception\InvalidProcessorArguments
 * @uses   \Membrane\Validator\Collection\Identical
 * @uses   \Membrane\Filter\Shape\Rename
 * @uses   \Membrane\Filter\Type\ToFloat
 * @uses   \Membrane\Filter\Type\ToInt
 * @uses   \Membrane\Filter\Type\ToString
 * @uses   \Membrane\Processor\AfterSet
 * @uses   \Membrane\Processor\BeforeSet
 * @uses   \Membrane\Processor\DefaultProcessor
 * @uses   \Membrane\Result\Result
 * @uses   \Membrane\Result\MessageSet
 * @uses   \Membrane\Result\Message
 * @uses   \Membrane\Processor\Field
 * @uses   \Membrane\Result\FieldName
 * @uses   \Membrane\Validator\FieldSet\RequiredFields
 * @uses   \Membrane\Validator\Type\IsFloat
 * @uses   \Membrane\Validator\Utility\Fails
 * @uses   \Membrane\Validator\Utility\Indifferent
 * @uses   \Membrane\Validator\Utility\Passes
 */
class FieldsetTest extends TestCase
{
    public function dataSetsWithIncorrectValues(): array
    {
        $notArrayMessage = 'Value passed to FieldSet chain be an array, %s passed instead';
        $listMessage = 'Value passed to FieldSet chain must be an array, list passed instead';
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
     * @dataProvider dataSetsWithIncorrectValues
     */
    public function onlyAcceptsArrayValues(mixed $input, Message $expectedMessage): void
    {
        $expected = Result::invalid($input, new MessageSet(null, $expectedMessage));
        $fieldName = 'field to process';
        $fieldset = new FieldSet($fieldName, new Field(''));

        $result = $fieldset->process(new FieldName('parent field'), $input);

        self::assertEquals($expected, $result);
    }

    /** @test */
    public function onlyAcceptsOneBeforeSet(): void
    {
        $beforeSet = new BeforeSet();
        self::expectExceptionObject(InvalidProcessorArguments::multipleBeforeSetsInFieldSet());

        new FieldSet('field to process', $beforeSet, $beforeSet);
    }

    /** @test */
    public function onlyAcceptsOneAfterSet(): void
    {
        $afterSet = new AfterSet();
        self::expectExceptionObject(InvalidProcessorArguments::multipleAfterSetsInFieldSet());

        new FieldSet('field to process', $afterSet, $afterSet);
    }

    /** @test */
    public function onlyAcceptsOneDefaultField(): void
    {
        $defaultField = DefaultProcessor::fromFiltersAndValidators();
        self::expectExceptionObject(InvalidProcessorArguments::multipleDefaultProcessorsInFieldSet());

        new FieldSet('field to process', $defaultField, $defaultField);
    }

    /** @test */
    public function processesTest(): void
    {
        $fieldName = 'field to process';
        $fieldset = new FieldSet($fieldName);

        $output = $fieldset->processes();

        self::assertEquals($fieldName, $output);
    }

    /** @test */
    public function processMethodCallsFieldProcessesMethod(): void
    {
        $input = ['a' => 1, 'b' => 2, 'c' => 3];
        $field = self::createMock(Field::class);
        $field->expects(self::once())
            ->method('processes');
        $fieldset = new FieldSet('field to process', $field);

        $fieldset->process(new FieldName('Parent field'), $input);
    }

    /** @test */
    public function processCallsBeforeSetProcessOnceAndProcessesNever(): void
    {
        $input = ['a' => 1, 'b' => 2, 'c' => 3];
        $beforeSet = self::createMock(BeforeSet::class);
        $beforeSet->expects(self::never())
            ->method('processes');
        $beforeSet->expects(self::once())
            ->method('process')
            ->willReturn(Result::invalid($input));

        $fieldset = new FieldSet('field to process', $beforeSet);

        $fieldset->process(new FieldName('Parent field'), $input);
    }

    /** @test */
    public function processCallsAfterSetProcessOnceAndProcessesNever(): void
    {
        $input = ['a' => 1, 'b' => 2, 'c' => 3];
        $afterSet = self::createMock(AfterSet::class);
        $afterSet->expects(self::never())
            ->method('processes');
        $afterSet->expects(self::once())
            ->method('process')
            ->willReturn(Result::invalid($input));

        $fieldset = new FieldSet('field to process', $afterSet);

        $fieldset->process(new FieldName('Parent field'), $input);
    }

    public function dataSetsOfFields(): array
    {
        return [
            'No chain returns noResult' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::noResult(['a' => 1, 'b' => 2, 'c' => 3]),
            ],
            'Return valid result' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::valid(['a' => 1, 'b' => 2, 'c' => 3]),
                new Field('a', new Passes()),
            ],
            'Return noResult' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::noResult(['a' => 1, 'b' => 2, 'c' => 3]),
                new Field('b', new Indifferent()),
            ],
            'Return invalid result' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::invalid(['a' => 1, 'b' => 2, 'c' => 3],
                    new MessageSet(
                        new FieldName('c', 'parent field', 'field to process'),
                        new Message('I always fail', [])
                    )),
                new Field('c', new Fails()),
            ],
            'Field only performs processes on defined processes field' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::noResult(['a' => 1.0, 'b' => 2, 'c' => 3]),
                new Field('a', new ToFloat()),
            ],
            'DefaultProcessor only processes fields not processed by other Field Processors' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::noResult(['a' => 1.0, 'b' => '2', 'c' => '3']),
                new Field('a', new ToFloat()),
                DefaultProcessor::fromFiltersAndValidators(new ToString()),
            ],
            'Field processed values persist' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::valid(['a' => 1, 'b' => 2.0, 'c' => 3]),
                new Field('b', new ToFloat(), new IsFloat()),
            ],
            'Multiple Fields are accepted' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::valid(['a' => 1.0, 'b' => 2, 'c' => '3']),
                new Field('a', new ToFloat()),
                new Field('a', new IsFloat()),
                new Field('c', new ToString()),
            ],
            'BeforeSet processes before Field' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::noResult(['a' => 1, 'b' => 2, 'd' => 3.0]),
                new BeforeSet(new Rename('c', 'd')),
                new Field('d', new ToFloat()),
            ],
            'BeforeSet processes before AfterSet' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                Result::valid(['a' => 1, 'b' => 2, 'd' => 3]),
                new BeforeSet(new Rename('c', 'd')),
                new AfterSet(new RequiredFields('d')),
            ],
            'AfterSet processes after Field' => [
                ['a' => 1.0, 'b' => 1, 'c' => 1],
                Result::valid(['a' => 1, 'b' => 1, 'c' => 1]),
                new Field('a', new ToInt()),
                new AfterSet(new Identical()),
            ],
            'BeforeSet then Field then AfterSet' => [
                ['a' => 1, 'b' => 1, 'c' => 1.0],
                Result::valid(['a' => 1, 'b' => 1, 'd' => 1]),
                new BeforeSet(new Rename('c', 'd')),
                new Field('d', new ToInt()),
                new AfterSet(new Identical()),
            ],
        ];
    }


    /**
     * @test
     * @dataProvider dataSetsOfFields
     */
    public function processTest(array $input, Result $expected, Processor ...$chain): void
    {
        $fieldset = new FieldSet('field to process', ...$chain);

        $actual = $fieldset->process(new FieldName('parent field'), $input);

        self::assertEquals($expected, $actual);
        self::assertSame($expected->value, $actual->value);
    }
}
