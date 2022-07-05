<?php declare(strict_types = 1);

/**
 * DevicePropertyMessageConsumer.php
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

use Doctrine\DBAL;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence;
use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\DevicesModule\Utilities as DevicesModuleUtilities;
use FastyBird\FbMqttConnector\Consumers;
use FastyBird\FbMqttConnector\Entities;
use FastyBird\FbMqttConnector\Exceptions;
use Nette;
use Nette\Utils;
use Psr\Log;
use Throwable;

/**
 * Device property MQTT message consumer
 *
 * @package        FastyBird:FbMqttConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DevicePropertyMessageConsumer implements Consumers\IConsumer
{

	use Nette\SmartObject;
	use TPropertyMessageConsumer;

	/** @var DevicesModuleModels\Devices\IDevicesRepository */
	private DevicesModuleModels\Devices\IDevicesRepository $deviceRepository;

	/** @var DevicesModuleModels\Devices\Properties\IPropertiesManager */
	private DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager;

	/** @var DevicesModuleModels\States\DevicePropertiesRepository */
	private DevicesModuleModels\States\DevicePropertiesRepository $propertyStateRepository;

	/** @var DevicesModuleModels\States\DevicePropertiesManager */
	private DevicesModuleModels\States\DevicePropertiesManager $propertiesStatesManager;

	/** @var Persistence\ManagerRegistry */
	protected Persistence\ManagerRegistry $managerRegistry;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	public function __construct(
		DevicesModuleModels\Devices\IDevicesRepository $deviceRepository,
		DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager,
		DevicesModuleModels\States\DevicePropertiesRepository $propertyStateRepository,
		DevicesModuleModels\States\DevicePropertiesManager $propertiesStatesManager,
		Persistence\ManagerRegistry $managerRegistry,
		?Log\LoggerInterface $logger = null
	) {
		$this->deviceRepository = $deviceRepository;
		$this->propertiesManager = $propertiesManager;
		$this->propertiesStatesManager = $propertiesStatesManager;
		$this->propertyStateRepository = $propertyStateRepository;

		$this->managerRegistry = $managerRegistry;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBAL\Exception
	 */
	public function consume(
		Entities\Messages\IEntity $entity
	): void {
		if (!$entity instanceof Entities\Messages\DeviceProperty) {
			return;
		}

		$findDeviceQuery = new DevicesModuleQueries\FindDevicesQuery();
		$findDeviceQuery->byIdentifier($entity->getDevice());

		$device = $this->deviceRepository->findOneBy($findDeviceQuery);

		if ($device === null) {
			$this->logger->error(
				sprintf('Device "%s" is not registered', $entity->getDevice()),
				[
					'source' => 'fastybird-fb-mqtt-connector',
					'type'   => 'consumer',
				]
			);

			return;
		}

		$property = $device->findProperty($entity->getProperty());

		if ($property === null) {
			$this->logger->error(
				sprintf('Property "%s" is not registered', $entity->getProperty()),
				[
					'source' => 'fastybird-fb-mqtt-connector',
					'type'   => 'consumer',
				]
			);

			return;
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$toUpdate = $this->handlePropertyConfiguration($entity);

			if ($toUpdate !== []) {
				$property = $this->propertiesManager->update($property, Utils\ArrayHash::from($toUpdate));
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

		if ($entity->getValue() !== 'N/A') {
			try {
				$propertyState = $this->propertyStateRepository->findOne($property);

			} catch (DevicesModuleExceptions\NotImplementedException $ex) {
				$this->logger->warning(
					'States repository is not configured. State could not be fetched',
					[
						'source' => 'fastybird-fb-mqtt-connector',
						'type'   => 'consumer',
					]
				);

				return;
			}

			$actualValue = DevicesModuleUtilities\ValueHelper::flattenValue(
				DevicesModuleUtilities\ValueHelper::normalizeValue(
					$property->getDataType(),
					$entity->getValue(),
					$property->getFormat(),
					$property->getInvalid()
				)
			);

			try {
				// In case synchronization failed...
				if ($propertyState === null) {
					// ...create state in storage
					$this->propertiesStatesManager->create(
						$property,
						Utils\ArrayHash::from(array_merge(
							$property->toArray(),
							[
								'actualValue'   => $actualValue,
								'expectedValue' => null,
								'pending'       => false,
								'valid'         => true,
							]
						))
					);

				} else {
					$this->propertiesStatesManager->update(
						$property,
						$propertyState,
						Utils\ArrayHash::from([
							'actualValue'   => $actualValue,
							'expectedValue' => null,
							'pending'       => false,
							'valid'         => true,
						])
					);
				}
			} catch (DevicesModuleExceptions\NotImplementedException $ex) {
				$this->logger->warning(
					'States manager is not configured. State could not be saved',
					[
						'source' => 'fastybird-fb-mqtt-connector',
						'type'   => 'consumer',
					]
				);
			}
		}
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
