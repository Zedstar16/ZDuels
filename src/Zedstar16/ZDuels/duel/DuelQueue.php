<?php


namespace Zedstar16\ZDuels\duel;


use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\Player;
use pocketmine\Server;
use Zedstar16\HorizonCore\HorizonPlayer;
use Zedstar16\ZDuels\Main;

class DuelQueue
{

    public $queue = [];

    public function __construct()
    {
        $kitnames = Main::getDuelKitManager()->getKitNames();
        foreach ($kitnames as $kitname) {
            $this->queue[$kitname] = [];
        }
    }

    public function addToQueue(Player $player, DuelKit $kit)
    {
        if (!empty($this->getWaiting($kit))) {
            $p = $this->queue[$kit->getName()][0];
            Main::getDuelManager()->createDuel($kit, $player, $p);
            unset($this->queue[$kit->getName()][0]);
            if (Main::$horizon) {
                if ($player instanceof HorizonPlayer) {
                    $player->duel_waiting = false;
                }
            }
        } else {
            $this->queue[$kit->getName()][] = $player;
            if (Main::$horizon) {
                if ($player instanceof HorizonPlayer) {
                    $player->duel_waiting = true;
                    if ($player->in_kitpvp) {
                        Main::getInstance()->saveInventory($player);
                    }
                }
            }
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $item = ItemFactory::get(ItemIds::REDSTONE_DUST);
            $item->setCustomName("§aLeave Duel Waiting Queue");
            $nbt = $item->getNamedTag();
            $nbt->setString("leave", "leave");
            $item->setCompoundTag($nbt);
            $item->addEnchantment(new EnchantmentInstance(new Enchantment(255, "", Enchantment::RARITY_COMMON, Enchantment::SLOT_ALL, Enchantment::SLOT_NONE, 1)));
            $player->getInventory()->addItem($item);
        }
    }

    public function getWaiting(DuelKit $kit): array
    {
        $players = [];
        foreach ($this->queue[$kit->getName()] as $player) {
            $p = Server::getInstance()->getPlayer($player);
            if (Server::getInstance()->getPlayer($player) !== null) {
                $players[] = $p;
            }
        }
        return $players;
    }

    public function removeFromQueue(Player $player)
    {
        foreach ($this->queue as $kit => $players) {
            if (in_array($player, $players)) {;
                unset($this->queue[$kit][array_keys($this->queue[$kit], $player)[0]]);
            }
        }
        if (Main::$horizon) {
            if ($player instanceof HorizonPlayer) {
                $player->duel_waiting = false;
                if ($player->in_kitpvp) {
                    if (Main::getInstance()->hasSavedInventory($player)) {
                        Main::getInstance()->loadSavedInventory($player);
                    }
                }
            }
        } elseif (Main::getInstance()->hasSavedInventory($player)) {
            Main::getInstance()->loadSavedInventory($player);
        }
        $player->sendMessage("You have left the duels queue");

    }
}