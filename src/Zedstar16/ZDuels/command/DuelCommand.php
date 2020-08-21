<?php


namespace Zedstar16\ZDuels\command;


use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;
use Zedstar16\ZDuels\libs\FormAPI\SimpleForm;
use Zedstar16\ZDuels\Main;

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
                } else {
                    $pn = $sender->getName();
                    $ct = floatval(Main::getInstance()->getConfig()->get("duel-request-cooldown"));
                    if((!isset($this->cooldown[$pn])) || (($this->cooldown[$pn] + $ct - time() <= 0))) {
                        $p = $server->getPlayer($args[0]);
                        if ($p !== null) {
                            $this->sendDuelUI($sender, $p);
                        } else $sender->sendMessage(Main::msg("target-not-online", "target", $args[0]));
                    }else $sender->sendMessage(Main::msg("duel-cooldown", "cooldown", ($this->cooldown[$pn] + $ct - time())));
                }
            }else $sender->sendMessage(Main::msg("duel-help"));
        } else $sender->sendMessage("§cYou can only run this command in-game");
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

        $form = new SimpleForm(function (Player $player, $data) use($buttons, $target) {
            if ($data === null) {
            } else {
                if (isset($buttons[$data])) {
                    $this->cooldown[$player->getName()] = time();
                    Main::getInstance()->getDuelManager()->duelRequest(Main::getDuelKitManager()->getKit($buttons[$data]["kitname"]), $player, $target);
                } else $player->sendMessage("§cError, invalid option");
            }
        });
        $form->setContent(Main::form("duelmenu.content"));
        $form->setTitle(Main::form("duelmenu.title"));
        foreach ($buttons as $data) {
            if (strlen($data["url"]) > 1) {
                $form->addButton($data["button"], SimpleForm::IMAGE_TYPE_URL, $data["url"]);
            } else $form->addButton($data["button"]);
        }
        $p->sendForm($form);
    }


}