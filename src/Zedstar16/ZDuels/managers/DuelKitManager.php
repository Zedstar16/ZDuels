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
use Zedstar16\ZDuels\duel\DuelKit;
use Zedstar16\ZDuels\Main;

class DuelKitManager
{

    /** @var DuelKit[] */
    public $kits = [];

    public function __construct()
    {
        $this->loadKits();
        Main::getInstance()->getLogger()->notice(count($this->kits)." Duel Kits loaded successfully");
    }

    public function loadKits(){
        $this->kits = [];
        $cfg = Main::getKits();
        if(!empty($cfg)) {
            foreach ($cfg as $kitname => $data) {
                $this->kits[] = new DuelKit($kitname);
            }
        }
    }

    public function getKits() : array{
        $kits = [];
        foreach($this->kits as $kit){
            $kits[$kit->getName()] = $kit;
        }
        return $kits;
    }

    public function getKitNames() : array{
        $names = [];
        foreach($this->kits as $kit){
            $names[] = $kit->getName();
        }
        return $names;
    }

    public function getKit(String $kitname){
        return new DuelKit($kitname);
    }

    public function exists(String $kitname){
        return in_array($kitname, $this->getKitNames());
    }


    public function getSpecificKitnames(){
        $cfg = Main::getInstance()->getConfig()->get("specific-level");
        $kits = [];
        if($cfg !== null && !empty($cfg)) {
            foreach ($cfg->get("specific-level") as $levelname => $kits) {
                foreach ($kits as $kitname) {
                    $kits[] = $kitname;
                }
            }
        }
        return $kits;
    }



}