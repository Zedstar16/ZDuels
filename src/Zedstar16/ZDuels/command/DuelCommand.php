<?php


namespace Zedstar16\ZDuels\command;


use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;
use Zedstar16\ZDuels\constant\Constants;
use Zedstar16\ZDuels\libs\FormAPI\CustomForm;
use Zedstar16\ZDuels\libs\FormAPI\SimpleForm;
use Zedstar16\ZDuels\Main;
use Zedstar16\ZDuels\managers\DuelManager;

class DuelCommand extends Command
{

    private $cooldown = [];

    public function __construct(string $name, string $description = "", string $usageMessage = null, array $aliases = [])
    {
        parent::__construct($name, $description, $usageMessage, $aliases);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        $server = Server::getInstance();
        $mgr = Main::getDuelManager();
        if ($sender instanceof Player) {
            if (Main::getDuelQueue()->isInAQueue($sender)) {
                $sender->sendMessage(Main::prefix . "You are already in the duels queue");
                return true;
            }
            if (isset($args[0])) {
                if ($args[0] == "accept") {
                    $data = $mgr->getDuelRequest($sender);
                    if ($data !== null) {
                        $kit = $data["kit"];
                        $challenger = $data["challenger"];
                        if (Main::getDuelLevelManager()->freeLevelExists($kit)) {
                            $mgr->duelAccept($kit, Server::getInstance()->getPlayer($challenger), $sender);
                        } else $sender->sendMessage(Main::msg("no-free-duel-levels"));
                    } else $sender->sendMessage(Main::msg("no-active-duel-requests"));
                } elseif ($args[0] === "spectate") {
                    if (!isset($args[1])) {
                        $this->sendSpectatorUI($sender);
                        return true;
                    }
                    $usr = $server->getPlayer($args[1]);
                    if ($usr instanceof Player) {
                        $duel = Main::getDuelManager()->getDuel($usr);
                        if ($duel !== null && in_array($duel->stage, [Constants::DUEL_INIT, Constants::DUEL_IN_PROGRESS])) {
                            $duel->addSpectator($sender);
                        } else $sender->sendMessage(Main::prefix . "The specified player is not currently in a duel");
                    } else $sender->sendMessage(Main::prefix . "The specified player is not online");
                } else {
                    $pn = $sender->getName();
                    $ct = floatval(Main::getInstance()->getConfig()->get("duel-request-cooldown"));
                    if ((!isset($this->cooldown[$pn])) || (($this->cooldown[$pn] + $ct - time() <= 0))) {
                        $p = $server->getPlayer($args[0]);
                        if ($p === $sender) {
                            return true;
                        }
                        if ($p === null) {
                            $sender->sendMessage(Main::msg("target-not-online", "target", $args[0]));
                            return true;
                        }
                        $duel = Main::getDuelManager()->getDuel($p);
                        if ($duel !== null) {
                            $sender->sendMessage(Main::prefix . "§f{$p->getName()}§7 is already in a duel");
                            return true;
                        }
                        $this->sendDuelUI($sender, $p);
                    } else $sender->sendMessage(Main::msg("duel-cooldown", "cooldown", ($this->cooldown[$pn] + $ct - time())));
                }
            } else $this->sendMainDuelUI($sender);
        } else $sender->sendMessage("§cYou can only run this command in-game");
    }

    public function sendMainDuelUI(Player $p){
        $form = new SimpleForm(function (Player $player, $data) {
           if($data === 0){
               $form = new CustomForm(function ($plyer, $data = null): void {
                   if ($data === null) {
                       return;
                   }
                   Server::getInstance()->dispatchCommand($plyer, "duel ".$data[1]);
               });
               $form->setTitle("§8§l(§9DUELS§8)§r§7 ");
               $form->addLabel("Duel a player");
               $form->addInput("Enter username", "username");
               $player->sendForm($form);
           }elseif($data === 1){
               $this->sendSpectatorUI($player);
           }
        });
        $ongoing = 0;
        foreach (Main::getDuelManager()->duels as $duel){
            if(in_array($duel->stage, [Constants::DUEL_INIT, Constants::DUEL_IN_PROGRESS])) {
               $ongoing++;
            }
        }
        $form->setContent("Select an option");
        $form->setTitle("§8§l(§bDUELS§8)");
        $form->addButton("§0Duel a Player");
        $form->addButton("§0Spectate ongoing Duels §8[§4{$ongoing}§8]");
        $p->sendForm($form);
    }


    public function sendSpectatorUI(Player $p)
    {
        $buttons = [];
        foreach (Main::getDuelManager()->duels as $duel){
            if(in_array($duel->stage, [Constants::DUEL_INIT, Constants::DUEL_IN_PROGRESS])) {
                $buttons[] = ["§8" . $duel->challenger->getName() . "§0 vs §8" . $duel->defender->getName() . "\n§4" . $duel->kit->getName(), $duel];
            }
        }
        $form = new SimpleForm(function (Player $player, $data) use ($buttons, $p) {
            if ($data !== null) {
                $duel = $buttons[$data][1] ?? null;
                if($duel !== null){
                    $duel->addSpectator($p);
                }
            }
        });
        $form->setContent(count(Main::getDuelManager()->duels) > 0 ? "Current Ongoing Duels" : "\n\nThere are no current ongoing duels\n");
        $form->setTitle(Main::form("duelmenu.title"));
        if(empty($buttons)){
            $form->addButton("Ok");
        }
        foreach ($buttons as $data) {
            $form->addButton($data[0]);
        }
        $p->sendForm($form);
    }

    public function sendDuelUI(Player $p, Player $target)
    {
        $buttons = [];
        $kitnames = Main::getDuelKitManager()->getKitNames();
        $cfgbuttons = Main::getInstance()->getConfig()->get("duelmenu-buttons");
        foreach ($kitnames as $kitname) {
            if (isset($cfgbuttons[$kitname])) {
                $buttons[] = [
                    "kitname" => $kitname,
                    "button" => $cfgbuttons[$kitname]["button"],
                    "url" => $cfgbuttons[$kitname]["url"] ?? ""
                ];
            }
        }

        $form = new SimpleForm(function (Player $player, $data) use ($buttons, $target) {
            if ($data === null) {
            } else {
                if (isset($buttons[$data])) {
                    $this->cooldown[$player->getName()] = time();
                    Main::getInstance()->getDuelManager()->duelRequest(Main::getDuelKitManager()->getKit($buttons[$data]["kitname"]), $player, $target);
                } else $player->sendMessage(Main::prefix . "Error, invalid option");
            }
        });

        $form->setContent(Main::form("duelmenu.content"));
        $form->setTitle(Main::form("duelmenu.title"));
        foreach ($buttons as $data) {
            if (strlen($data["url"]) > 1) {
                $url = strpos($data["url"], "http") !== false;
                $form->addButton($data["button"], $url ? SimpleForm::IMAGE_TYPE_URL : SimpleForm::IMAGE_TYPE_PATH, $data["url"]);
            } else $form->addButton($data["button"]);
        }
        $p->sendForm($form);
    }


}