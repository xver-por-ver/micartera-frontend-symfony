<?php

declare(strict_types=1);

namespace Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Xver\MiCartera\Domain\Account\Application\Query\AccountQuery;
use Xver\MiCartera\Domain\Account\Domain\AccountPersistenceInterface;

final class StockOperateType extends AbstractType implements DataMapperInterface
{
    private readonly string $accountIdentifier;

    public function __construct(
        TokenStorageInterface $token,
        private readonly AccountPersistenceInterface $accountPersistence,
        private readonly ContainerBagInterface $params
    ) {
        /** @psalm-suppress PossiblyNullReference */
        $this->accountIdentifier = $token->getToken()->getUser()->getUserIdentifier();
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $accountQuery = new AccountQuery($this->accountPersistence);
        $account = $accountQuery->findByIdentifierOrThrowException($this->accountIdentifier);
        $submitLabel = (
            isset($options['data']['type']) && 0 === $options['data']['type']
            ? 'purchase'
            : 'sell'
        );
        $builder
            ->add('datetime', DateTimeType::class, [
                'years' => range((int) date('Y') - 10, (int) date('Y')),
                'label' => new TranslatableMessage(
                    'dateWithTZ',
                    ['timezone' => $account->getTimeZone()->getName()]
                ),
                'input' => 'datetime',
                'with_seconds' => true,
                'widget' => 'single_text',
                'model_timezone' => $this->params->get('app.timezone'),
                'view_timezone' => $account->getTimeZone()->getName(),
            ])
            ->add('amount', null, [
                'label' => new TranslatableMessage(
                    'amount',
                    []
                ),
            ])
            ->add('price', NumberType::class, [
                'scale' => 4,
                'rounding_mode' => \NumberFormatter::ROUND_HALFUP,
                'html5' => true,
                'attr' => ['step' => 0.0001],
                'label' => new TranslatableMessage(
                    'priceWithCurrencySymbol',
                    ['symbol' => $account->getCurrency()->getSymbol()]
                ),
            ])
            ->add('expenses', NumberType::class, [
                'scale' => $account->getCurrency()->getDecimals(),
                'rounding_mode' => \NumberFormatter::ROUND_HALFUP,
                'html5' => true,
                'attr' => ['step' => '1e-' . $account->getCurrency()->getDecimals()],
                'label' => new TranslatableMessage(
                    'expensesWithCurrencySymbol',
                    ['symbol' => $account->getCurrency()->getSymbol()]
                ),
            ])
            ->add('refererPage', HiddenType::class)
            ->add('cmdSubmit', SubmitType::class, [
                'label' => new TranslatableMessage(
                    $submitLabel,
                    []
                ),
            ])
            ->setDataMapper($this)
        ;
    }

    /**
     * @psalm-param array $viewData,
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    #[\Override]
    public function mapDataToForms(mixed $viewData, \Traversable $forms): void
    {
        $forms = iterator_to_array($forms);

        /** @var FormInterface[] $forms */

        // initialize form field values
        $forms['refererPage']->setData(
            $viewData['refererPage']
        );
    }

    /**
     * @psalm-param array $viewData
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    #[\Override]
    public function mapFormsToData(\Traversable $forms, mixed &$viewData): void {}
}
