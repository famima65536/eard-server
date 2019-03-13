<?php

namespace Eard\Enemys;

use pocketmine\Server;
use pocketmine\Player;
use pocketmine\level\Level;
use pocketmine\level\MovingObjectPosition;
use pocketmine\level\format\FullChunk;
use Eard\World\Generator\Biome\Biome;
use pocketmine\level\particle\SpellParticle;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;

use pocketmine\nbt\tag\CompoundTag;

use pocketmine\entity\Human;

use Eard\Utils\Chat;
use Eard\Utils\ItemName;
use Eard\MeuHandler\Account;
use Eard\Quests\Quest;

/**各エネミーに継承させるためのクラス
 */

class Humanoid extends Human{

	protected $gravity = 0.025;
	public $attackingTick = 0;
	public $rainDamage = true;//継承先でfalseにすると雨天時にダメージを受けない
	public $isDrown = true;//継承先でfalseにすると水没にダメージを受けない
	public $returnTime = 240;//出現から自動で消えるまでの時間(秒)
	public $spawnTime = 0;
	public static $ground = true;
	public static $noRainBiomes = [
		Biome::HELL => true, 
		Biome::END => true,
		Biome::DESERT => true,
		Biome::DESERT_HILLS => true,
		Biome::MESA => true,
		Biome::MESA_PLATEAU_F => true,
		Biome::MESA_PLATEAU => true,
	];
	public $score = [];
	/**
	 * 貫通できるブロックかを返す
	 *
	 * @param int $blockId
	 * @return bool
	 */
	public static function canThrough($blockId){
		switch($blockId){
			case 0:
			case 8:
			case 9:
			case 10:
			case 11:
			case 30:
			case 31:
			case 32:
			case 38:
			case 37:
			case 50:
			case 52:
			case 65:
			case 78:
			case 101:
			case 175:
			case 208:
				return true;
			break;
			default:
				return false;
			break;
		}
	}

	public static function spawnGround(){
		return static::$ground;
	}

	public function __construct(Level $level, CompoundTag $nbt){
		parent::__construct($level, $nbt);
		AI::setSize($this, static::getSize());
		$this->setNameTag("");
		$this->spawnTime = microtime(true);
	}

	public function getDrops($score = 0): array{
		if($score === 0){
			return [];
		}
		$drops = [];
		if($this->lastDamageCause instanceof EntityDamageByEntityEvent and $this->lastDamageCause->getDamager() instanceof Player){
			$all_drops = static::getAllDrops();
			$s = $this->score;
			rsort($s);
			$mvp = (isset($s[0])) ? $s[0] : 0;
			$mvp_2 = (isset($s[1])) ? $s[1] : 0;
			if($mvp === $score){
				$all_drops[] = static::getMVPTable();
				$all_drops[] = static::getMVPTable();
			}elseif($mvp_2 === $score){
				$all_drops[] = static::getMVPTable();
			}
			foreach($all_drops as $key => $value){
				//list($id, $data, $amount, $percent) = $value;
				list($percent, $count, $items) = $value;
				for($i = 0; $i < $count; $i++){
					if(mt_rand(1, 1000) <= $percent*10){
						shuffle($items);
						$item = $items[0];
						list($id, $data, $amount) = $item;
						$drops[] = Item::get($id, $data, $amount);
					}
				}
				/*
				if(mt_rand(0, 1000) < $percent*10){
					$drops[] = Item::get($id, $data, $amount);
				}
				*/
			}
		}
		return $drops;
	}

