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
use pocketmine\event\entity\EntityArmorChangeEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use Zedstar16\_Api\OwnageAPI;
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
            $player->getCursorInventory()->clearAll();
            $player->getCraftingGrid()->clearAll();
            $duel->terminate("{$player->getName()} logged out");
        }
        if (Main::getDuelQueue()->isInAQueue($player)) {
            Main::getDuelQueue()->removeFromQueue($player);
        }
        $spec = Main::getDuelManager()->getDuelSpectating($player);
        if($spec !== null){
            $spec->removeSpectator($player);
        }
    }

    public function onDrop(PlayerDropItemEvent $event){
        $p = $event->getPlayer();
        $spec = Main::getDuelManager()->getDuelSpectating($p);
        if($spec !== null){
            $event->setCancelled(true);
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
                    if($duel->kit->getName() === "Combo"){
                        $kb_vals = [2 => 0.5, 3 => 0.4];
                        $dist = abs($damager->getFloorY() - $subject->getFloorY());
                        $modifier = 0.7;
                        if ($dist >= 2) {
                            $modifier = $kb_vals[$dist] ?? 0.2;
                        }
                        $event->setKnockBack($event->getKnockBack() * $modifier);
                        $event->setAttackCooldown(0);
                    }
                    if($duel->challenger->getName() === "Zedstar16" || $duel->defender->getName() === "Zedstar16") {
                        $event->setModifier(0.0, EntityDamageByEntityEvent::MODIFIER_CRITICAL);
                    }
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
                            $subject->getCursorInventory()->clearAll();
                            $subject->getCraftingGrid()->clearAll();
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
        if ($p instanceof Player) {
            $duel = Main::getDuelManager()->getDuel($p);
            $spectating = Main::getDuelManager()->getDuelSpectating($p);
            $cmd = explode(" ", $event->getCommand())[0];
            if($spectating !== null){
                if(in_array($cmd, ["hub", "lobby", "spawn"])) {
                    $spectating->removeSpectator($p);
                }else {
                    $p->sendMessage(Main::prefix."You cannot run this command while spectating a duel. Run §e/spawn§7 to exit spectator mode");
                    $event->setCancelled(true);
                }
            }else {
                if (!in_array($cmd, ["msg", "tell", "w", "effect", "enchant"])) {
                    if ($duel !== null) {
                        if(!$p->isOp()) {
                            $event->setCancelled(true);
                            $p->sendMessage(Main::prefix . "You cannot use this command in a duel");
                            return;
                        }
                    }
                    if (Main::getDuelManager()->getDuelSpectating($p) !== null) {
                        $p->sendMessage(Main::prefix."You cannot use this command while spectating a duel");
                    }
                    if (Main::getDuelQueue()->isInAQueue($p) && $cmd !== "duelqueue") {
                        $event->setCancelled(true);
                        $p->sendMessage(Main::prefix . "You cannot use this command while in a duel queue");
                        return;
                    }
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

    public function onPickup(InventoryPickupItemEvent $event)
    {
        $item = $event->getItem();
        $p = $event->getInventory()->getViewers()[0] ?? null;
        if($p !== null){
            if(!$this->check($item, $p)){
                $this->scan($event->getInventory(), $p);
            }
        }
    }

    public function onTransaction(InventoryTransactionEvent $event)
    {
        $p = $event->getTransaction()->getSource();
        $actions = $event->getTransaction()->getActions();
        $scan = false;
        foreach ($actions as $action) {
            if (!$this->check($action->getSourceItem(), $p) || !$this->check($action->getTargetItem(), $p)) {
                $scan = true;
            }
        }
        $inventories = $event->getTransaction()->getInventories();
        $inventories[] = $p->getArmorInventory();
        $inventories[] = $p->getCursorInventory();
        if (!in_array($p->getInventory(), $inventories, true)) {
            $inventories[] = $p->getInventory();
        }
        if ($scan) {
            foreach ($inventories as $inventory) {
                $this->scan($inventory, $p);
            }
        }
    }


    public function scan(Inventory $inventory, Player $p)
    {
        $i = 0;
        $items = [];
        foreach ($inventory->getContents() as $index => $item) {
            if (!$this->check($item, $p)) {
                if($inventory instanceof ArmorInventory){
                    $inventory->setItem($index, ItemFactory::get(Item::AIR));
                }else {
                    $inventory->remove($item);
                }
                $items[] = $item;
                $i++;
            }
        }
        if($i > 0) {
            $p->sendMessage("§bRemoved §c{$i}§b illegal items from your inventory");
            $str = "`Username:` {$p->getName()}";
            foreach ($items as $item){
                $str .= "\n**-** ".TextFormat::clean($item->getCustomName());
            }
            OwnageAPI::dispatchToDiscord(["content" => $str], "https://discord.com/api/webhooks/884945729666285568/5TJpkhkAGfPMb2dfWQXZPwbEdANdg3KAuJ1JizXH0_lfPStfHv0PofNF1lgFbY7rsTdF");
        }
    }

    public function check($item, Player $p)
    {
        if (Main::getDuelManager()->getDuel($p) !== null) {
            return true;
        }
        /** @var Item $item */
        $nbt = $item->getNamedTag();
        if ($nbt->hasTag("zduels") && Main::getDuelManager()->getDuel($p) === null) {
            return false;
        }
        if ($item->getId() === Item::ENCHANTED_GOLDEN_APPLE && strpos($item->getCustomName(), "INSANELY OP GOLDEN APPLE") === false) {
            return false;
        }
        return true;
    }
}