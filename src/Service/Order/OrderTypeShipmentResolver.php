<?php
/**
 * Resolves the shipment type that matches an order's selected carrier.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Order;

use Order;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Validate;

final class OrderTypeShipmentResolver
{
	public function __construct(
		private readonly ?TypeShipmentRepository $typeShipmentRepository = null
	) {
	}

	/**
	 * @return array{type_id: int, carrier_id: int, reference_id: int}|null
	 */
	public function resolveForOrder(?Order $order): ?array
	{
		if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
			return null;
		}

		$carrierId = isset($order->id_carrier) ? (int) $order->id_carrier : 0;
		$referenceId = $this->resolveCarrierReferenceId($carrierId);

		if ($referenceId <= 0) {
			return null;
		}

		return $this->resolveForCarrierReference($referenceId);
	}

	/**
	 * @return array{type_id: int, carrier_id: int, reference_id: int}|null
	 */
	public function resolveForCarrierIds(?int $carrierId, ?int $referenceCarrierId = null): ?array
	{
		$referenceId = $referenceCarrierId ?: $this->resolveCarrierReferenceId($carrierId ?? 0);

		if ($referenceId <= 0) {
			return null;
		}

		return $this->resolveForCarrierReference($referenceId);
	}

	private function resolveForCarrierReference(int $referenceCarrierId): ?array
	{
		$typeShipment = $this->findActiveTypeShipment($referenceCarrierId);

		if (!$typeShipment instanceof TypeShipment || null === $typeShipment->getId()) {
			return null;
		}

		$carrier = $typeShipment->getCarrier();
		if (null === $carrier->getId()) {
			return null;
		}

		return [
			'type_id' => (int) $typeShipment->getId(),
			'carrier_id' => (int) $carrier->getId(),
			'reference_id' => (int) ($typeShipment->getReferenceCarrierId() ?? 0),
		];
	}

	private function resolveCarrierReferenceId(int $carrierId): int
	{
		if ($carrierId <= 0 || !class_exists(\Carrier::class)) {
			return 0;
		}

		try {
			$carrier = new \Carrier($carrierId);
		} catch (\Throwable) {
			return 0;
		}

		if (!Validate::isLoadedObject($carrier)) {
			return 0;
		}

		return (int) ($carrier->id_reference ?? 0);
	}

	private function findActiveTypeShipment(int $referenceCarrierId): ?TypeShipment
	{
		if (!$this->typeShipmentRepository instanceof TypeShipmentRepository) {
			return null;
		}

		try {
			return $this->typeShipmentRepository->findActiveByReferenceCarrier($referenceCarrierId);
		} catch (\Throwable) {
			return null;
		}
	}
}
