<?php
namespace Eard\Quests\Level1;

use Eard\Quests\Quest;
use pocketmine\item\Item;

class mission2 extends Quest{
	const QUESTID = 2;
	const NORM = 5;
	public $achievement = 0;

	public static function getName(){
		return "撒かない種に芽は出ない";
	}

	public static function getDescription(){
		return "うちの土地が悪いのか小麦が育たなくてさ...\n代わりに持ってきてくれないかい？";
	}

	public static function getQuestType(){
		return self::TYPE_DELIVERY;
	}

	public static function getNorm(){
		return self::NORM;
	}

	public static function getTarget(){
		return [Item::WHEAT, 0];
	}

	/*目的達成するたびに+1
	*/
	public function addAchievement(){
		$result = parent::addAchievement();
	}
}