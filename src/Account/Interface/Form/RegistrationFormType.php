<?php

declare(strict_types=1);

namespace Xver\MiCartera\Frontend\Symfony\Account\Interface\Form;

use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Xver\PhpAuthCoreBundle\Account\Interface\Web\Form\RegistrationFormType as FormRegistrationFormType;
use Xver\MiCartera\Domain\Currency\Application\Query\CurrencyQuery;
use Xver\MiCartera\Domain\Currency\Domain\CurrencyPersistenceInterface;

final class RegistrationFormType extends FormRegistrationFormType implements DataMapperInterface
{
    public function __construct(
        private readonly CurrencyPersistenceInterface $currencyPersistence
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currenciesQuery = new CurrencyQuery(
            $this->currencyPersistence
        );
        $currencies = $currenciesQuery->all()->getCollection()->toArray();
        $builder
            ->add('currency', ChoiceType::class, [
                'choices' => $currencies,
                'label' => new TranslatableMessage(
                    'currency',
                    []
                ),
                'choice_value' => 'iso3',
                'choice_label' => 'iso3',
                'choice_translation_domain' => false,
            ])
            ->add('timezone', TimezoneType::class, [
                'label' => new TranslatableMessage(
                    'timezone',
                    []
                ),
                'input' => 'datetimezone',
                'data' => new \DateTimeZone(date_default_timezone_get()),
            ])
            ->setDataMapper($this)
        ;
        parent::buildForm($builder, $options);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // ...,
            'agreeTerms_label' => '',
        ]);

        // you can also define the allowed types, allowed values and
        // any other feature supported by the OptionsResolver component
        $resolver->setAllowedTypes('agreeTerms_label', 'string');
    }

    /**
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    #[\Override]
    public function mapDataToForms(mixed $viewData, \Traversable $forms): void {}

    /**
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    #[\Override]
    public function mapFormsToData(\Traversable $forms, mixed &$viewData): void {}
}
