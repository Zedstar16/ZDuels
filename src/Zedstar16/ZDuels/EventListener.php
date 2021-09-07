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


use pocketmine\entity\projectile\EnderPearl;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use Zedstar16\ZDuels\constant\Constants;
use Zedstar16\ZDuels\duel\Duel;
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
                $item->setNamedTag($nbt);
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
        if (Main::getDuelQueue()->isInAQueue($player)) {
            Main::getDuelQueue()->removeFromQueue($player);
        }
    }

    public function onDeath(PlayerDeathEvent $event)
    {
        $subject = $event->getPlayer();
        $cause = $event->getPlayer()->getLastDamageCause();
        $duel = Main::getDuelManager()->getDuel($subject);
        if ($duel !== null) {
            $duel->terminate("{$subject->getName()} unexpectedly died");
        }
    }

    /**
     * @param EntityDamageByEntityEvent $event
     * @priority LOWEST
     * @ignoreCancelled false
     */
    public function onAnyDamage(EntityDamageByEntityEvent $event)
    {
        $subject = $event->getEntity();
        $damager = $event->getDamager();
        if ($damager instanceof Player && !$subject instanceof Player) {
            if (Main::getDuelQueue()->isInAQueue($damager)) {
                $damager->sendMessage(Main::prefix . "You cannot perform this interaction while in a duels queue");
                $event->setCancelled(true);
                return;
            }
        }
    }

    /**
     * @param EntityDamageByEntityEvent $event
     * @priority HIGH
     * @ignoreCancelled true
     */
    public function onDamage(EntityDamageEvent $event)
    {
        if ($event instanceof EntityDamageByEntityEvent || $event instanceof EntityDamageByChildEntityEvent) {
            $subject = $event->getEntity();
            if ($event instanceof EntityDamageByChildEntityEvent) {
                $child = $event->getChild();
                if ($child !== null) {
                    $damager = $child->getOwningEntity();
                }
            } else $damager = $event->getDamager();
            if ($subject instanceof Player && $damager instanceof Player) {
                $duel = Main::getDuelManager()->getDuel($subject);
                if (Main::getDuelManager()->getDuel($damager) !== null && $duel !== null) {
                    $event->setModifier(0.0, EntityDamageByEntityEvent::MODIFIER_CRITICAL);
                    $damage = $event->getFinalDamage();
                    $duel->stats[$subject->getName()]["damage_taken"] += $damage;
                    $duel->stats[$damager->getName()]["damage_dealt"] += $damage;
                    $duel->stats[$damager->getName()]["hits"]++;
                    if ($subject->getHealth() - $event->getFinalDamage() <= 0) {
                        $duel = Main::getDuelManager()->getDuel($subject);
                        if ($duel !== null) {
                            $duel->sendFireworks($subject);
                            $duel->saveMatchInventory($subject->getName(), $subject->getInventory()->getContents(), $subject->getArmorInventory()->getContents());
                            $subject->setHealth(20);
                            $subject->setGamemode(3);
                            $subject->teleport($subject->asVector3()->add(0, 2));
                            $subject->extinguish();
                            $subject->getInventory()->clearAll(true);
                            $subject->getArmorInventory()->clearAll(true);
                            $duel->finish($duel->getOpponent($subject), $subject);
                        }
                    }
                }
            }
        }
    }


    public function onShoot(ProjectileLaunchEvent $e)
    {
        $entity = $e->getEntity();
        $owner = $entity->getOwningEntity();
        if ($owner instanceof Player) {
            $duel = Main::getDuelManager()->getDuel($owner);
            if ($duel !== null && $duel->stage === Constants::DUEL_INIT) {
                $e->setCancelled(true);
                $owner->sendMessage(Main::prefix . "You cannot shoot before the duel starts");
            }
        }
    }


    public function onpacketyeet(DataPacketReceiveEvent $e): void
    {
        $pk = $e->getPacket();
        if ($pk::NETWORK_ID === InventoryTransactionPacket::NETWORK_ID && $pk->trData->getTypeId() === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY) {
            Main::getInstance()->clicked($e->getPlayer());
        } elseif ($pk::NETWORK_ID === LevelSoundEventPacket::NETWORK_ID && $pk->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE) {
            Main::getInstance()->clicked($e->getPlayer());
        } elseif ($pk::NETWORK_ID === PlayerActionPacket::NETWORK_ID && $pk->action === PlayerActionPacket::ACTION_START_BREAK && $e->getPlayer()->getGamemode() == 2) {
            Main::getInstance()->clicked($e->getPlayer());
        }
    }

    /**
     * @param PlayerInteractEvent $event
     * @priority HIGHEST
     */
    public function onInteract(PlayerInteractEvent $event)
    {
        $p = $event->getPlayer();
        $item = $p->getInventory()->getItemInHand();
        if (Main::getDuelQueue()->isInAQueue($p) && !$item->getNamedTag()->hasTag("leave")) {
            $event->setCancelled();
        }
        if ($item->getNamedTag()->hasTag("leave")) {
            Main::getDuelQueue()->removeFromQueue($p);
        }
    }

    /**
     * @param CommandEvent $event
     * @priority LOWEST
     */
    public function oncmd(CommandEvent $event)
    {
        $p = $event->getSender();
        $cmd = explode(" ", $event->getCommand())[0];
        if ($p instanceof Player && !$p->isOp()) {
            $duel = Main::getDuelManager()->getDuel($p);
            $cmd = explode(" ", $event->getCommand())[0];
            if (!in_array($cmd, ["msg", "tell", "w", "effect", "enchant"])) {
                if ($duel !== null) {
                    $event->setCancelled(true);
                    $p->sendMessage(Main::prefix . "You cannot use this command in a duel");
                    return;
                }
                if (Main::getDuelQueue()->isInAQueue($p) && $cmd !== "duelqueue") {
                    $event->setCancelled(true);
                    $p->sendMessage(Main::prefix . "You cannot use this command while in a duel queue");
                    return;
                }
            }
        }
    }

    public function onHeld(PlayerItemHeldEvent $event)
    {
        $p = $event->getPlayer();
        $induel = Main::getDuelManager()->getDuel($p) !== null;
        foreach ($p->getInventory()->getContents() as $item) {
            if ($item->getNamedTag()->hasTag("zduels") && !$induel) {
                $p->getInventory()->remove($item);
            }
        }
        foreach ($p->getArmorInventory()->getContents() as $item) {
            if ($item->getNamedTag()->hasTag("zduels") && !$induel) {
                $p->getInventory()->remove($item);
            }
        }
    }
}