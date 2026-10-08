<?php

declare(strict_types=1);

namespace Xver\MiCartera\Frontend\Symfony\Stock\Interface\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Xver\MiCartera\Domain\Account\Application\Query\AccountQuery;
use Xver\MiCartera\Domain\Account\Domain\AccountPersistenceInterface;

final class StockOperateImportType extends AbstractType implements DataMapperInterface
{
    private readonly string $accountIdentifier;

    public function __construct(
        TokenStorageInterface $token,
        private readonly AccountPersistenceInterface $accountPersistence
    ) {
        /** @psalm-suppress PossiblyNullReference */
        $this->accountIdentifier = $token->getToken()->getUser()->getUserIdentifier();
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $accountQuery = new AccountQuery($this->accountPersistence);
        $account = $accountQuery->findByIdentifierOrThrowException($this->accountIdentifier);
        $builder
            ->add('csv', FileType::class, [
                'label' => new TranslatableMessage(
                    'csvTransactionFormat',
                    [
                        'timezone' => $account->getTimeZone()->getName(),
                        'currency' => $account->getCurrency()->getSymbol(),
                    ]
                ),
                'label_html' => true,
                'required' => true,
                'mapped' => false,
            ])
            ->add('upload', SubmitType::class, ['label' => 'import'])
            ->setDataMapper($this)
        ;
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
