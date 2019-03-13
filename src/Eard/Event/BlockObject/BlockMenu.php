<?php
namespace Eard\Event\BlockObject;


# TextParticle
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\utils\UUID;

# Eard
use Eard\MeuHandler\Account;


trait BlockMenu {

/*
	コマンドをなくすための、ブロック型展開メニュー。
	長押しで決定、タップで次のカーソルへ移動を行う。
*/

	//useするclassではかならず これらを持っててね
	abstract public function getObjIndexNo();
	abstract public function getPageAr($no, $player);

/*
	I/O (tap)
*/

	public function MenuTap(Player $player){
		if(!isset($this->menu[$player->getName()])){
			//全部の値を送信用にセット
			$this->menu[$player->getName()] = [1, 0, null, null, false]; // [$page, $cursor, $pageAr, $temp, $sent]
			$pageAr = $this->getPageAr($this->menu[$player->getName()][0], $player);
			$this->menu[$player->getName()][2] = $pageAr;
			$this->menu[$player->getName()][3] = $this->makeTemp($pageAr);
		}else{
			$this->menu[$player->getName()][1] = $this->getNextCursor($player);
		}
		//あとは向こうで頑張って
		$this->sendTextParticle($player);
	}

	public function MenuLongTap(Player $player){
		if(isset($this->menu[$player->getName()])){//最初タップしてからアクティベート
			//現在のカーソル
			$cursor = $this->menu[$player->getName()][1];
			//カーソルから次のページ番号を探す
			$pageNo = $this->menu[$player->getName()][2][ $this->menu[$player->getName()][3][$cursor] ][1];
			//次のページを送信用にセット、向こうで頑張って
			$this->sendPageData($pageNo, $player);
		}
	}

/*
	Calculate
*/

	public function makeTemp(int $pageAr){
		$temp = [];
		foreach($pageAr as $key => $ar){
			if($ar[1]) $temp[] = $key; //ページ番号が入っていたらそれはカーソルなので、カウント
		}
		return $temp;
	}

	public function getNextCursor(Player $player){
		if(count($this->menu[$player->getName()][3]) === 1){
			return 0;
		}else{
			$preNextCursor = $this->menu[$player->getName()][1] + 1;
			return isset($this->menu[$player->getName()][3][$preNextCursor]) ? $preNextCursor : 0;
		}
	}

	/**
	*	直接、ページ内容を送る。pageNoを指定すればびゅーんととべるので
	*	Chatや、getPageArからの指定でもいいぞ。
	 * @param int $pageNo
	 * @param Player $player
	*/
	public function sendPageData(int $pageNo, Player $player){
		//おくったと記録し
		$this->menu[$player->getName()][0] = $pageNo;
		//次ページの初期のカーソル位置
		$this->menu[$player->getName()][1] = 0;
		//pageArを、送信ページリストにぶちこむ
		$pageAr = $this->getPageAr($pageNo, $player);
		if($pageAr){
			$this->menu[$player->getName()][2] = $pageAr;
			//tempをつくる
			$this->menu[$player->getName()][3] = $this->makeTemp($pageAr);
		}
		//後は向こうで頑張って
		$this->sendTextParticle($player);
	}

/*
	Text Particle 
*/

	private function getAddPacket(string $text){
		$pk = new AddPlayerPacket();
		$pk->entityRuntimeId = 900000 + $this->getObjIndexNo();
		$pk->uuid = UUID::fromRandom();
		$pk->username = "";
		$pk->position = new Vector3($this->x + 0.5,$this->y + 1.5,$this->z + 0.5);
		$pk->item = ItemFactory::get(Item::AIR, 0, 0);

		$flags = (
			(1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG) |
			(1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG) |
			(1 << Entity::DATA_FLAG_IMMOBILE)
		);
		$pk->metadata = [
			Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
			Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $text],
			Entity::DATA_SCALE =>   [Entity::DATA_TYPE_FLOAT,  0.01] //zero causes problems on debug builds
		];
		return $pk;
	}

	public function getRemovePacket(){
		$pk = new RemoveEntityPacket;
		$pk->entityUniqueId = 900000 + $this->getObjIndexNo();
		return $pk;
	}

	public function sendTextParticle(Player $player){
		//一回送って居たら、削除するぱけっと
		//print_r($this->menu[$player->getName()]);
		$sent = $this->menu[$player->getName()][4];
		if($sent){
			$pk = $this->getRemovePacket();
			$player->directDataPacket($pk);
		}

		//送るテキスト用意
		$cursor = $this->menu[$player->getName()][1];
		$pageAr = $this->menu[$player->getName()][2];
		$text = "";
		$targetRowNo = $this->menu[$player->getName()][3][$cursor];
		foreach($pageAr as $rowNo => $p){
			//カーソルのところは オレンジ 選択できないところは白 選択できるところは灰色
			$textcolor = $p[1] ? ($rowNo === $targetRowNo ? "§a" : "§7") : "§f";
			$blank = isset($p[2]) ? $p[2] : "\n"; 
			$text .= $textcolor.$p[0].$blank;
		}
		//パケット用意
		$pk = self::getAddPacket($text);

		//いってらっしゃい
		$player->directDataPacket($pk);
		$this->menu[$player->getName()][4] = true;
	}

	public function removeTextParticleAll(){
		foreach($this->menu as $name => $d){
			$player = Account::getByName($name)->getPlayer();
		}
		if($player instanceof Player){
			$pk = $this->getRemovePacket();
			$player->directDataPacket($pk);
		}
	}

	private $menu = [];

}