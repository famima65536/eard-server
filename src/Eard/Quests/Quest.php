<?php
namespace Eard\Quests;

use Eard\Utils\Chat;
use Eard\Utils\ChestIO;
use Eard\MeuHandler\Government;
use Eard\MeuHandler\Account;
use pocketmine\item\Item;
use pocketmine\Player;

class Quest{
	public static $allQuests = [];

	const TYPE_MEU = 0;
	const TYPE_ITEM = 1;

	const QUESTID = 0;
	const NORM = 0;
	public $achievement = 0;
	private $inventory;

	const TYPE_SUBJUGATION = 1;//討伐系
	const TYPE_DELIVERY = 2;//納品系
	const TYPE_SPECIAL = 3;//特殊なやつ

	public static function getAllQuests(){
		return self::$allQuests;
	}

	public static function get(int $id, int $achievement = 0){
		if(isset(self::$allQuests[$id])){
			$class = self::$allQuests[$id];
			$quest = new $class(); 
			$quest->achievement = $achievement;
			return $quest;
		}
		return null;
	}

	public function getQuestId(){
		return static::QUESTID;
	}

	public static function getNorm(){
		return static::NORM;
	}

	public function getNormI(){
		return static::NORM;
	}

	/*目的達成するたびに+1
	*/
	public function addAchievement(){
		$this->achievement++;
		if($this->checkAchievement()){
			return true;
		}else{
			return false;
		}
	}

	/*現在の達成状況を返す
	*/
	public function getAchievement(){
		return $this->achievement;
	}

	public function checkAchievement(){
		return (static::NORM <= $this->achievement);
	}

	public function sendRewardMeu(Player $player, int $amount){
		//Meu送金処理
		$player->sendMessage(Chat::SystemToPlayer("§e報酬金 {$amount}μ 獲得しました"));
		Government::giveMeu(Account::get($player), $amount, "Quest: クリア報酬 {$amount}μ");
	}

	public function checkDelivery(Player $player){
		$inv = $player->getInventory();
		$delitem = static::getTarget();
		$delid = $delitem[0];
		$deldamage = $delitem[1];
		$delamount = static::getNorm();
		$contents = $inv->getContents();
		$sendc = $contents;
		foreach ($contents as $index => $item) {
			if($item->getId() === $delid && $item->getDamage() === $deldamage){
				if($delamount >= $item->getCount()){
					unset($sendc[$index]);
					$delamount -= $item->getCount();
				}else{
					$sendc[$index] = $item->setCount($item->getCount() - $delamount);
					$delamount = 0;
				}
				if($delamount === 0){
					break;
				}
			}
		}
		if($delamount === 0){
			$inv->setContents(array_values($sendc));
			return true;
		}else{
			return false;
		}
	}

	public function sendRewardItem(Player $player, Item $item){
		//アイテム送信処理
		$this->inventory = new ChestIO($player);
		$this->inventory->additem($item);
		$this->inventory->setName("報酬ボックス(閉じると中のアイテムは消滅します)");
		$player->addWindow($this->inventory);
	}

}