<?php

namespace HealthTag;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\player\Player;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;

use pocketmine\scheduler\ClosureTask;

use _64FF00\PureChat\PureChat;

class Main extends PluginBase implements Listener{

    private PureChat $purechat;
    private array $hidden = [];

    public function onEnable(): void{

        $this->saveDefaultConfig();

        $pc = $this->getServer()->getPluginManager()->getPlugin("PureChat");

        if(!$pc instanceof PureChat){
            $this->getLogger()->error("PureChat not found! Disabling plugin.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->purechat = $pc;

        $this->getServer()->getPluginManager()->registerEvents($this,$this);
    }

    public function onJoin(PlayerJoinEvent $event): void{
        $this->updateTag($event->getPlayer());
    }

    public function onSneak(PlayerToggleSneakEvent $event): void{

        $player = $event->getPlayer();

        if($event->isSneaking()){
            $this->hidden[$player->getName()] = true;
            $player->setNameTag("");
        }else{
            unset($this->hidden[$player->getName()]);
            $this->updateTag($player);
        }
    }

    public function onDamage(EntityDamageEvent $event): void{

        $entity = $event->getEntity();

        if($entity instanceof Player){

            $this->getScheduler()->scheduleDelayedTask(
                new ClosureTask(function() use ($entity){
                    if($entity->isOnline()){
                        $this->updateTag($entity);
                    }
                }),1
            );
        }
    }

    public function onHeal(EntityRegainHealthEvent $event): void{

        $entity = $event->getEntity();

        if($entity instanceof Player){

            $this->getScheduler()->scheduleDelayedTask(
                new ClosureTask(function() use ($entity){
                    if($entity->isOnline()){
                        $this->updateTag($entity);
                    }
                }),1
            );
        }
    }

    private function updateTag(Player $player): void{

        if(isset($this->hidden[$player->getName()])){
            return;
        }

        $format = $this->getConfig()->get("format");

        # PureChat colored rank
        $rank = $this->purechat->getNametag($player);

        $hearts = round($player->getHealth() / 2);

        $bar = $this->createHealthBar($player);

        $tag = str_replace(
            ["{rank}", "{player}", "{hearts}", "{healthbar}"],
            [$rank, $player->getName(), $hearts, $bar],
            $format
        );

        $player->setNameTag($tag);
        $player->setNameTagAlwaysVisible(true);
    }

    private function createHealthBar(Player $player): string{

        if(!$this->getConfig()->get("health-bar.enabled")){
            return "";
        }

        $bars = $this->getConfig()->get("health-bar.bars");
        $symbol = $this->getConfig()->get("health-bar.symbol");

        $percent = $player->getHealth() / $player->getMaxHealth();

        $filled = (int) round($percent * $bars);

        $color = $this->getColor($percent);

        $empty = $this->getConfig()->get("colors.empty");

        return str_repeat($color.$symbol,$filled) .
               str_repeat($empty.$symbol,$bars-$filled);
    }

    private function getColor(float $percent): string{

        $colors = $this->getConfig()->get("colors");

        if($percent >= 0.75){
            return $colors["high"];
        }

        if($percent >= 0.35){
            return $colors["medium"];
        }

        return $colors["low"];
    }
}
