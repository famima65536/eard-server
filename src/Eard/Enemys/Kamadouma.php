<?php

namespace Eard\Enemys;

use Eard\Main;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\block\Block;

use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;

use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\level\Location;
use pocketmine\level\Explosion;
use pocketmine\level\MovingObjectPosition;
use pocketmine\level\format\FullChunk;
use pocketmine\level\particle\DestroyBlockParticle;
use pocketmine\level\particle\TerrainParticle;
use Eard\World\Generator\Biome\Biome;
use pocketmine\level\sound\AnvilFallSound;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\ByteTag;

use pocketmine\entity\Effect;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Item;

use pocketmine\math\Vector3;
class Kamadouma extends Humanoid implements Enemy{
	public static $ground = false;
	public $rainDamage = false;

	//名前を取得
	public static function getEnemyName(){
		return "カマドウマ";
	}

	//エネミー識別番号を取得
	public static function getEnemyType(){
		return EnemyRegister::TYPE_KAMADOUMA;
	}

	//最大HPを取得
	public static function getHP(){
		return 25;
	}

	//召喚時のポータルのサイズを取得
	public static function getSize(){
		return 0.8;
	}

	//召喚時ポータルアニメーションタイプを取得
	public static function getAnimationType(){
		return EnemySpawn::TYPE_COMMON;
	}

	//召喚時のポータルアニメーションの中心座標を取得
	public static function getCentralPosition(){
		return new Vector3(0, 0, 0);
	}

	public static function getBiomes() : array{
		return [
			//雨なし
			//Biome::HELL => true, 
			//Biome::END => true,
			Biome::DESERT => true,
			//Biome::DESERT_HILLS => true,
			//Biome::MESA => true,
			//Biome::MESA_PLATEAU_F => true,
			//Biome::MESA_PLATEAU => true,
			//雨あり
			//Biome::OCEAN => true,
			Biome::PLAINS => true,
			//Biome::MOUNTAINS => true,
			//Biome::FOREST => true,
			//Biome::TAIGA => true,
			//Biome::SWAMP => true,
			//Biome::RIVER => true,
			Biome::ICE_PLAINS => true,
			//Biome::SMALL_MOUNTAINS => true,
			//Biome::BIRCH_FOREST => true,
		];
	}

	public static function getSpawnRate() : int{
		return 20;
	}

	//ドロップするアイテムIDの配列を取得 [[ID, data, amount, percent], [ID, data, amount, percent], ...]
	public static function getAllDrops(){
		//火薬 骨粉 種(スイカ/かぼちゃ/麦になるやつ)
		//5%の確率で鉄インゴットも
/*
		return [
			[Item::GUNPOWDER, 0, 1, 70],
			[Item::DYE, 15, 1, 40],//骨粉
			[Item::PUMPKIN_SEEDS, 0, 1, 20],
			[Item::MELON_SEEDS, 0, 1, 20],
			[Item::WHEAT_SEEDS, 0, 1, 20],
			[Item::IRON_INGOT , 0, 1, 5],
		];
		*/
		return [
			/*
			[テーブルのドロップ率, ドロップ判定回数,
				[
					[アイテムID, データ値, 個数],
					[...]
				],
			],
			*/
			[100, 1,
				[
					[Item::RABBIT_FOOT, 0, 2],
				]
			],
			[100, 1,
				[
					[Item::IRON_NUGGET, 0, 1],
				],
			],
			[100, 1,
				[
					[Item::GLOWSTONE_DUST, 0, 1],
				],
			],
			[60, 2,
				[
					[Item::GLOWSTONE_DUST, 0, 1],
					[Item::IRON_NUGGET, 0, 3],
					[Item::RABBIT_FOOT, 0, 2],
				],
			],
			[40, 1,
				[
					[Item::GLOWSTONE_DUST, 0, 3],
					[Item::GOLD_NUGGET, 0, 2],
				],
			],
			[5, 1,
				[
					[Item::GOLD_INGOT, 0, 1],
				],
			],
			[2, 1,
				[
					[Item::EMERALD , 0, 1],
				],
			],
		];
	}

	public static function getMVPTable(){
		return [100, 1, 
			[
				[Item::IRON_INGOT, 0, 1],
				[Item::GLOWSTONE_DUST, 0, 2],
				[Item::GOLD_NUGGET, 0, 2],
				[Item::RABBIT_FOOT, 0, 2],
				[Item::RABBIT_FOOT, 0, 1],
			]
		];

	}

