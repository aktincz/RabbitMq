<?php

declare(strict_types = 1);

namespace Kdyby\RabbitMq;

use Nette;
use PhpAmqpLib\Connection\Heartbeat\AbstractSignalHeartbeatSender;

class Connection extends \PhpAmqpLib\Connection\AMQPLazyConnection implements \Kdyby\RabbitMq\IConnection
{

	private ?AbstractSignalHeartbeatSender $heartbeatSender;

	/**
	 * @var \Nette\DI\Container
	 */
	private $serviceLocator;

	/**
	 * @var \Kdyby\RabbitMq\Diagnostics\Panel
	 */
	private $panel;

	/**
	 * @var array
	 */
	private $serviceMap = [];

	public function getConsumer(string $name): \Kdyby\RabbitMq\Consumer
	{
		if (!isset($this->serviceMap['consumer'][$name])) {
			throw new \Kdyby\RabbitMq\Exception\InvalidArgumentException(
				\sprintf('Unknown consumer %s', $name)
			);
		}

		return $this->serviceLocator->getService($this->serviceMap['consumer'][$name]);
	}

	public function getProducer(string $name): Producer
	{
		if (!isset($this->serviceMap['producer'][$name])) {
			throw new \Kdyby\RabbitMq\Exception\InvalidArgumentException(
				\sprintf('Unknown producer %s', $name)
			);
		}

		return $this->serviceLocator->getService($this->serviceMap['producer'][$name]);
	}

	public function getRpcClient(string $name): RpcClient
	{
		if (!isset($this->serviceMap['rpcClient'][$name])) {
			throw new \Kdyby\RabbitMq\Exception\InvalidArgumentException(
				\sprintf('Unknown RPC client %s', $name)
			);
		}

		return $this->serviceLocator->getService($this->serviceMap['rpcClient'][$name]);
	}

	public function getRpcServer(string $name): RpcServer
	{
		if (!isset($this->serviceMap['rpcServer'][$name])) {
			throw new \Kdyby\RabbitMq\Exception\InvalidArgumentException(
				\sprintf('Unknown RPC server %s', $name)
			);
		}

		return $this->serviceLocator->getService($this->serviceMap['rpcServer'][$name]);
	}

	/**
	 * @internal
	 * @param array<string> $producers
	 * @param array<string> $consumers
	 * @param array<string> $rpcClients
	 * @param array<string> $rpcServers
	 */
	public function injectServiceMap(
		array $producers,
		array $consumers,
		array $rpcClients,
		array $rpcServers
	): void
	{
		$this->serviceMap = [
			'consumer' => $consumers,
			'producer' => $producers,
			'rpcClient' => $rpcClients,
			'rpcServer' => $rpcServers,
		];
	}

	/**
	 * @internal
	 * @param \Nette\DI\Container $sl
	 */
	public function injectServiceLocator(Nette\DI\Container $sl): void
	{
		$this->serviceLocator = $sl;
	}

	/**
	 * @internal
	 * @param \Kdyby\RabbitMq\Diagnostics\Panel $panel
	 */
	public function injectPanel(Diagnostics\Panel $panel): void
	{
		$this->panel = $panel->register($this);
	}

	/**
	 * Fetch a Channel object identified by the numeric channel_id, or
	 * create that object if it doesn't already exist.
	 *
	 * @param string $id
	 * @return \Kdyby\RabbitMq\Channel
	 * @throws \Exception
	 */
	// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint,SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	public function channel($id = NULL): Channel
	{
		if (isset($this->channels[$id])) {
			return $this->channels[$id];
		}

		$this->connect();
		$id = $id ?: $this->get_free_channel_id();

		return $this->channels[$id] = $this->doCreateChannel($id);
	}

	protected function doCreateChannel(int $id): Channel
	{
		$channel = new Channel($this->connection, $id);

		if ($this->panel) {
			$channel->injectPanel($this->panel);
		}

		return $channel;
	}

	protected function connect()
	{
		$connected = $this->isConnected();
		parent::connect();
		if (!$connected && $this->heartbeatSender) {
			$this->heartbeatSender->register();
		}
	}

	public function getHeartbeatSender(): ?AbstractSignalHeartbeatSender
	{
		return $this->heartbeatSender;
	}

	public function setHeartbeatSender(?AbstractSignalHeartbeatSender $heartbeatSender): void
	{
		$this->heartbeatSender = $heartbeatSender;
	}
}
