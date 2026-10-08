<?php

declare(strict_types=1);

namespace Tests\application\Stock\Interface\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form\NumericStringData;

/**
 * @internal
 */
#[CoversClass(NumericStringData::class)]
final class NumericStringDataTest extends TestCase
{
    public function testReturnsNumericStringForValidFieldData(): void
    {
        $form = $this->createMock(FormInterface::class);
        $field = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('get')->with('price')->willReturn($field);
        $field->expects(self::once())->method('getData')->willReturn(12.5);

        self::assertSame('12.5', new NumericStringData()->from($form, 'price'));
    }

    public function testRejectsNonNumericFieldData(): void
    {
        $form = $this->createMock(FormInterface::class);
        $field = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('get')->with('price')->willReturn($field);
        $field->expects(self::once())->method('getData')->willReturn('not a number');

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageIs('Expected numeric data for form field "price".');

        new NumericStringData()->from($form, 'price');
    }

    public function testRejectsUnsupportedFieldData(): void
    {
        $form = $this->createMock(FormInterface::class);
        $field = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('get')->with('price')->willReturn($field);
        $field->expects(self::once())->method('getData')->willReturn(null);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageIs('Expected numeric data for form field "price".');

        new NumericStringData()->from($form, 'price');
    }
}
