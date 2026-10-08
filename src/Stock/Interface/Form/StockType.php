<?php

declare(strict_types=1);

namespace Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Xver\MiCartera\Domain\Account\Application\Query\AccountQuery;
use Xver\MiCartera\Domain\Account\Domain\AccountPersistenceInterface;
use Xver\MiCartera\Domain\Exchange\Application\Query\ExchangeQuery;
use Xver\MiCartera\Domain\Exchange\Domain\ExchangePersistenceInterface;
use Xver\MiCartera\Domain\Stock\Application\Query\StockQuery;
use Xver\MiCartera\Domain\Stock\Domain\StockPersistenceInterface;

final class StockType extends AbstractType implements DataMapperInterface
{
    private readonly string $accountIdentifier;

    public function __construct(
        TokenStorageInterface $token,
        private readonly ExchangePersistenceInterface $exchangePersistence,
        private readonly AccountPersistenceInterface $accountPersistence,
        private readonly StockPersistenceInterface $stockPersistence
    ) {
        /** @psalm-suppress PossiblyNullReference */
        $this->accountIdentifier = $token->getToken()->getUser()->getUserIdentifier();
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (array_key_exists('data', $options)) { // When editting stock, code is readonly
            $builder
                ->add('exchange', null, [
                    'disabled' => true,
                    'label' => new TranslatableMessage(
                        'exchange'
                    ),
                ])
                ->add('code', null, [
                    'disabled' => true,
                    'label' => new TranslatableMessage(
                        'code'
                    ),
                ])
            ;
            $submitLabel = new TranslatableMessage(
                'update'
            );
        } else {
            $exchangesQuery = new ExchangeQuery($this->exchangePersistence);
            $exchanges = $exchangesQuery->all()->getCollection()->toArray();
            $builder
                ->add('exchange', ChoiceType::class, [
                    'choices' => $exchanges,
                    'label' => new TranslatableMessage(
                        'exchange'
                    ),
                    'choice_value' => 'code',
                    'choice_label' => 'code',
                    'choice_translation_domain' => false,
                ])
                ->add('code', null, [
                    'label' => new TranslatableMessage(
                        'code'
                    ),
                ])
            ;
            $submitLabel = new TranslatableMessage(
                'createNew'
            );
        }
        $accountQuery = new AccountQuery($this->accountPersistence);
        $account = $accountQuery->findByIdentifierOrThrowException($this->accountIdentifier);
        $builder
            ->add('name', TextType::class, [
                'label' => new TranslatableMessage(
                    'name'
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
            ->add('refererPage', HiddenType::class)
            ->add('cmdSubmit', SubmitType::class, [
                'label' => $submitLabel,
            ])
            ->setDataMapper($this)
        ;
    }

    /**
     * @psalm-param array|null $viewData,
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    #[\Override]
    public function mapDataToForms(mixed $viewData, \Traversable $forms): void
    {
        // there is no data yet, so nothing to prepopulate
        if (null === $viewData || !$forms instanceof \Traversable) {
            return;
        }

        $forms = iterator_to_array($forms);

        /** @var FormInterface[] $forms */

        // initialize form field values
        $forms['code']->setData($viewData['code']);
        if (!isset($viewData['updatePost'])) {
            $query = new StockQuery($this->stockPersistence, $this->accountPersistence);
            $stock = $query->byCode((string) $viewData['code']);
            $forms['exchange']->setData($stock->getExchange()->getCode());
            $forms['name']->setData($stock->getName());
            $forms['price']->setData($stock->getPrice()->getValue());
            $forms['refererPage']->setData($viewData['refererPage']);
        }
    }

    /**
     * @psalm-param array $viewData
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    #[\Override]
    public function mapFormsToData(\Traversable $forms, mixed &$viewData): void {}
}
