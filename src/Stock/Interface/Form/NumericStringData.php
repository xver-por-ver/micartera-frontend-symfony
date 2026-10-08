<?php

declare(strict_types=1);

namespace Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form;

use Symfony\Component\Form\FormInterface;

final class NumericStringData
{
    /** @return numeric-string */
    public function from(FormInterface $form, string $field): string
    {
        $value = $form->get($field)->getData();

        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new \UnexpectedValueException(sprintf('Expected numeric data for form field "%s".', $field));
        }

        $value = (string) $value;

        if (!is_numeric($value)) {
            throw new \UnexpectedValueException(sprintf('Expected numeric data for form field "%s".', $field));
        }

        return $value;
    }
}
