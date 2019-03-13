<?php

namespace Eard\Enemys;

use pocketmine\block\Block;

use pocketmine\level\Level;
use pocketmine\level\MovingObjectPosition;
use pocketmine\level\format\FullChunk;
use pocketmine\level\particle\DestroyBlockParticle;
use Eard\World\Generator\Biome\Biome;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;

use pocketmine\entity\Entity;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;

use pocketmine\math\Vector3;
class Hopper extends Humanoid implements Enemy{

	//名前を取得
	public static function getEnemyName(){
		return "バッタ";
	}

	//エネミー識別番号を取得
	public static function getEnemyType(){
		return EnemyRegister::TYPE_HOPPER;
	}

	//最大HPを取得
	public static function getHP(){
		return 15;
	}

	//召喚時のポータルのサイズを取得
	public static function getSize(){
		return 1;
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
			//Biome::DESERT => true,
			//Biome::DESERT_HILLS => true,
			//Biome::MESA => true,
			//Biome::MESA_PLATEAU_F => true,
			//Biome::MESA_PLATEAU => true,
			//雨あり
			Biome::OCEAN => true,
			Biome::PLAINS => true,
			Biome::MOUNTAINS => true,
			Biome::FOREST => true,
			Biome::TAIGA => true,
			Biome::SWAMP => true,
			//Biome::RIVER => true,
			Biome::ICE_PLAINS => true,
			Biome::SMALL_MOUNTAINS => true,
			Biome::BIRCH_FOREST => true,
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
					[Item::APPLE, 0, 1],
				],
			],
			[100, 1,
				[
					[Item::DYE, 15, 1],//骨粉
				],
			],
			[60, 2,
				[
					[Item::POTATO, 0, 1],
					[Item::CARROT, 0, 1],
				],
			],
			[40, 1,
				[
					[Item::GUNPOWDER, 0, 1],
					[Item::DYE, 15, 1],//骨粉
					[Item::RABBIT_FOOT, 0, 1],
				],
			],
			[5, 1,
				[
					[Item::IRON_NUGGET, 0, 1],
				],
			],
			[1, 1,
				[
					[Item::EMERALD , 0, 1],
				],
			],
		];
	}

	public static function getMVPTable(){
		return [100, 1, 
			[
				[Item::APPLE, 0, 2],
				[Item::POTATO, 0, 2],
				[Item::CARROT, 0, 2]
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
				new StringTag("Data", EnemyRegister::loadSkinData('Batta')),
				new StringTag("Name", 'Batta')
			]),
		]);
		$custom_name = self::getEnemyName();
		if(!is_null($custom_name)){
			$nbt->setTag(new StringTag("CustomName", $custom_name));
		}
		$entity = new Hopper($level, $nbt);
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
		//$this->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_GLIDING, true);
		/*$item = Item::get(267);
		$this->getInventory()->setItemInHand($item);*/
	}

	public function onUpdate(int $tick): bool{
		if($this->getHealth() > 0 && AI::getRate($this)){
			if($this->charge && $this->onGround){
				$this->yaw += mt_rand(-60, 60);
				if($this->target){
					AI::lookAt($this, $this->target);
				}
				AI::setRate($this, 20);
				AI::jump($this, 0.25, 0, AI::DEFAULT_JUMP*3);
				AI::rangeAttack($this, 2.2, 2);
				$this->getLevel()->addParticle(new DestroyBlockParticle($this, Block::get(2)));
				$this->charge = false;
			}else{
				$this->getMotion()->x = 0;
				$this->getMotion()->z = 0;
				AI::rangeAttack($this, 2.5, 4);
				$this->getLevel()->addParticle(new DestroyBlockParticle($this, Block::get(2)));
				AI::setRate($this, 20);
				$this->charge = true;
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

	public $cooltime, $charge, $target, $mode;
}