	//ちゃんと動いてもらうための補助関数(PMMP側から呼び出される)
	public function onUpdate(int $tick): bool{
		if($this instanceof Human){
			if($this->attackingTick > 0){
				$this->attackingTick--;
			}
			if(!$this->isAlive() and $this->hasSpawned){
				++$this->deadTicks;
				if($this->deadTicks >= 20){
					$this->despawnFromAll();
				}
				return true;
			}
			if($this->isAlive()){
				if($this->spawnTime + $this->returnTime < microtime(true)){
					#todo ここで消えるアニメーション
					$this->close();
					return true;
				}
				//$weather = $this->level->getWeather()->getWeather();
				$weather = 0;
				if((($this->rainDamage && $weather <= 2 && $weather >= 1 && !isset(self::$noRainBiomes[$this->level->getBiomeId(intval($this->x), intval($this->z))])) || (($id = $this->level->getBlock($this)->getId()) === 9 || $id === 8) && $this->isDrown) && $this->getHealth() > 0){
					$this->deadTicks = 0;
					$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 2));
				}

				$this->getMotion()->y -= $this->gravity;

				$this->move($this->getMotion()->x, $this->getMotion()->y, $this->getMotion()->z);

				$friction = 1 - $this->drag;

				if($this->onGround and (abs($this->getMotion()->x) > 0.00001 or abs($this->getMotion()->z) > 0.00001)){
					$friction = $this->getLevel()->getBlock($this->temporalVector->setComponents((int) floor($this->x), (int) floor($this->y - 1), (int) floor($this->z) - 1))->getFrictionFactor() * $friction;
				}

				$this->getMotion()->x *= $friction;
				$this->getMotion()->y *= 1 - $this->drag;
				$this->getMotion()->z *= $friction;

				if($this->onGround){
					$this->getMotion()->y *= -0.5;
				}

				/*if(!self::canThrough($this->getLevel()->getBlockIdAt($this->x, $this->y-1.65, $this->z))){
					$this->getMotion()->y = $this->gravity;
				}*/

				$this->updateMovement();
			}
		}
		parent::entityBaseTick();
		$grandParent = get_parent_class(get_parent_class($this));
		return $grandParent::onUpdate($tick);
	}

	public function attack(EntityDamageEvent $source): void{
		$damage = $source->getDamage();// 20170928 src変更による書き換え
		if($source->getCause() === EntityDamageEvent::CAUSE_FALL){
			$source->setCancelled(true);
		}
		if(!$source->isCancelled() && $source instanceof EntityDamageByEntityEvent){
			$source->setKnockBack($source->getKnockBack()/4);
			$attacker = $source->getDamager();
			if($attacker instanceof Player){
				$name = $attacker->getName();
				if(!isset($this->score[$name])){
					$this->score[$name] = 0;
				}
				$this->score[$name] += $damage;
			}
		}
		parent::attack($source);
	}

	public function kill(): void{
		$this->level->addParticle(new SpellParticle($this, 20, 220, 20));
		if($this->lastDamageCause instanceof EntityDamageByEntityEvent and $this->lastDamageCause->getDamager() instanceof Player){
			foreach ($this->score as $name => $score) {
				$player = Server::getInstance()->getPlayer($name);
				if($player === null){
					continue;
				}
				$inv = $player->getInventory();
				$player->sendMessage(Chat::SystemToPlayer($this->getEnemyName()."の討伐に成功しました"));
				$str = "";
				$first = true;
				foreach($this->getDrops($score) as $item){
					$inv->addItem($item);
					if(!$first){
						$str .= "、";
					}else{
						$first = false;
					}
					$str .= ItemName::getNameOf($item->getId(), $item->getDamage())."×".$item->getCount();
				}
				$player->sendMessage(Chat::SystemToPlayer("以下のアイテムを入手しました"));
				$player->sendMessage(Chat::SystemToPlayer($str));
				$account = Account::get($player);
				$nq = $account->getNowQuest();
				if($nq !== null && $nq->getQuestType() === Quest::TYPE_SUBJUGATION && $nq->getTarget() === static::getEnemyType()){
					$result = $nq->addAchievement();
					if($result){
						$player->sendMessage(Chat::SystemToPlayer("クエストクリア！"));
						//ここで報酬を送り付ける
						$nq->sendReward($player);
						if($account->addClearQuest($nq->getQuestId())){
							$player->sendMessage(Chat::SystemToPlayer("初クリア！"));
						}
						$account->resetQuest();
					}else{
						$player->sendMessage(Chat::SystemToPlayer("あと".($nq->getNormI()-$nq->getAchievement())."体です"));
					}
				}
			}
		}
		parent::kill();
	}
}