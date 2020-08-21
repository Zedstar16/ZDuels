<?php

/*
 *
 *  ___________            _
 * |___  /  _  \          | |
 *    / /| | | |_   _  ___| |___
 *   / / | | | | | | |/ _ \ / __|
 * ./ /__| |/ /| |_| |  __/ \__ \
 * \_____/___/  \__,_|\___|_|___/
 *
 * @author Zedstar16
 *
 */

namespace Zedstar16\ZDuels;


use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\Player;
use Zedstar16\ZDuels\libs\invmenu\InvMenu;

class EventListener implements Listener
{
    /** @var Main */
    private $main;

    public function __construct(Main $main)
    {
        $this->main = $main;
    }

    public function onJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        if (Main::getInstance()->hasSavedInventory($player)) {
            Main::getInstance()->loadSavedInventory($player);
        }
        if (Main::getDuelLevelManager()->isDuelLevel($player->getLevel())) {
            $player->teleport($player->getServer()->getDefaultLevel()->getSpawnLocation());
        }
    }


    private function getMatchInventoryContents(Player $player): array
    {
        $armor_slots = [
            0 => 47,
            1 => 48,
            2 => 49,
            3 => 50
        ];
        for ($i = 36; $i <= 44; $i++) {
            $content[$i] = Item::get(Item::STAINED_GLASS_PANE, 15)->setCustomName(" ");
        }
        $content[45] = Item::get(Item::STAINED_GLASS_PANE, 15)->setCustomName(" ");
        $content[46] = Item::get(Item::STAINED_GLASS_PANE, 15)->setCustomName("Armor ->");
        $content[51] = Item::get(Item::STAINED_GLASS_PANE, 15)->setCustomName("<- Armor");
        $content[52] = Item::get(Item::STAINED_GLASS_PANE, 15)->setCustomName(" ");
        $content[53] = Item::get(Item::STAINED_GLASS_PANE, 15)->setCustomName(" ");
        foreach ($content as $item) {
            if ($item instanceof Item) {
                $nbt = $item->getNamedTag();
                $nbt->setString("chest", "chest");
                $item->setCompoundTag($nbt);
            }
        }
        return $content;
    }

    public function onQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();
        $duel = Main::getDuelManager()->getDuel($player);
        if ($duel !== null) {
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $duel->terminate("{$player->getName()} logged out");
        }
    }

    public function onDeath(PlayerDeathEvent $event)
    {
        $subject = $event->getPlayer();
        $cause = $event->getPlayer()->getLastDamageCause();
        if ($cause instanceof EntityDamageByEntityEvent) {
            $duel = Main::getDuelManager()->getDuel($subject);
            if ($duel !== null) {
                $duel->saveMatchInventory($subject->getName(), $subject->getInventory()->getContents(), $subject->getArmorInventory()->getContents());
                $event->setDrops([]);
                $duel->finish($duel->getOpponent($subject), $subject);
            }
        }
    }

    public function onDamage(EntityDamageByEntityEvent $event)
    {
        $subject = $event->getEntity();
        $damager = $event->getDamager();
        if ($subject instanceof Player && $damager instanceof Player) {
            $duel = Main::getDuelManager()->getDuel($subject);
            if (Main::getDuelManager()->getDuel($damager) !== null && $duel !== null) {
                $damage = $event->getFinalDamage();
                $duel->stats[$subject->getName()]["damage_taken"] += $damage;
                $duel->stats[$damager->getName()]["damage_dealt"] += $damage;
                $duel->stats[$damager->getName()]["hits"]++;
            }
        }
    }


    public function onpacketyeet(DataPacketReceiveEvent $e): void
    {
        $pk = $e->getPacket();
        if ($pk::NETWORK_ID === InventoryTransactionPacket::NETWORK_ID && $pk->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY) {
            Main::getInstance()->clicked($e->getPlayer());
        } elseif ($pk::NETWORK_ID === LevelSoundEventPacket::NETWORK_ID && $pk->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE) {
            Main::getInstance()->clicked($e->getPlayer());
        } elseif ($pk::NETWORK_ID === PlayerActionPacket::NETWORK_ID && $pk->action === PlayerActionPacket::ACTION_START_BREAK && $e->getPlayer()->getGamemode() == 2) {
            Main::getInstance()->clicked($e->getPlayer());
        }
    }

    public function onInteract(PlayerInteractEvent $event){
        $p = $event->getPlayer();
        $item = $p->getInventory()->getItemInHand();
        if($item->getNamedTag()->hasTag("leave")){
            $p->getInventory()->remove($item);
            Main::getDuelQueue()->removeFromQueue($p);
        }
    }
}