<?php

declare(strict_types=1);

namespace Phoenix\UltimateAntiLag;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\scheduler\ClosureTask;
use pocketmine\entity\object\ItemEntity;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener {
    
    private Config $config;
    private array $itemTimers = [];
    
    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        
        $this->getLogger()->info(TextFormat::GREEN . "UltimateAntiLag activado! Items se eliminaran despues de " . $this->config->get("item-lifetime", 60) . " segundos");
        
        // Tarea para limpiar items periódicamente
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            $this->cleanupExpiredItems();
        }), 20); // Cada segundo
    }
    
    public function onDisable(): void {
        $this->getLogger()->info(TextFormat::RED . "UltimateAntiLag desactivado!");
    }
    
    /**
     * Evento cuando un item es dropeado al suelo
     */
    public function onItemSpawn(ItemSpawnEvent $event): void {
        $item = $event->getEntity();
        $itemId = spl_object_id($item);
        
        // Registrar el tiempo cuando el item fue dropeado
        $this->itemTimers[$itemId] = [
            'entity' => $item,
            'spawn_time' => time(),
            'lifetime' => $this->config->get("item-lifetime", 60)
        ];
    }
    
    /**
     * Limpia los items que han expirado
     */
    private function cleanupExpiredItems(): void {
        $currentTime = time();
        $removedCount = 0;
        
        foreach ($this->itemTimers as $itemId => $data) {
            $item = $data['entity'];
            $spawnTime = $data['spawn_time'];
            $lifetime = $data['lifetime'];
            
            // Verificar si el item aún existe y no ha sido cerrado
            if ($item->isClosed() || !$item->isAlive()) {
                unset($this->itemTimers[$itemId]);
                continue;
            }
            
            // Verificar si ha pasado el tiempo de vida
            if (($currentTime - $spawnTime) >= $lifetime) {
                $item->flagForDespawn();
                unset($this->itemTimers[$itemId]);
                $removedCount++;
            }
        }
        
        // Log cada 5 minutos si se han eliminado items
        if ($removedCount > 0 && ($currentTime % 300) === 0) {
            $this->getLogger()->info(TextFormat::YELLOW . "Eliminados $removedCount items expirados");
        }
    }
    
    /**
     * Comando para limpiar manualmente todos los items
     */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "clearlag") {
            if (!$sender->hasPermission("ultimateantilag.clearlag")) {
                $sender->sendMessage(TextFormat::RED . "No tienes permisos para usar este comando!");
                return true;
            }
            
            $removedCount = 0;
            
            // Limpiar todos los items del suelo inmediatamente
            foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
                foreach ($world->getEntities() as $entity) {
                    if ($entity instanceof ItemEntity) {
                        $entity->flagForDespawn();
                        $removedCount++;
                    }
                }
            }
            
            // Limpiar el array de timers
            $this->itemTimers = [];
            
            $sender->sendMessage(TextFormat::GREEN . "¡ClearLag ejecutado! Eliminados $removedCount items del suelo.");
            
            // Broadcast a todos los jugadores
            if ($this->config->get("broadcast-clearlag", true)) {
                $this->getServer()->broadcastMessage(TextFormat::YELLOW . "¡ClearLag ejecutado! Eliminados $removedCount items del suelo.");
            }
            
            return true;
        }
        
        if ($command->getName() === "antilagstats") {
            if (!$sender->hasPermission("ultimateantilag.stats")) {
                $sender->sendMessage(TextFormat::RED . "No tienes permisos para usar este comando!");
                return true;
            }
            
            $activeItems = count($this->itemTimers);
            $lifetime = $this->config->get("item-lifetime", 60);
            
            $sender->sendMessage(TextFormat::AQUA . "=== UltimateAntiLag Stats ===");
            $sender->sendMessage(TextFormat::WHITE . "Items activos siendo monitoreados: " . TextFormat::YELLOW . $activeItems);
            $sender->sendMessage(TextFormat::WHITE . "Tiempo de vida configurado: " . TextFormat::YELLOW . $lifetime . " segundos");
            $sender->sendMessage(TextFormat::WHITE . "Plugin creado por: " . TextFormat::GREEN . "Phoenix4041");
            
            return true;
        }
        
        if ($command->getName() === "antilagreload") {
            if (!$sender->hasPermission("ultimateantilag.reload")) {
                $sender->sendMessage(TextFormat::RED . "No tienes permisos para usar este comando!");
                return true;
            }
            
            $this->reloadConfig();
            $this->config = $this->getConfig();
            $sender->sendMessage(TextFormat::GREEN . "¡Configuración de UltimateAntiLag recargada!");
            
            return true;
        }
        
        return false;
    }
}