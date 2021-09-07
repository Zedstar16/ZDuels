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

declare(strict_types=1);

namespace Zedstar16\ZDuels;

use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\level\generator\GeneratorManager;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\scheduler\ClosureTask;
use Zedstar16\_Api\OwnageAPI;
use Zedstar16\ZDuels\command\DuelAdminCommand;
use Zedstar16\ZDuels\command\DuelCommand;
use Zedstar16\ZDuels\command\DuelQueueCommand;
use Zedstar16\ZDuels\constant\Constants;
use Zedstar16\ZDuels\duel\Duel;
use Zedstar16\ZDuels\duel\DuelQueue;
use Zedstar16\ZDuels\generator\VoidGenerator;
use Zedstar16\ZDuels\libs\FormAPI\SimpleForm;
use Zedstar16\ZDuels\libs\invmenu\inventory\InvMenuInventory;
use Zedstar16\ZDuels\libs\invmenu\InvMenu;
use Zedstar16\ZDuels\libs\invmenu\InvMenuHandler;
use Zedstar16\ZDuels\managers\DuelKitManager;
use Zedstar16\ZDuels\managers\DuelLevelManager;
use Zedstar16\ZDuels\managers\DuelManager;

class Main extends PluginBase implements Listener
{

    /** @var Main */
    private static $instance;
    /** @var array */
    public $data, $cps = [];
    /** @var DuelManager */
    private static $duelmanager;
    /** @var DuelLevelManager */
    private static $duellevelmanager;
    /** @var DuelKitManager */
    private static $duelkitmanager;
    /** @var DuelQueue */
    private static $duelqueue;
    public static $inv = [];
    public static $horizon = false;

    public const prefix = "§8§l(§9DUELS§8)§r§7 ";

