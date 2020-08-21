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


namespace Zedstar16\ZDuels\command;


use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use Zedstar16\ZDuels\constant\Constants;
use Zedstar16\ZDuels\Main;

class DuelAdminCommand extends Command
{

    public function __construct(string $name, string $description = "", string $usageMessage = null, array $aliases = [])
    {
        $this->setPermission("zduels.admin");
        parent::__construct($name, $description, $usageMessage, $aliases);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        $leveldata = Main::getInstance()->getJson("levels.json");
        if ($sender instanceof Player) {
            $help = implode("\n", [
                "§aUsage for Duels Admin",
                "§9- §b/duela level setspawn <1/2> §a- Set spawn position for current duel level",
                "§9- §b/duela kit list §a- List all duel kits",
                "§9- §b/duela kit set <kitname> §a- Set a duel kit as your current inventory",
                "§9- §b/duela kit remove <kitname> §a- Delete a duelkit",
                "§9- §b/duela reload §a- Reload configs so you do not have to restart server",
                "§9- §b/duela rename <name> §a- Renames the item in your hand, for creating kits"
            ]);
            if (isset($args[0])) {
                switch ($args[0]) {
                    case "level":
                        if (count($args) == 3) {
                            if ($args[1] == "setspawn") {
                                if ($args[2] == "1" or $args[2] == "2") {
                                    if (count(Main::getDuelLevelManager()->getLevelsInUse()) == 0) {
                                        Main::getDuelLevelManager()->setDuelLevelSpawn($sender, (int)$args[2] - 1);
                                        $sender->sendMessage("§aSuccessfully set Spawn position $args[2] in level {$sender->getLevel()->getName()} to your current position");
                                    } else $sender->sendMessage("§cYou cannot run this command while there is an ongoing duel/level in use");
                                }
                            }
                        } else $sender->sendMessage("§cUsage: §6/duela level setspawn <1/2>");
                        break;
                    case "kit":
                        $kithelp = implode("\n", [
                            "§aUsage for /duela kit",
                            "§9- §b/duela kit list",
                            "§9- §b/duela kit set <kitname>",
                            "§9- §b/duela kit remove <kitname>"
                        ]);
                        if ($args[1] == "list") {
                            $sender->sendMessage("§aList of current kits:\n§9- §b" . implode("\n§9- §b", Main::getDuelKitManager()->getKitNames()));
                        } elseif (count($args) == 3) {
                            if ($args[1] == "set") {
                                $this->setKit($args[2], $sender);
                                Main::getDuelKitManager()->loadKits();
                                $sender->sendMessage("§aSet kit $args[2] to your inventory contents");
                            } elseif ($args[1] == "remove") {
                                $kits = Main::getKits();
                                if (isset($kits[$args[2]])) {
                                    unset($kits[$args[2]]);
                                    yaml_emit_file(Main::getInstance()->getDataFolder() . "kits.yml", $kits);
                                    Main::getDuelKitManager()->loadKits();
                                    $sender->sendMessage("§aSuccessfully removed kit $args[2]");
                                } else $sender->sendMessage("§cThere is no kit by name $args[2]");
                            } else $sender->sendMessage($kithelp);
                        } else $sender->sendMessage($kithelp);
                        break;
                    case "reload":
                        Main::getInstance()->getConfig()->reload();
                        if (count(Main::getDuelLevelManager()->getLevelsInUse()) == 0) {
                            Main::getDuelLevelManager()->loadAllDuelLevels();
                        }
                        Main::getDuelKitManager()->loadKits();
                        $sender->sendMessage("§aReloaded all configurations");
                        break;
                    case "rename":
                        if(isset($args[1])){
                            array_shift($args);
                            $name = implode(" ", $args);
                            $item = $sender->getInventory()->getItemInHand();
                            $item->setCustomName($name);
                            $sender->getInventory()->setItemInHand($item);
                            $sender->sendMessage("§aRenamed the item in your hand to §r$name");
                        }else $sender->sendMessage("§cUsage: §6/duela rename (new name)");
                        break;
                    case "force":
                        $p = $sender->getServer()->getPlayer($args[1]);
                        if ($p !== null) {
                            Main::getDuelManager()->createDuel(Main::getDuelKitManager()->getKit($args[2] ?? "gapple"), $sender, $p);
                        } else $sender->sendMessage(Main::msg("target-not-online", "target", $args[1]));
                        break;
                    default:
                        $sender->sendMessage($help);
                        break;
                }
            }else $sender->sendMessage($help);
        } else $sender->sendMessage("§cYou can only run this command in-game");
    }

    public function setKit($kitname, Player $p)
    {
        $kits = Main::getKits();
        $kits[$kitname]["armor"] = $this->indexContents($p->getArmorInventory()->getContents());
        $kits[$kitname]["item"] = $this->indexContents($p->getInventory()->getContents());
        yaml_emit_file(Main::getInstance()->getDataFolder() . "kits.yml", $kits);
    }

    public function indexContents(array $contents)
    {
        $data = [];
        foreach ($contents as $index => $item) {
            $store = [];
            $store[] = $item->getId();
            $store[] = $item->getDamage();
            $store[] = $item->getCount();
            if ($item->hasCustomName() or $item->hasEnchantments()) {
                if ($item->hasCustomName()) {
                    $store[3] = str_replace("§", "&", $item->getCustomName());
                } else $store[3] = "DEFAULT";
                if ($item->hasEnchantments()) {
                    foreach ($item->getEnchantments() as $enchantment) {
                        $store[] = Constants::$enchantment_by_id[$enchantment->getId()];
                        $store[] = $enchantment->getLevel();
                    }
                }
            }
            $data[$index] = implode(":", $store);
        }
        return $data;
    }

}