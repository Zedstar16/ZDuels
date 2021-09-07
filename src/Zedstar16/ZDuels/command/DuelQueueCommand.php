<?php


namespace Zedstar16\ZDuels\command;


use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;
use Zedstar16\ZDuels\libs\FormAPI\CustomForm;
use Zedstar16\ZDuels\libs\FormAPI\SimpleForm;
use Zedstar16\ZDuels\Main;

class DuelQueueCommand extends Command
{
    public function __construct(string $name, string $description = "", string $usageMessage = null, array $aliases = [])
    {
        parent::__construct($name, $description, $usageMessage, $aliases);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if ($sender instanceof Player) {
            $this->openMain($sender);
        } else $sender->sendMessage(Main::prefix."You can only run this command in-game");
    }

    public function openMain(Player $player)
    {
        $in_queue = Main::getDuelQueue()->isInAQueue($player);
        $form = new SimpleForm(function (Player $player, $data) use($in_queue){
            if ($data === null) {
                return;
            }
            if ($in_queue && in_array($data, [0, 2])) {
                if ($data === 2) {
                    Main::getDuelQueue()->removeFromQueue($player);
                } else {
                    $player->sendMessage(Main::prefix."You are already in the duels queue");
                }
                return;
            }
            if ($data === 0) {
                $this->openDuelPlayerForm($player);
            } elseif ($data === 1) {
                $this->openUI($player);
            }
        });
        $form->addButton("§8Duel a player");
        $form->addButton("§8Duels Queue");
        if ($in_queue) {
            $form->addButton("§4Leave Duel Queue");
        }
        $form->setContent("Select a Duel Gamemode");
        $form->setTitle("§8§l(§9DUELS§8)§r§7 ");
        $player->sendForm($form);
    }

    public function openDuelPlayerForm(Player $player)
    {
        if (Main::getDuelQueue()->isInAQueue($player)) {
            $player->sendMessage(Main::prefix."You are already in the duels queue");
            return;
        }
        $form = new CustomForm(function ($player, $data = null): void {
            if ($data === null) {
                return;
            }
            Server::getInstance()->dispatchCommand($player, "duel ".$data[1]);
        });
        $form->setTitle("§8§l(§9DUELS§8)§r§7 ");
        $form->addLabel("Duel a player");
        $form->addInput("Enter username", "username");
        $player->sendForm($form);

    }

    public function openUI(Player $player)
    {
        $buttons = [];
        $kitnames = Main::getDuelKitManager()->getKitNames();
        $cfgbuttons = Main::getInstance()->getConfig()->get("duelmenu-buttons");
        $in_queue = Main::getDuelQueue()->isInAQueue($player);
        foreach ($kitnames as $kitname) {
            if (isset($cfgbuttons[$kitname])) {
                $buttons[] = [
                    "kitname" => $kitname,
                    "button" => $cfgbuttons[$kitname]["button"],
                    "url" => $cfgbuttons[$kitname]["url"] ?? ""
                ];
            }
        }
        $form = new SimpleForm(function (Player $player, $data) use ($buttons, $in_queue) {
            if ($data === null) {
                return;
            }
            if ($in_queue) {
                $player->sendMessage(Main::prefix."You are already in a queue");
                return;
            }
            if (isset($buttons[$data])) {
                $kit = Main::getDuelKitManager()->getKit($buttons[$data]["kitname"]);
                Main::getDuelQueue()->addToQueue($player, $kit);
            } else $player->sendMessage(Main::prefix."Error, invalid option");
        });
        $form->setContent("Select a Duel Gamemode\n§b".Main::getDuelManager()->getTotalDuelPlayers()." players§f currently dueling");
        $form->setTitle("§8§l(§9DUELS§8)§r§7 ");
        $levels = Main::getDuelLevelManager()->getLevelsInUse();
        $playing = [];
        foreach ($levels as $level) {
            $kit = $level->getKitName();
            if (!isset($playing[$kit])) {
                $playing[$kit] = 0;
            }
            $playing[$kit] += count($level->getLevel()->getPlayers());
        }
        $waiting = [];
        foreach (Main::getDuelQueue()->queue as $kit => $players) {
            $waiting[$kit] = count($players);
        }
        foreach ($buttons as $data) {
            $kit = $data["kitname"];
            if (strlen($data["url"]) > 1) {
                $url = strpos($data["url"], "http")  !== false;
                $form->addButton($data["button"] . "\n§r§0" . ($playing[$kit] ?? "0") . " §8playing §f| §0$waiting[$kit] §8waiting", $url ? SimpleForm::IMAGE_TYPE_URL : SimpleForm::IMAGE_TYPE_PATH, $data["url"]);
            } else $form->addButton($data["button"] . "\n§r§0" . ($playing[$kit] ?? "0") . " §8playing §f| §0$waiting[$kit] §8waiting");
        }
        $player->sendForm($form);
    }

}