	public static function summon($level, $x, $y, $z){
		$nbt = new CompoundTag("", [
			"Pos" => new ListTag("Pos", [
				new DoubleTag("", $x),
				new DoubleTag("", $y-1),
				new DoubleTag("", $z)
			]),
			"Motion" => new ListTag("Motion", [
				new DoubleTag("", 0),
				new DoubleTag("", 0),
				new DoubleTag("", 0)
			]),
			"Rotation" => new ListTag("Rotation", [
				new FloatTag("", lcg_value() * 360),
				new FloatTag("", 0)
			]),
			"Skin" => new CompoundTag("Skin", [
				new StringTag("geometryData", EnemyRegister::loadModelData('battamodel')),
				new StringTag("geometryName", 'geometry.hopper02'),
				new StringTag("capeData", ''),
				new StringTag("Data", EnemyRegister::loadSkinData('Kamadouma')),
				new StringTag("Name", 'kamadouma')
			]),
		]);
		$custom_name = self::getEnemyName();
		if(!is_null($custom_name)){
			$nbt->CustomName = new StringTag("CustomName", $custom_name);
		}
		$entity = new Kamadouma($level, $nbt);
		$random_hp = 1+(mt_rand(-10, 10)/100);
		$entity->setMaxHealth(round(self::getHP()+$random_hp));
		$entity->setHealth(round(self::getHP()+$random_hp));
		AI::setSize($entity, self::getSize());
		if($entity instanceof Entity){
			$entity->spawnToAll();
			return $entity;
		}
		echo $custom_name." is Not Entity\n";
		return false;
	}

	public function __construct(Level $level, CompoundTag $nbt){
		parent::__construct($level, $nbt);
		$this->cooltime = 0;
		$this->target = false;
		$this->charge = 0;
		$this->mode = 0;
		$this->check = 0;
		//$this->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_GLIDING, true);
		/*$item = Item::get(267);
		$this->getInventory()->setItemInHand($item);*/
	}

	public function onUpdate(int $tick): bool{
		if($this->getHealth() > 0 && AI::getRate($this)){
			if(!$this->target) $this->target = AI::searchTarget($this, 180);
			if($this->charge && $this->onGround){
				$this->yaw += mt_rand(-60, 60);
				if($this->target){
					AI::lookAt($this, $this->target);
				}
				AI::setRate($this, 40);
				AI::jump($this, 0.25, 0, AI::DEFAULT_JUMP*3.5);
				AI::rangeAttack($this, 3.5, 7);
				$this->getLevel()->addParticle(new DestroyBlockParticle($this, Block::get(4)));
				$this->charge = false;
			}else{
				$this->getLevel()->addParticle(new DestroyBlockParticle($this, Block::get(20)));
				AI::setRate($this, 20);
				$this->charge = true;
			}
		}else if($this->getHealth() > 0 && $this->charge && $this->target){
			if($this->mode){
				$this->mode = false;
			}else{
				$radius = 6;
				if($this->check%2 == 0){
					AI::rangeAttack($this, $radius, 2, null, function($a, $v){
						if($v->getHealth() < $v->getmaxHealth() && $v->getHealth() > 0){
							$v->heal(new EntityRegainHealthEvent($v, 1.0, EntityRegainHealthEvent::CAUSE_MAGIC));
						}
						return true;
					});
				}
				++$this->check;
				$this->level->addSound(new AnvilFallSound($this));
				$position = $this->getPosition();
				$block = Block::get(20);

				for ($yaw = 0; $yaw < 360; $yaw += M_PI*$radius*1.5) { 
					for ($pitch = 0; $pitch < 360; $pitch += M_PI*$radius*1.5) {
						$rad_y = deg2rad($yaw);
						$rad_p = deg2rad($pitch-180);
						$p = clone $position;
						$p->x += sin($rad_y)*cos($rad_p)*$radius;
						$p->y += sin($rad_p)*$radius;
						$p->z += -cos($rad_y)*$radius;
						$this->level->addParticle(new TerrainParticle($p, $block));
					}
				}
			}
		}
		//AI::walkFront($this, 0.08);
		return parent::onUpdate($tick);
	}

		public function attack(EntityDamageEvent $source): void{
		$damage = $source->getDamage();// 20170928 src変更による書き換え
		parent::attack($source);
		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			$this->target = $damager;
		}
	}

	public function getName() : string{
		return self::getEnemyName();
	}
}