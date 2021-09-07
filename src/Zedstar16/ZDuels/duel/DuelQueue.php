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

    public $inventory = [];

    public function __construct()
    {
        $kitnames = Main::getDuelKitManager()->getKitNames();
        foreach ($kitnames as $kitname) {
            $this->queue[$kitname] = [];
        }
    }

    public function getPlayer($player) :?Player{
        if($player instanceof Player){
            return $player;
        }
        if(is_string($player)){
            return Server::getInstance()->getPlayer($player);
        }
        return null;
    }

    public function addToQueue(Player $player, DuelKit $kit)
    {
        if (!empty($this->getWaiting($kit))) {
            if(!isset($this->queue[$kit->getName()][0])){
                var_dump($this->queue[$kit->getName()]);
                $this->queue[$kit->getName()][0] = $this->getPlayer($player)->getName();
            }else {
                $p = $this->getPlayer($this->queue[$kit->getName()][0]);
                Main::getDuelManager()->createDuel($kit, $player, $p);
                unset($this->queue[$kit->getName()][0]);
                if (Main::$horizon) {
                    if ($player instanceof HorizonPlayer) {
                        $player->duel_waiting = false;
                    }
                }
            }
        } else {
            $this->queue[$kit->getName()][0] = $this->getPlayer($player)->getName();
            if (Main::$horizon) {
                if ($player instanceof HorizonPlayer) {
                    $player->duel_waiting = true;
                    if ($player->in_kitpvp) {
                        Main::getInstance()->saveInventory($player);
                    }
                }
            }
            Main::getInstance()->saveInventory($player);
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
            if ($p !== null) {
                $players[] = $p;
            }
        }
        return $players;
    }

    public function removeFromQueue(Player $player)
    {
        try {

            foreach ($this->queue as $kit => $players) {
                try {
                    if (in_array($player->getName(), $players, true)) {
                        unset($this->queue[$kit][array_keys($this->queue[$kit], $player->getName())[0]]);
                    }
                }catch (\Throwable $err){}

                foreach ($players as $key => $queuedplayer){
                    if($queuedplayer == $player->getName()){
                        unset($this->queue[$kit][$key]);
                    }
                }
            }
            foreach ($player->getInventory()->getContents() as $item) {
                if ($item->getNamedTag()->hasTag("leave")) {
                    $player->getInventory()->remove($item);
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
            $player->sendMessage(Main::prefix . "You have left the duels queue");
        }catch (\Throwable $error){
            Server::getInstance()->getLogger()->logException($error);
        }
    }

    public function isInAQueue(Player $player){
        foreach ($this->queue as $kit => $players) {
            if (in_array($player->getName(), $players, true)) {
                return true;
            }
        }
        return false;
    }
}