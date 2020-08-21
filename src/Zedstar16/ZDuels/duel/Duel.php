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

use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;
use Zedstar16\HorizonCore\HorizonPlayer;
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
        if ($this->challenger instanceof HorizonPlayer && $this->defender instanceof HorizonPlayer) {
            $this->challenger->getSession()->getScoreboard()->setScoreboard(new \Zedstar16\HorizonCore\components\HUD\Duel($this->challenger));
            $this->defender->getSession()->getScoreboard()->setScoreboard(new \Zedstar16\HorizonCore\components\HUD\Duel($this->defender));
        }
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
        Main::getInstance()->saveInventory($this->defender);
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
        $this->challenger->addTitle(Main::msg("title-duel-start"));
        $this->defender->addTitle(Main::msg("title-duel-start"));
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
            $p->sendPopup($string);
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
            $this->challenger->addTitle($title);
            $this->defender->addTitle($title);
            $this->countdownTimeRemaining--;
        }
        if ($this->stage == Constants::DUEL_IN_PROGRESS) {
            $this->timeRemaining--;
            $this->displayPopup();
            $this->displayPopup();
        }
        if ($this->timeRemaining == 0 && $this->stage == Constants::DUEL_IN_PROGRESS) {
            $this->draw();
        }
        if ($this->stage == Constants::DUEL_FINISHED) {
            if ($this->endTimeRemaining == 0) {
                $this->challenger->addSubTitle("§aTeleporting");
                $this->end($this->challenger);
                $this->end($this->defender);
            } else {
                $this->challenger->addSubTitle(Main::msg("subtitle-duel-end-countdown", "time_remaining", $this->endTimeRemaining));
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
        $winner->addTitle(Main::msg("title-duel-result-won"));
        $loser->addTitle(Main::msg("title-duel-result-lost"));
        $loser->setGamemode(3);
        $loser->teleport(new Position($winner->x, $winner->y + 7, $winner->z, $winner->getLevel()));
        $this->winner_health = (int)$winner->getHealth();
    }

    public function draw()
    {
        $this->results = array_fill_keys([$this->challenger->getName(), $this->defender->getName()], Constants::DUEL_DRAW);
        $this->stage = Constants::DUEL_FINISHED;
        $title = Main::msg("title-duel-result-draw");
        $this->challenger->addTitle($title);
        $this->defender->addTitle($title);
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
        $player->teleport($spawn);
        Main::getInstance()->loadSavedInventory($player);
    }

    public function getOpponent(Player $player)
    {
        return $this->challenger === $player ? $this->defender : $this->challenger;
    }


}