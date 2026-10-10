<?php

declare(strict_types=1);

namespace Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Xver\MiCartera\Domain\Account\Application\Query\AccountQuery;
use Xver\MiCartera\Domain\Account\Domain\AccountPersistenceInterface;

final class CashDividendType extends AbstractType
{
    public function __construct(
        private readonly TokenStorageInterface $token,
        private readonly AccountPersistenceInterface $accountPersistence,
        private readonly ContainerBagInterface $params
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @psalm-suppress PossiblyNullReference */
        $identifier = $this->token->getToken()->getUser()->getUserIdentifier();
        $account = new AccountQuery($this->accountPersistence)->findByIdentifierOrThrowException($identifier);

        if ($options['include_datetime']) {
            $builder->add('datetime', DateTimeType::class, [
                'years' => range((int) date('Y') - 10, (int) date('Y')),
                'label' => new TranslatableMessage('dateWithTZ', ['timezone' => $account->getTimeZone()->getName()]),
                'input' => 'datetime',
                'with_seconds' => true,
                'widget' => 'single_text',
                'model_timezone' => $this->params->get('app.timezone'),
                'view_timezone' => $account->getTimeZone()->getName(),
            ]);
        }

        $formData = is_array($options['data'] ?? null) ? $options['data'] : [];
        $builder
            ->add('dividendPerShare', NumberType::class, [
                'scale' => 4,
                'rounding_mode' => \NumberFormatter::ROUND_HALFUP,
                'html5' => true,
                'attr' => ['step' => 0.0001],
                'label' => new TranslatableMessage('dividendPerShareWithCurrencySymbol', ['symbol' => $account->getCurrency()->getSymbol()]),
            ])
            ->add('expenses', NumberType::class, [
                'data' => $formData['expenses'] ?? 0,
                'scale' => $account->getCurrency()->getDecimals(),
                'rounding_mode' => \NumberFormatter::ROUND_HALFUP,
                'html5' => true,
                'attr' => ['step' => '1e-' . $account->getCurrency()->getDecimals()],
                'label' => new TranslatableMessage('expensesWithCurrencySymbol', ['symbol' => $account->getCurrency()->getSymbol()]),
            ])
            ->add('cmdSubmit', SubmitType::class, ['label' => new TranslatableMessage($options['submit_label'])]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'include_datetime' => true,
            'submit_label' => 'createCashDividend',
        ]);
    }
}
