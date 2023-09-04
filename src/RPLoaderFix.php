<?php

declare(strict_types = 1);

namespace EmreTr1\RPLoaderFix;

use pocketmine\player\Player;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\event\server\DataPacketReceiveEvent as PacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkDataPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket as RPCRPacket;

class RPLoaderFix extends PluginBase implements Listener
{
    /** @var PackSendEntry[] */
    public static array $packSendQueue = [];

    protected function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            foreach (self::$packSendQueue as $entry) {
                $entry->tick();
            }
        }), (int) $this->getConfig()->get("rp-chunk-send-interval", 30));
    }

    public function getRpChunkSize(): int
    {
        return (int) $this->getConfig()->get("rp-chunk-size", 524288);
    }

    public function onPacketReceive(PacketReceiveEvent $event): void {
        $origin = $event->getOrigin();
        $player = $origin->getPlayer();
        $packet = $event->getPacket();

        if (!$player instanceof Player) return;

        if ($packet instanceof RPCRPacket) {
            if ($packet->status === RPCRPacket::STATUS_SEND_PACKS) {
             $event->cancel();
             $manager = $this->getServer()->getResourcePackManager();
             $playerName = $player->getName();
             self::$packSendQueue[$playerName] = $entry = new PackSendEntry($player);

       foreach ($packet->packIds as $uuid) {
                    // Dirty hack for Mojang's dirty hack for versions
        $splitPos = strpos($uuid, "_");
        if ($splitPos !== false) {
             $uuid = substr($uuid, 0, $splitPos);
           }
           $pack = $manager->getPackById($uuid);

        if (!($pack instanceof ResourcePack)) {
            $this->InvaildRP($player, $uuid, $manager);
             return;
            }
            // send ResourcePackDataInfoPacket.
            $this->sendResourcePackDataInfoPacket($pack, $player);

            for ($i = 0; $i < $pk->chunkCount; $i++) {
               // send ResourcePackChunkDataPacket..
              $this->sendResourcePackChunkDataPacket($pack, $entry);
                    }
                }
            }
        } elseif ($packet instanceof ResourcePackChunkRequestPacket) {
            $event->cancel(); // Don't rely on the client
        }
    }
    private function sendResourcePackDataInfoPacket($pack, $player) :void {
        $pk = new ResourcePackDataInfoPacket();
        $pk->packId = $pack->getPackId();
        $pk->maxChunkSize = $this->getRpChunkSize();
        $pk->chunkCount = (int) ceil($pack->getPackSize() / $pk->maxChunkSize);
        $pk->compressedPackSize = $pack->getPackSize();
        $pk->sha256 = $pack->getSha256();
        $player->getNetworkSession()->sendDataPacket($pk);
    }

    private function sendResourcePackChunkDataPacket($pack, $entry): void {
        $pk2 = new ResourcePackChunkDataPacket();
        $pk2->packId = $pack->getPackId();
        $pk2->chunkIndex = $i;
        $pk2->data = $pack->getPackChunk($pk->maxChunkSize * $i, $pk->maxChunkSize);
        //$pk2->progress = ($pk->maxChunkSize * $i);

        $entry->addPacket($pk2);
    }
    
    public function InvaildRP($player, $uuid, $manager): void {
        $player->kick("disconnectionScreen.resourcePack");
        $this->getServer()->getLogger()->debug(
            "Got a resource pack request for an unknown pack with UUID " .
            $uuid . ", available packs: " . implode(", ", $manager->getPackIdList())
        );
    }
}