<?php declare(strict_types = 1);

/**
 * DeviceFirmwareMessageConsumer.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:FbMqttConnector!
 * @subpackage     Consumers
 * @since          0.4.0
 *
 * @date           05.02.22
 */

namespace FastyBird\FbMqttConnector\Consumers;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\FbMqttConnector\Consumers;
use FastyBird\FbMqttConnector\Entities;
use FastyBird\FbMqttConnector\Exceptions;
use Nette;
use Nette\Utils;
use Psr\Log;
use Throwable;

/**
 * Device firmware MQTT message consumer
 *
 * @package        FastyBird:FbMqttConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceFirmwareMessageConsumer implements Consumers\IConsumer
{

	use Nette\SmartObject;

	/** @var DevicesModuleModels\Devices\IDeviceRepository */
	private DevicesModuleModels\Devices\IDeviceRepository $deviceRepository;

	/** @var DevicesModuleModels\Devices\IDevicesManager */
	private DevicesModuleModels\Devices\IDevicesManager $devicesManager;

	/** @var Persistence\ManagerRegistry */
	protected Persistence\ManagerRegistry $managerRegistry;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	public function __construct(
		DevicesModuleModels\Devices\IDeviceRepository $deviceRepository,
		DevicesModuleModels\Devices\IDevicesManager $devicesManager,
		Persistence\ManagerRegistry $managerRegistry,
		?Log\LoggerInterface $logger = null
	) {
		$this->deviceRepository = $deviceRepository;
		$this->devicesManager = $devicesManager;

		$this->managerRegistry = $managerRegistry;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Exceptions\InvalidStateException
	 */
	public function consume(
		Entities\Messages\IEntity $entity
	): void {
		if (!$entity instanceof Entities\Messages\Firmware) {
			return;
		}

		$findDeviceQuery = new DevicesModuleQueries\FindDevicesQuery();
		$findDeviceQuery->byIdentifier($entity->getDevice());

		$device = $this->deviceRepository->findOneBy($findDeviceQuery);

		if ($device === null) {
			$this->logger->error(sprintf('[FB:NODE:MQTT] Device "%s" is not registered', $entity->getDevice()));

			return;
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$toUpdate = [];

			foreach (
				[
					Entities\Messages\Firmware::MANUFACTURER,
					Entities\Messages\Firmware::VERSION,
				] as $attribute
			) {
				if ($entity->getParameter() === $attribute) {
					$subResult = $this->setDeviceFirmwareInfo($attribute, $entity->getValue());

					$toUpdate = array_merge($toUpdate, $subResult);
				}
			}

			if ($toUpdate !== []) {
				$this->devicesManager->update($device, Utils\ArrayHash::from($toUpdate));
			}

			// Commit all changes into database
			$this->getOrmConnection()->commit();

		} catch (Throwable $ex) {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}

			throw new Exceptions\InvalidStateException('An error occurred: ' . $ex->getMessage(), $ex->getCode(), $ex);
		}
	}

	/**
	 * @param string $parameter
	 * @param string $value
	 *
	 * @return mixed[]
	 */
	private function setDeviceFirmwareInfo(
		string $parameter,
		string $value
	): array {
		$parametersMapping = [
			Entities\Messages\Firmware::MANUFACTURER => 'firmwareManufacturer',
			Entities\Messages\Firmware::VERSION      => 'firmwareVersion',
		];

		foreach ($parametersMapping as $key => $field) {
			if ($parameter === $key) {
				return [
					$field => $value,
				];
			}
		}

		return [];
	}

	/**
	 * @return Connection
	 */
	private function getOrmConnection(): Connection
	{
		$connection = $this->managerRegistry->getConnection();

		if ($connection instanceof Connection) {
			return $connection;
		}

		throw new Exceptions\RuntimeException('Entity manager could not be loaded');
	}

}