    public function onEnable(): void
    {
        self::$instance = $this;
        $this->getServer()->getCommandMap()->register("duel", new DuelCommand("duel", "Challenge a player to a duel!", "/duel <player/accept>"));
        if($this->getServer()->getPort() !== 19134 || OwnageAPI::getServerManager()->getServerName() !== "KitPvP") {
            $this->getServer()->getCommandMap()->register("duelqueue", new DuelQueueCommand("duelqueue", "Open Duel Menu", "/duelqueue"));
        }
        $this->getServer()->getCommandMap()->register("duela", new DuelAdminCommand("duela", "Duels Admin management command"));
        $this->getServer()->getPluginManager()->addPermission(new Permission("zduels.admin", "ZDuels admin perm", Permission::DEFAULT_OP));
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }
        $this->saveResource("config.yml");
        $this->saveResource("kits.yml");
        self::$duelmanager = new DuelManager();
        self::$duellevelmanager = new DuelLevelManager();
        self::$duelkitmanager = new DuelKitManager();
        self::$duelqueue = new DuelQueue();
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (int $currentTick): void {
            foreach (self::$duelmanager->getDuels() as $duel) {
                $duel->update();
                if ($duel->baseTimer <= -60) {
                    self::getDuelManager()->terminateDuel($duel);
                }
            }
        }), 20);
        if ($this->getServer()->getPluginManager()->getPlugin("HorizonCore") !== null) {
            self::$horizon = true;
        }
    }

    public function onLoad()
    {
        GeneratorManager::addGenerator(VoidGenerator::class, "void", true);
    }

    public static function getInstance(): Main
    {
        return self::$instance;
    }

    public static function getDuelManager(): DuelManager
    {
        return self::$duelmanager;
    }

    public static function getDuelLevelManager(): DuelLevelManager
    {
        return self::$duellevelmanager;
    }

    public static function getDuelKitManager(): DuelKitManager
    {
        return self::$duelkitmanager;
    }

    public static function getDuelQueue(): DuelQueue
    {
        return self::$duelqueue;
    }

    public static function getKits()
    {
        return yaml_parse_file(self::getInstance()->getDataFolder() . "kits.yml");
    }

    public function clicked(Player $p)
    {
        $name = $p->getName();
        $time = microtime(true);
        if (!isset($this->data[$name])) {
            $this->data[$name] = [];
            $this->cps[$name] = 0;
            return;
        }
        $cps = 0;
        array_unshift($this->data[$name], $time);
        if (count($this->data[$name]) >= 101) {
            //this means cps counts will cap out at 100 lol if they get above its def auto :P
            array_pop($this->data[$name]);
        }
        if (!empty($this->data[$name])) {
            $cps = count(
                    array_filter(
                        $this->data[$name],
                        static function (float $t) use ($time): bool {
                            return ($time - $t) <= 1;
                        }
                    )
                ) - 1;
        }
        if ($cps <= 0) {
            $cps = 1;
        }
        $duel = self::getDuelManager()->getDuel($p);
        $this->cps[$name] = ["cps" => $cps, "time" => time()];
        if ($duel !== null) {
            $duel->stats[$name]["clicks"]++;
            $duel->displayPopup();
        }
    }

    public static function getCPS(Player $player)
    {
        $cps = 0;
        $name = $player->getName();
        if (isset(self::getInstance()->cps[$name])) {
            $data = self::getInstance()->cps[$player->getName()];
            $cps = (time() - $data["time"]) <= 1 ? $data["cps"] : 0;
        }
        return $cps;
    }

    public function sendGameEndUI(Player $p, Duel $duel)
    {
        $opponent = $duel->getOpponent($p);
        $form = new SimpleForm(function (Player $player, $data) use ($p, $duel) {
            $opponent = $duel->getOpponent($player);
            if ($data === null) {
                return;
            }
            if ($opponent === null) {
                $player->sendMessage(Main::msg("opponent-not-online"));
                return;
            }
            if ($data == 0) {
                self::getDuelManager()->duelRequest($duel->kit, $player, $opponent);
            } elseif ($data == 1) {
                $this->showInventory($player, $opponent, $duel);
            }
        });
        $form->setTitle(self::form("gameend.title"));
        $result = $duel->results[$p->getName()];
        $pstat = $duel->stats[$p->getName()];
        $opstat = $duel->stats[$duel->getOpponent($p)->getName()];
        $stats = "\n" . Main::form("gameend.content-stats",
                ["op-clicks", "op-hits", "clicks", "hits", "damage-taken", "damage-dealt"],
                [$opstat["clicks"], $opstat["hits"], $pstat["clicks"], $pstat["hits"], (int)$pstat["damage_taken"], (int)$pstat["damage_dealt"]]
            );
        switch ($result) {
            case Constants::DUEL_WIN:
                $form->setContent(self::form("gameend.content-winner", ["opponent", "kit"], [$opponent->getName(), $duel->kit->getName()]) . $stats);
                break;
            case Constants::DUEL_DRAW:
                $form->setContent(self::form("gameend.content-draw", ["opponent", "kit"], [$opponent->getName(), $duel->kit->getName()]) . $stats);
                break;
            case Constants::DUEL_LOST:
                $form->setContent(self::form("gameend.content-loser", ["opponent", "opponent-health", "kit"], [$opponent->getName(), $duel->winner_health, $duel->kit->getName()]) . $stats);
                break;
        }
        $form->addButton(self::form("gameend.button-rematch"));
        $form->addButton(self::form("gameend.button-see-inventory"));
        $p->sendForm($form);
    }

    private function showInventory(Player $viewer, Player $subject, Duel $duel)
    {
        $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
        $menu->readonly(true);
        $menu->setInventoryCloseListener(function (Player $player, InvMenuInventory $inventory) use ($duel) {
            $inv = $player->getInventory()->getContents();
            foreach ($inv as $item) {
                if ($item->getNamedTag()->hasTag("chest")) {
                    $player->getInventory()->remove($item);
                }
            }
            if ($duel !== null) {
                try {
                    $this->sendGameEndUI($player, $duel);
                } catch (\Throwable $error) {
                    $this->getLogger()->error("Error sending game end UI to player, " . $error->getMessage());
                }
            }
        });
        $menu->setName($subject->getName() . "'s Match Inventory");
        $menu->getInventory()->setContents($this->getMatchInventoryContents($subject, $duel));
        $menu->send($viewer);
    }

    private function getMatchInventoryContents(Player $player, Duel $duel): array
    {
        $data = $duel->match_inventories;
        $inventories = $data[$player->getName()];
        $content = [];
        foreach ($inventories["inventory"] as $index => $item) {
            $content[$index] = $this->jsonDeserialize($item);
        }
        $armor_slots = [
            0 => 47,
            1 => 48,
            2 => 49,
            3 => 50
        ];
        foreach ($inventories["armorinventory"] as $index => $armor) {
            $content[$armor_slots[$index]] = $this->jsonDeserialize($armor);
        }
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

    public function saveInventory(Player $player)
    {
        echo "\n\nSaving invetnroy for {$player->getName()}\n\n";
        $data = $this->getJson("inventory.json");
        foreach ($player->getInventory()->getContents() as $slot => $item) {
            $data[$player->getName()]["inventory"][$slot] = $item->jsonSerialize();
        }
        foreach ($player->getArmorInventory()->getContents() as $slot => $item) {
            $data[$player->getName()]["armorinventory"][$slot] = $item->jsonSerialize();
        }
        $this->saveJson("inventory.json", $data);
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
    }

    public function hasSavedInventory(Player $player): bool
    {
        return isset($this->getJson("inventory.json")[$player->getName()]);
    }

    public function loadSavedInventory(Player $player)
    {
        if ($this->hasSavedInventory($player)) {
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $rawdata = $this->getJson("inventory.json");
            $data = $rawdata[$player->getName()];
            if (isset($data["inventory"])) {
                foreach ($data["inventory"] as $slot => $item) {
                    $player->getInventory()->setItem($slot, $this->jsonDeserialize($item));
                }
            }
            if (isset($data["armorinventory"])) {
                foreach ($data["armorinventory"] as $slot => $item) {
                    $player->getArmorInventory()->setItem($slot, $this->jsonDeserialize($item));
                }
            }
            unset($rawdata[$player->getName()]);
            $this->saveJson("inventory.json", $rawdata);
        }
    }


    public static function msg($key, $variables = [], $replacement = []): string
    {
        $cfg = self::getInstance()->getConfig();
        if (!empty($variables)) {
            if (is_array($variables)) {
                $toreplace = [];
                foreach ($variables as $variable) {
                    $toreplace[] = "{" . $variable . "}";
                }
            } else {
                $toreplace = "{" . $variables . "}";
            }
        } else $toreplace = [];
        if ($cfg->getNested("messages.$key") !== null) {
            return str_replace($toreplace, $replacement, str_replace(["&", '\n'], ["§", "\n"], $cfg->getNested("messages.$key")));
        } else {
            $options = is_array($toreplace) ? implode(" ", $toreplace) : $toreplace;
            $cfg->setNested("messages.$key", "Config Me, OPTIONS: $options");
            $cfg->save();
            return "The value messages.$key needs to be set in the config.yml";
        }
    }

    public static function form($key, $variables = [], $replacement = []): string
    {

        $cfg = self::getInstance()->getConfig();
        if (!empty($variables)) {
            if (is_array($variables)) {
                $toreplace = [];
                foreach ($variables as $variable) {
                    $toreplace[] = "{" . $variable . "}";
                }
            } else {
                $toreplace = "{" . $variables . "}";
            }
        } else $toreplace = [];
        array_push($toreplace, "{line}");
        array_push($replacement, "\n");
        if ($cfg->getNested("forms.$key") !== null) {
            return str_replace($toreplace, $replacement, $cfg->getNested("forms.$key"));
        } else {
            $cfg->setNested("forms.$key", "Config Me, OPTIONS: " . implode(" ", $toreplace));
            $cfg->save();
            return "The value forms.$key needs to be set in the config.yml";
        }
    }

    # the recursive copy and delete are from stackoverflow

    public static function recurseCopy($src, $dst, $childFolder = '')
    {
        $dir = opendir($src);
        @mkdir($dst);
        if ($childFolder != '') {
            @mkdir($dst . '/' . $childFolder);
            while (false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..')) {
                    if (is_dir($src . '/' . $file)) {
                        self::recurseCopy($src . '/' . $file, $dst . '/' . $childFolder . '/' . $file);
                    } else {
                        copy($src . '/' . $file, $dst . '/' . $childFolder . '/' . $file);
                    }
                }
            }
        } else {
            // return $cc;
            while (false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..')) {
                    if (is_dir($src . '/' . $file)) {
                        self::recurseCopy($src . '/' . $file, $dst . '/' . $file);
                    } else {
                        copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
            }
        }
        closedir($dir);
    }

    public static function rmdirRecursive(string $dir)
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::rmdirRecursive("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    public function scoreStats($name, $type)
    {
        $data = $this->getJson("stats.json");
        if (!isset($data[$name][$type])) {
            $data[$name][$type] = 1;
        } else $data[$name][$type]++;
        $this->saveJson("stats.json", $data);
    }

    public function jsonDeserialize(array $data): Item
    {
        $nbt = "";
        if (isset($data["nbt"])) {
            $nbt = $data["nbt"];
        } elseif (isset($data["nbt_hex"])) {
            $nbt = hex2bin($data["nbt_hex"]);
        } elseif (isset($data["nbt_b64"])) {
            $nbt = base64_decode($data["nbt_b64"], true);
        }
        return ItemFactory::get(
            (int)$data["id"],
            (int)($data["damage"] ?? 0),
            (int)($data["count"] ?? 1),
            (string)$nbt
        );
    }


    public function getJson($filename)
    {
        $filename = $this->getDataFolder() . $filename;
        if (file_exists($filename)) {
            return json_decode(file_get_contents($filename), true);
        } else file_put_contents($filename, json_encode([]));
        return [];
    }

    public function saveJson($filename, $data)
    {
        $filename = $this->getDataFolder() . $filename;
        file_put_contents($filename, json_encode($data));
    }


    public function onDisable(): void
    {
        foreach ($this->getServer()->getOnlinePlayers() as $player){
            $duel = Main::getDuelManager()->getDuel($player);
            if ($duel !== null) {
                $player->getInventory()->clearAll();
                $player->getArmorInventory()->clearAll();
            }
        }
        $this->cps = [];
        $this->data = [];
    }
}
