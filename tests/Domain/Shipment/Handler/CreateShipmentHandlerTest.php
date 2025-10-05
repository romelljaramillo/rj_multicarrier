<?php
/**
 * @covers \Roanja\Module\RjMulticarrier\Domain\Shipment\Handler\CreateShipmentHandler
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Tests\Domain\Shipment\Handler;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\CreateShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Handler\CreateShipmentHandler;
use Roanja\Module\RjMulticarrier\Entity\Company;
use Roanja\Module\RjMulticarrier\Entity\InfoPackage;
use Roanja\Module\RjMulticarrier\Entity\Label;
use Roanja\Module\RjMulticarrier\Entity\Shipment;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;
use Roanja\Module\RjMulticarrier\Repository\InfoPackageRepository;
use Roanja\Module\RjMulticarrier\Repository\ShipmentRepository;

final class CreateShipmentHandlerTest extends TestCase
{
    public function testHandleCreatesNewShipmentAndLabels(): void
    {
        $company = new Company('Carrier Co', 'CC');
        $typeShipment = new TypeShipment($company, 'Express', 'EXP');
        $infoPackage = new InfoPackage(10, 200, $typeShipment, 1, 2.5);

        $persisted = [];
        $flushCount = 0;

    /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $entityManager
            ->expects($this->exactly(2))
            ->method('flush')
            ->willReturnCallback(static function () use (&$flushCount): void {
                ++$flushCount;
            });

    /** @var ShipmentRepository&\PHPUnit\Framework\MockObject\MockObject $shipmentRepository */
    $shipmentRepository = $this->createMock(ShipmentRepository::class);
        $shipmentRepository
            ->expects($this->once())
            ->method('findOneByOrderId')
            ->with(1000)
            ->willReturn(null);

    /** @var InfoPackageRepository&\PHPUnit\Framework\MockObject\MockObject $infoPackageRepository */
    $infoPackageRepository = $this->createMock(InfoPackageRepository::class);
        $infoPackageRepository
            ->expects($this->once())
            ->method('find')
            ->with(501)
            ->willReturn($infoPackage);

    /** @var CompanyRepository&\PHPUnit\Framework\MockObject\MockObject $companyRepository */
    $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository
            ->expects($this->once())
            ->method('find')
            ->with(77)
            ->willReturn($company);

        $handler = new CreateShipmentHandler($entityManager, $shipmentRepository, $infoPackageRepository, $companyRepository);

    $command = new CreateShipmentCommand(
            orderId: 1000,
            orderReference: 'ORD-1000',
            shipmentNumber: 'SHIP-9000',
            infoPackageId: 501,
            companyId: 77,
            product: 'EXPRESS',
            requestPayload: ['foo' => 'bar'],
            responsePayload: ['status' => 'ok'],
            labels: [
                [
                    'storage_key' => 'label-1',
                    'package_id' => 'PK-1',
                    'tracker_code' => 'TRACK-1',
                    'label_type' => 'PDF',
                ],
            ]
        );

        $shipment = $handler->handle($command);

        $this->assertInstanceOf(Shipment::class, $shipment);
        $this->assertSame('ORD-1000', $shipment->getOrderReference());
        $this->assertSame('SHIP-9000', $shipment->getShipmentNumber());
        $this->assertSame('EXPRESS', $shipment->getProduct());
        $this->assertSame(json_encode(['foo' => 'bar']), $shipment->getRequestPayload());
        $this->assertSame(json_encode(['status' => 'ok']), $shipment->getResponsePayload());
        $this->assertSame($infoPackage, $shipment->getInfoPackage());
        $this->assertSame($company, $shipment->getCompany());

        $this->assertCount(2, $persisted, 'Shipment and label should be persisted');
        $this->assertInstanceOf(Shipment::class, $persisted[0]);
        $this->assertInstanceOf(Label::class, $persisted[1]);
        $this->assertSame($shipment, $persisted[1]->getShipment());
        $this->assertSame(2, $flushCount, 'Handler flushes once for shipment and once for labels');
    }

    public function testHandleUpdatesExistingShipmentAndRemovesMissingCompany(): void
    {
        $company = new Company('Carrier Co', 'CC');
        $typeShipment = new TypeShipment($company, 'Express', 'EXP');
        $infoPackage = new InfoPackage(20, 300, $typeShipment, 3, 5.5);

        $persisted = [];
        $flushCount = 0;

    /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $entityManager
            ->expects($this->exactly(2))
            ->method('flush')
            ->willReturnCallback(static function () use (&$flushCount): void {
                ++$flushCount;
            });

        $existingShipment = new Shipment(2000, $infoPackage);
        $existingShipment->setCompany(new Company('Old', 'OLD'));

    /** @var ShipmentRepository&\PHPUnit\Framework\MockObject\MockObject $shipmentRepository */
    $shipmentRepository = $this->createMock(ShipmentRepository::class);
        $shipmentRepository
            ->expects($this->once())
            ->method('findOneByOrderId')
            ->with(2000)
            ->willReturn($existingShipment);

    /** @var InfoPackageRepository&\PHPUnit\Framework\MockObject\MockObject $infoPackageRepository */
    $infoPackageRepository = $this->createMock(InfoPackageRepository::class);
        $infoPackageRepository
            ->expects($this->once())
            ->method('find')
            ->with(888)
            ->willReturn($infoPackage);

    /** @var CompanyRepository&\PHPUnit\Framework\MockObject\MockObject $companyRepository */
    $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository
            ->expects($this->once())
            ->method('find')
            ->with(99)
            ->willReturn(null);

        $handler = new CreateShipmentHandler($entityManager, $shipmentRepository, $infoPackageRepository, $companyRepository);

    $command = new CreateShipmentCommand(
            orderId: 2000,
            orderReference: 'ORD-2000',
            shipmentNumber: 'SHIP-2000',
            infoPackageId: 888,
            companyId: 99,
            product: 'STANDARD',
            requestPayload: ['baz' => 'qux'],
            responsePayload: ['status' => 'updated'],
            labels: [
                [
                    'storage_key' => 'label-2',
                    'package_id' => 'PK-2',
                ],
            ]
        );

        $shipment = $handler->handle($command);

        $this->assertSame($existingShipment, $shipment, 'Existing shipment instance should be reused');
        $this->assertSame('ORD-2000', $shipment->getOrderReference());
        $this->assertSame('SHIP-2000', $shipment->getShipmentNumber());
        $this->assertSame('STANDARD', $shipment->getProduct());
        $this->assertSame(json_encode(['baz' => 'qux']), $shipment->getRequestPayload());
        $this->assertSame(json_encode(['status' => 'updated']), $shipment->getResponsePayload());
        $this->assertSame($infoPackage, $shipment->getInfoPackage());
        $this->assertNull($shipment->getCompany(), 'Company should be null when repository does not find it');

        $this->assertCount(1, $persisted, 'Only labels should be persisted for existing shipments');
        $this->assertInstanceOf(Label::class, $persisted[0]);
        $this->assertSame($shipment, $persisted[0]->getShipment());
        $this->assertSame(2, $flushCount, 'Handler flushes twice even when shipment already exists');
    }
}
