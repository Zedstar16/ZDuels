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

use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;
use Zedstar16\HorizonCore\HorizonPlayer;
use de\Fireworks;
use Zedstar16\ZDuels\constant\Constants;
use Zedstar16\ZDuels\Main;

class Duel
{

    /** @var Player */
    public $challenger, $defender;
    /** @var DuelLevel */
    public $level;
    /** @var DuelKit */
    public $kit;
    /** @var Int */
    public $baseTimer, $timeRemaining, $countdownTimeRemaining, $endTimeRemaining, $winner_health, $stage;
    /** @var array */
    public $match_inventories, $stats, $competitors, $results = [];

    public function __construct(DuelKit $kit, Player $challenger, Player $defender)
    {
        $this->kit = $kit;
        $this->challenger = $challenger;
        $this->defender = $defender;
        $this->stats[$challenger->getName()] = array_fill_keys(["clicks", "hits", "damage_taken", "damage_dealt"], 0);
        $this->stats[$defender->getName()] = array_fill_keys(["clicks", "hits", "damage_taken", "damage_dealt"], 0);
        $this->level = Main::getInstance()->getDuelLevelManager()->getNewDuelLevel($kit);
        $this->timeRemaining = Main::getInstance()->getConfig()->get("duel-time");
        $this->countdownTimeRemaining = Main::getInstance()->getConfig()->get("duel-countdown-time");
        $this->endTimeRemaining = 5;
        $this->baseTimer = $this->timeRemaining + $this->countdownTimeRemaining + $this->endTimeRemaining;
        $this->init();
    }

    public function init()
    {
        $this->stage = Constants::DUEL_INIT;
        if (!Main::getInstance()->hasSavedInventory($this->challenger)) {
            Main::getInstance()->saveInventory($this->challenger);
        }
        if (!Main::getInstance()->hasSavedInventory($this->defender)) {
            Main::getInstance()->saveInventory($this->defender);
        }
        $this->challenger->setGamemode(2);
        $this->defender->setGamemode(2);
        $this->challenger->setImmobile(true);
        $this->defender->setImmobile(true);
        $this->challenger->teleport($this->level->getSpawnLocation($this->kit, 0));
        $this->defender->teleport($this->level->getSpawnLocation($this->kit, 1));
    }

    public function start()
    {
        $this->stage = Constants::DUEL_IN_PROGRESS;
        $this->challenger->sendTitle(Main::msg("title-duel-start"));
        $this->defender->sendTitle(Main::msg("title-duel-start"));
        $this->challenger->setImmobile(false);
        $this->defender->setImmobile(false);
        $this->kit->set($this->challenger);
        $this->kit->set($this->defender);
    }

    public function displayPopup()
    {
        foreach ([$this->challenger, $this->defender] as $p) {
            $opponent_cps = Main::getCPS($this->getOpponent($p));
            $cps = Main::getCPS($p);
            $timeRemaining = gmdate("i:s", $this->timeRemaining);
            $string = Main::msg("duel-popup", ["playercps", "opponentcps", "time_remaining"], [$cps, $opponent_cps, $timeRemaining]);
            /** @var Player $p */
            $p->sendTip($string);
        }
    }

    public function update()
    {
        $this->baseTimer--;
        if (count($this->level->getLevel()->getPlayers()) == 1 && $this->stage == Constants::DUEL_IN_PROGRESS) {
            foreach ($this->level->getLevel()->getPlayers() as $p) {
                if ($this->getOpponent($p) !== null) {
                    $this->finish($p, $this->getOpponent($p));
                } else $this->terminate("The other participant left the game");
            }
        }
        if ($this->stage == Constants::DUEL_INIT) {
            if ($this->countdownTimeRemaining == 0) {
                $this->start();
                return;
            }
            if ($this->countdownTimeRemaining > 5) {
                $title = Main::msg("title-duel-countdown", "time", gmdate("i:s", $this->countdownTimeRemaining));
            } else  $title = Main::msg("title-duel-countdown-critical", "time", $this->countdownTimeRemaining);
            $this->challenger->sendTitle($title, "", 0, 15, 5);
            $this->defender->sendTitle($title, "", 0, 15, 5);
            $this->countdownTimeRemaining--;
        }
        if ($this->stage == Constants::DUEL_IN_PROGRESS) {
            $this->timeRemaining--;
            $this->displayPopup();
            $this->challenger->setFood(20);
            $this->defender->setFood(20);
        }
        if ($this->timeRemaining == 0 && $this->stage == Constants::DUEL_IN_PROGRESS) {
            $this->draw();
        }
        if ($this->stage == Constants::DUEL_FINISHED) {
            if ($this->endTimeRemaining == 0) {
                $this->challenger->sendSubTitle("§aTeleporting");
                $this->end($this->challenger);
                $this->end($this->defender);
            } else {
                $this->challenger->sendSubTitle(Main::msg("subtitle-duel-end-countdown", "time_remaining", $this->endTimeRemaining));
                $this->endTimeRemaining--;
            }
        }
    }

