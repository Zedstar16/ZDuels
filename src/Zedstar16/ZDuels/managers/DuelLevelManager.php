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

use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\utils\BinaryStream;
use Zedstar16\ZDuels\duel\DuelKit;
use Zedstar16\ZDuels\duel\DuelLevel;
use Zedstar16\ZDuels\Main;

class DuelLevelManager
{
    /** @var DuelLevel[] */
    private $duellevels = [];
    /** @var array */
    private $config;

    public function __construct()
    {
        $this->config = Main::getInstance()->getConfig()->getAll();
        $this->loadAllDuelLevels();
        Main::getInstance()->getLogger()->notice(count($this->duellevels)." Duel Levels loaded successfully");
    }

    public function loadAllDuelLevels()
    {
        $this->duellevels = [];
        foreach (Main::getInstance()->getJson("levels.json") ?? [] as $levelname => $data) {
            Main::getInstance()->getServer()->loadLevel($levelname);
            $this->duellevels[] = new DuelLevel($levelname);
        }
        foreach ($this->config["specific-levels"] ?? [] as $levelname => $kits) {
            foreach ($kits as $kitname) {
                $this->duellevels[] = new DuelLevel($levelname, true, $kitname);
            }
        }
    }

    public function isDuelLevel(Level $level){
        return $this->getDuelLevelByName($level->getName()) !== null;
    }

    public function setDuelLevelSpawn(Player $p, Int $int){
        $leveldata = Main::getInstance()->getJson("levels.json");
        $leveldata[$p->getLevel()->getName()][$int] = [
            "x" => $p->getX(),
            "y" => $p->getY(),
            "z" => $p->getZ(),
            "yaw" => $p->getYaw(),
            "pitch" => $p->getPitch()
        ];
        Main::getInstance()->saveJson("levels.json", $leveldata);
    }

    /**
     * @return DuelLevel[]
     */
    public function getLevelsInUse() : array{
        return array_filter($this->duellevels, function(DuelLevel $level){
            return $level->inUse();
        });
    }

    public function freeLevelExists(DuelKit $kit): bool
    {
        return !empty($this->getAvailableLevels($kit));
    }

    public function getDuelLevelByName($levelname): ?DuelLevel
    {
        foreach ($this->duellevels as $duelLevel) {
            if ($duelLevel->getName() == $levelname) {
                return $duelLevel;
            }
        }
        return null;
    }

    /**
     * @return DuelLevel[]
     */
    private function getUsableLevels(DuelKit $kit): array
    {
        $levels = [];
        if (!$kit->isSpecific()) {
            return $this->duellevels;
        }
        foreach ($this->duellevels as $duelLevel) {
            if ($duelLevel->isSpecific() && $duelLevel->getKitName() == $kit) {
                $levels[] = $duelLevel;
            }
        }
        return $levels;
    }

    /**
     * @return DuelLevel[]
     */
    private function getAvailableLevels(DuelKit $kit): array
    {
        $levels = [];
        foreach ($this->getUsableLevels($kit) as $duelLevel) {
            if (!$duelLevel->inUse()) {
                $levels[] = $duelLevel;
            }
        }
        return $levels;
    }

    public function getNewDuelLevel(DuelKit $kit): DuelLevel
    {
        $levels = $this->getAvailableLevels($kit);
        $level = $levels[mt_rand(0, count($levels) - 1)];
        $level->setInUse(true);
        return $level;
    }


}