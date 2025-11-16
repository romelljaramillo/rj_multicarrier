<?php
/**
 * Doctrine repository for carrier entities.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Roanja\Module\RjMulticarrier\Entity\Carrier;

if (!class_exists('\\Carrier') && class_exists('\\CarrierCore')) {
    class_alias('\\CarrierCore', '\\Carrier');
}

if (!class_exists('\\Context') && class_exists('\\ContextCore')) {
    class_alias('\\ContextCore', '\\Context');
}

class CarrierRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findOneByShortName(string $shortName): ?Carrier
    {
        try {
            return $this->createQueryBuilder('carrier')
                ->andWhere('carrier.shortName = :shortName')
                ->andWhere('carrier.deleted = false')
                ->setParameter('shortName', $shortName)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException) {
            return null;
        }
    }

    public function find(int $id): ?Carrier
    {
        $entity = $this->entityManager->find(Carrier::class, $id);

        if ($entity instanceof Carrier && $entity->isDeleted()) {
            return null;
        }

        return $entity instanceof Carrier ? $entity : null;
    }

    public function getIconByShortName(string $shortName): ?string
    {
        $carrier = $this->findOneByShortName($shortName);

        return $carrier?->getIcon();
    }

    /**
     * @return Carrier[]
    */
    public function findAllOrdered(?string $shortName = null): array
    {
        $qb = $this->createQueryBuilder('carrier')
            ->andWhere('carrier.deleted = false')
            ->orderBy('carrier.name', 'ASC');

        if ($shortName) {
            $qb->andWhere('carrier.shortName = :shortName')
                ->setParameter('shortName', $shortName);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, array{id_reference: int, name: string}>
     */
    public function getActiveReferenceCarriers(?int $languageId = null): array
    {
        if (!class_exists('\\Carrier')) {
            return [];
        }

        $languageId = $languageId ?? $this->resolveLanguageId();
        $rawCarriers = (array) \Carrier::getCarriers($languageId, true);

        $references = [];

        foreach ($rawCarriers as $carrier) {
            if (is_array($carrier)) {
                $referenceId = (int) ($carrier['id_reference'] ?? 0);
                $name = trim((string) ($carrier['name'] ?? ''));
            } elseif (is_object($carrier)) {
                $referenceId = (int) ($carrier->id_reference ?? 0);
                $name = trim((string) ($carrier->name ?? ''));
            } else {
                continue;
            }

            if ($referenceId <= 0) {
                continue;
            }

            if (!isset($references[$referenceId])) {
                $references[$referenceId] = [
                    'id_reference' => $referenceId,
                    'name' => $name,
                ];
                continue;
            }

            if ('' === $references[$referenceId]['name'] && '' !== $name) {
                $references[$referenceId]['name'] = $name;
            }
        }

        $referenceList = array_values($references);

        usort($referenceList, static function (array $left, array $right): int {
            $leftName = $left['name'] !== '' ? $left['name'] : null;
            $rightName = $right['name'] !== '' ? $right['name'] : null;

            if (null !== $leftName && null !== $rightName) {
                $comparison = strcasecmp($leftName, $rightName);
                if (0 !== $comparison) {
                    return $comparison;
                }
            } elseif (null !== $leftName) {
                return -1;
            } elseif (null !== $rightName) {
                return 1;
            }

            return $left['id_reference'] <=> $right['id_reference'];
        });

        return $referenceList;
    }

    private function resolveLanguageId(): int
    {
        if (class_exists('\\Context')) {
            $context = \Context::getContext();

            if (isset($context->language->id)) {
                return (int) $context->language->id;
            }
        }

        return 1;
    }

    private function createQueryBuilder(string $alias): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select($alias)
            ->from(Carrier::class, $alias);
    }
}
