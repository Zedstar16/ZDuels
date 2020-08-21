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


use pocketmine\entity\object\ExperienceOrb;
use pocketmine\entity\object\ItemEntity;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\Server;
use Zedstar16\ZDuels\Main;

class DuelLevel
{
    /** @var bool */
    private $inuse, $specific = false;
    /** @var */
    private $kitname;
    /** @var array */
    private $kitcfg = [];
    /** @var Level */
    private $level;


    public function __construct($levelname, $specific = false, $kitname = null)
    {
        $this->kitcfg = Main::getKits();
        $this->level = Server::getInstance()->getLevelByName($levelname);
        $this->specific = $specific;
        $this->kitname = $kitname;
    }

    public function inUse()
    {
        return $this->inuse;
    }

    public function setInUse(bool $bool)
    {
        $this->clearEntities();
        $this->inuse = $bool;
    }

    private function clearEntities()
    {
        foreach ($this->level->getEntities() as $entity) {
            if ($entity instanceof ItemEntity) {
                $entity->flagForDespawn();
            } elseif ($entity instanceof ExperienceOrb) {
                $entity->flagForDespawn();

            }
        }
    }

    public function getName()
    {
        return $this->level->getName();
    }

    public function isSpecific()
    {
        return $this->specific;
    }

    public function getKitName()
    {
        return $this->kitname;
    }

    public function getLevel(): Level
    {
        return $this->level;
    }

    public function getSpawnLocation(DuelKit $kit, Int $int): Location
    {
        $data = Main::getInstance()->getJson("levels.json");
        $d = $data[$this->level->getName()][$int];
        return new Location($d["x"], $d["y"], $d["z"], $d["yaw"], $d["pitch"], $this->level);
    }

}