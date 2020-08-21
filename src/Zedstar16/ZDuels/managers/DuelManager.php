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


namespace Zedstar16\ZDuels\managers;


use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use Zedstar16\ZDuels\constant\Constants;
use Zedstar16\ZDuels\duel\Duel;
use Zedstar16\ZDuels\duel\DuelKit;
use Zedstar16\ZDuels\Main;

class DuelManager
{
    /** @var Duel[] */
    public $duels = [];

    public $requests = [];

    public function createDuel(DuelKit $kit, Player $challenger, Player $defender)
    {
        $this->duels[] = new Duel($kit, $challenger, $defender);
    }

    public static function isInDuel(Player $player)
    {
        $duel = Main::getInstance()->getDuelManager()->getDuel($player);
        return $duel !== null && $duel->stage !== Constants::DUEL_END;
    }

    public function getDuel(Player $player): ?Duel
    {
        foreach ($this->duels as $duel) {
            if (($duel->challenger === $player or $duel->defender === $player) && $duel->stage !== Constants::DUEL_END) {
                return $duel;
            }
        }
        return null;
    }

    public function terminateDuel(Duel $duel)
    {
        foreach ($this->duels as $key => $listedduel) {
            if ($listedduel === $duel) {
                unset($this->duels[$key]);
            }
        }
    }

    public function getDuels()
    {
        return $this->duels;
    }


    public function getDuelRequest(Player $player): ?array
    {
        $name = $player->getName();
        if (isset($this->requests[$name]) && Server::getInstance()->getPlayer($this->requests[$name]["challenger"]) !== null) {
            return $this->requests[$name];
        }
        return null;
    }

    public function duelRequest(DuelKit $kit, Player $challenger, Player $defender)
    {
        $this->requests[$defender->getName()] = ["kit" => $kit, "challenger" => $challenger->getName()];
        $challenger->sendMessage(Main::msg("challenged-player-to-duel", ["defender", "kit"], [$defender->getName(), $kit->getName()]));
        $defender->sendMessage(Main::msg("duel-request", ["challenger", "kit"], [$challenger->getName(), $kit->getName()]));
        Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function (Int $currentTick) use ($defender, $challenger) : void {
            if (isset($this->requests[$defender->getName()])) {
                unset($this->requests[$defender->getName()]);
                if ($challenger !== null) {
                    $challenger->sendMessage(Main::msg("duel-request-timeout", ["defender"], [$defender->getName()]));
                }
            }
        }), 20 * 30);
    }

    public function duelAccept(DuelKit $kit, Player $challenger, Player $defender)
    {
        if (Main::getDuelLevelManager()->freeLevelExists($kit)) {
            $challenger->sendMessage(Main::msg("duel-accepted", "defender", $defender->getName()));
            $this->createDuel($kit, $challenger, $defender);
            if (isset($this->requests[$defender->getName()])) {
                unset($this->requests[$defender->getName()]);
            }
        }else Main::msg("no-free-duel-levels");
    }

    public function getTotalDuelPlayers() : Int{
        $players = 0;
        $levels = Main::getDuelLevelManager()->getLevelsInUse();
        foreach ($levels as $level){
            $players += count($level->getLevel()->getPlayers());
        }
        foreach(Main::getDuelQueue()->queue as $kit => $players){
            $players =+ count($players);
        }
        return $players;
    }

}