    public function finish(Player $winner, Player $loser)
    {
        $this->stage = Constants::DUEL_FINISHED;
        $this->results[$winner->getName()] = Constants::DUEL_WIN;
        $this->results[$loser->getName()] = Constants::DUEL_LOST;
        Main::getInstance()->scoreStats($winner->getName(), "wins");
        Main::getInstance()->scoreStats($loser->getName(), "losses");
        $winner->sendTitle(Main::msg("title-duel-result-won"));
        $loser->sendTitle(Main::msg("title-duel-result-lost"));
        $loser->setGamemode(3);
        $loser->teleport(new Position($winner->x, $winner->y + 7, $winner->z, $winner->getLevel()));
        $this->winner_health = (int)$winner->getHealth();
        $msg = "§f{$winner->getName()}§c[$this->winner_health]§7 won a §f{$this->kit->getName()} §7duel against §f{$loser->getName()}";
        if ($this->kit->getName() === "NoDebuff") {
            $winner_pots = abs($this->getPotCount($winner->getInventory()->getContents())-33);
            $msg = "§f{$winner->getName()}§c[$this->winner_health]§7 §6$winner_pots potted §f{$loser->getName()} §7in a §fNoDebuff §7Duel";
        }
        Server::getInstance()->broadcastMessage(Main::prefix . $msg);
    }

    public function getPotCount(array $items)
    {
        return count(array_filter($items, function ($item) {
            if(!$item instanceof Item) {
                $item = Main::getInstance()->jsonDeserialize($item);
            }
            return ($item->getId() === Item::SPLASH_POTION) && $item->getDamage() === 22;
        }));
    }

    public function draw()
    {
        $this->results = array_fill_keys([$this->challenger->getName(), $this->defender->getName()], Constants::DUEL_DRAW);
        $this->stage = Constants::DUEL_FINISHED;
        $title = Main::msg("title-duel-result-draw");
        $this->challenger->sendTitle($title);
        $this->defender->sendTitle($title);
        Server::getInstance()->broadcastMessage(Main::prefix . "§f{$this->challenger->getName()}§7 drew in a §f{$this->kit->getName()}§7 duel against §f{$this->defender->getName()}");
    }

    public function saveMatchInventory($name, $inventory = [], $armorinventory = [])
    {
        foreach ($inventory as $slot => $item) {
            $this->match_inventories[$name]["inventory"][$slot] = $item->jsonSerialize();
        }
        foreach ($armorinventory as $slot => $item) {
            $this->match_inventories[$name]["armorinventory"][$slot] = $item->jsonSerialize();
        }
    }

    public function end(Player $player)
    {
        try {
            $s = Server::getInstance();
            if ($this->results[$player->getName()] == Constants::DUEL_WIN or $this->results[$player->getName()] == Constants::DUEL_DRAW) {
                $this->saveMatchInventory($player->getName(), $player->getInventory()->getContents(), $player->getArmorInventory()->getContents());
            }
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->setGamemode(0);
            $player->setHealth(20);
            $player->removeAllEffects();
            $player->teleport($s->getDefaultLevel()->getSpawnLocation());
            Main::getInstance()->sendGameEndUI($player, $this);
            Main::getInstance()->loadSavedInventory($player);
        } catch (\Throwable $error) {
            Server::getInstance()->getLogger()->error($error->getMessage() . " at Line: " . $error->getLine());
        }
        $this->level->setInUse(false);
        $this->stage = Constants::DUEL_END;
    }

    // If a duel has to end unexpectedly or prematurely
    public function terminate(string $reason)
    {
        if ($this->challenger !== null) {
            $this->handleTerminatedDuelPlayer($this->challenger, $reason);
        }
        if ($this->defender !== null) {
            $this->handleTerminatedDuelPlayer($this->defender, $reason);
        }
        $this->level->setInUse(false);
        Main::getDuelManager()->terminateDuel($this);
    }

    public function handleTerminatedDuelPlayer(Player $player, string $reason)
    {
        $spawn = Server::getInstance()->getDefaultLevel()->getSafeSpawn();
        $player->teleport($spawn);
        $player->sendMessage(Main::msg("duel-unexpected-terminate", ["reason"], [$reason]));
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->setGamemode(0);
        $player->setImmobile(false);
        $player->teleport($spawn);
        Main::getInstance()->loadSavedInventory($player);
    }

    public function sendFireworks(Player $p)
    {
        try {
            for ($i = 0; $i < 10; $i++) {
                $firework = new Fireworks();
                $color = [Fireworks::COLOR_RED, Fireworks::COLOR_YELLOW, Fireworks::COLOR_GREEN, Fireworks::COLOR_LIGHT_AQUA, Fireworks::COLOR_BLUE, Fireworks::COLOR_PINK, Fireworks::COLOR_DARK_PINK];
                $firework->addExplosion(Fireworks::TYPE_HUGE_SPHERE, $color[mt_rand(0, 6)]);
                $firework->setFlightDuration(1);
                $pos = new Vector3($p->x + mt_rand(-4, 4), $p->y, $p->z + mt_rand(-4, 4));
                $level = $p->getLevel();
                $nbt = Entity::createBaseNBT($pos, new Vector3(0.001, 0.05, 0.001), lcg_value() * 360, 90);
                $entity = Entity::createEntity("FireworksRocket", $level, $nbt, $firework);
                $entity->spawnToAll();
            }
        } catch (\Throwable $err) {
            Server::getInstance()->getLogger()->logException($err);
        }
    }

    public function getOpponent(Player $player)
    {
        return $this->challenger === $player ? $this->defender : $this->challenger;
    }


}