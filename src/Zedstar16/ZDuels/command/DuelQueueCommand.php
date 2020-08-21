<?php


namespace Zedstar16\ZDuels\command;


use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
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
            $this->openUI($sender);
        } else $sender->sendMessage("§cYou can only run this command in-game");
    }

    public function openUI(Player $player)
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
        $form = new SimpleForm(function (Player $player, $data) use ($buttons) {
            if ($data === null) {
                return;
            }
            if (isset($buttons[$data])) {
                $kit = Main::getDuelKitManager()->getKit($buttons[$data]["kitname"]);
                Main::getDuelQueue()->addToQueue($player, $kit);
            } else $player->sendMessage("§cError, invalid option");
        });
        $form->setContent("Select a Duel Gamemode");
        $form->setTitle("Duels Queue");
        $levels = Main::getDuelLevelManager()->getLevelsInUse();
        $playing = [];
        foreach ($levels as $level){
            $kit = $level->getKitName();
            if(isset($playing[$kit])){
                $playing[$kit] = 0;
            }
            $playing[$kit] += count($level->getLevel()->getPlayers());
        }
        $waiting = [];
        foreach(Main::getDuelQueue()->queue as $kit => $players){
            $waiting[$kit] = count($players);
        }
        foreach ($buttons as $data) {
            $kit = $data["kitname"];
            if (strlen($data["url"]) > 1) {
                $form->addButton($data["button"]."\n§r§8".($playing[$kit] ?? "0")." §7playing §f| §8$waiting[$kit] §7waiting", SimpleForm::IMAGE_TYPE_URL, $data["url"]);
            } else $form->addButton($data["button"]."\n§r§8".($playing[$kit] ?? "0")." §7playing §f| §8$waiting[$kit] §7waiting");
        }
        $player->sendForm($form);
    }

}