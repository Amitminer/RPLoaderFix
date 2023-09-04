<?php

declare(strict_types=1);

namespace EmreTr1\RPLoaderFix;

use pocketmine\player\Player;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use function array_shift;
use function unset;

class PackSendEntry
{
	/** @var DataPacket[] */
	protected array $packets = [];
	/** @var Player */
	public Player $player;

	public function __construct(Player $player)
	{
		$this->player = $player;
	}

	public function addPacket(DataPacket $packet): void
	{
		$this->packets[] = $packet;
	}

	public function tick(): void
	{
		if (!$this->player->isConnected()) {
			unset(RPLoaderFix::$packSendQueue[$this->player->getName()]);
			return;
		}

		if ($next = array_shift($this->packets)) {
			if ($next instanceof ClientboundPacket) {
				$this->player->getNetworkSession()->sendDataPacket($next);
			}
		} else {
			unset(RPLoaderFix::$packSendQueue[$this->player->getName()]);
		}
	}
}