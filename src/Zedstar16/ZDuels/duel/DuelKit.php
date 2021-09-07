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


namespace Zedstar16\ZDuels\duel;


use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\Player;
use Zedstar16\HorizonCore\components\Constants;
use Zedstar16\HorizonCore\managers\KitManager;
use Zedstar16\ZDuels\Main;

class DuelKit
{

    private $kitname;

    public function __construct($kitname)
    {
        $this->kitname = $kitname;
    }

    public function set(Player $player)
    {
        if(!Main::getInstance()->hasSavedInventory($player)) {
            Main::getInstance()->saveInventory($player);
        }
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $this->setInventory($player);
        $this->setArmor($player);
        if(Main::$horizon){
            $kit = KitManager::getKit($player, $this->kitname, Constants::KIT_DUELS);
            $player->getArmorInventory()->setContents(KitManager::indexContents($kit["armor"]));
            $player->getInventory()->setContents(KitManager::indexContents($kit["inventory"]));
        }else {
            $this->setInventory($player);
            $this->setArmor($player);
        }
    }

    public function getName(): String
    {
        return $this->kitname;
    }

    public function isSpecific(): bool
    {
        return in_array($this->kitname, Main::getDuelKitManager()->getSpecificKitnames(), true);
    }

    private function setArmor(Player $player)
    {
        $items = Main::getKits()[$this->kitname]["armor"];
        $armor = [];
        foreach ($items as $item) {
            $armor[] = $this->parseItem($item);
        }
        $player->getArmorInventory()->setContents($armor);
    }

    private function setInventory(Player $player)
    {
        $items = Main::getKits()[$this->kitname]["item"];
        foreach ($items as $index => $item) {
            $player->getInventory()->setItem($index, $this->parseItem($item));
        }
    }

    private function parseItem(String $string): Item
    {
        $data = explode(":", $string);
        $id = $data[0];
        $damage = $data[1];
        $count = $data[2];
        $item = ItemFactory::get($id, $damage, $count);
        $nbt = $item->getNamedTag();
        $nbt->setString("zduels", "duel");
        $item->setNamedTag($nbt);
        if (isset($data[3])) {
            if ($data[3] !== "DEFAULT") {
                $item->setCustomName(str_replace("&", "§", $data[3]));
            }
            if (isset($data[4])) {
                $data = array_slice($data, 4);
                for ($i = 0, $iMax = count($data); $i < $iMax; $i++) {
                    if (is_int($i / 2)) {
                        $enchant = Enchantment::getEnchantmentByName($data[$i]);
                        $instance = new EnchantmentInstance($enchant, $data[$i + 1]);
                        $item->addEnchantment($instance);
                        $i++;
                    } else $i++;
                }
                return $item;
            } else return $item;
        } else return $item;
    }

}