<?php
namespace Eard\Form;

# basic
use Eard\DBCommunication\Place;
use Eard\Main;
use pocketmine\Server;
use pocketmine\scheduler\Task;
use pocketmine\math\Vector3;

# Eard
use Eard\DBCommunication\Connection;
use Eard\MeuHandler\Account;
use Eard\Event\AreaProtector;
use Eard\Enemys\AI;
use Eard\Utils\Chat;

class NaviForm extends FormBase {

	const NEXT = 8;

	public $onlinelist = []; // 一時的な保存用
	public $isLivingArea = true;
	public $addressList = [];
	public $name = [];
	public $dest = null; //目的地
	public $data = null;

	public function __construct(Account $playerData, $isLivingArea){
		$this->isLivingArea = $isLivingArea;
		parent::__construct($playerData);
	}

	//案内するためのタスク生成
	public static function init(){
		Main::getInstance()->getScheduler()->scheduleRepeatingTask(new Navigation(), 20);
	}

	public function send(int $id){
		$playerData = $this->playerData;
		$cache = [];
		$data = [];
		switch($id){
			//GPS => 目的地設定
			case 1:
				$this->data = null;
				$title = "GPS > 目的地設定";
				$buttons[] = ['text' => "住所を入力して登録"];
				$cache[] = 2;
				$buttons[] = ['text' => "現在地点の住所を登録する"];
				$cache[] = 3;
				if($this->isLivingArea){ //生活区域
					$buttons[] = ['text' => "自宅に設定"];
					$cache[] = 4;
				}else{//資源区域
					; //なんか必要なら登録
				}
				$buttons[] = ['text' => "登録済みの住所から選ぶ"];
				$cache[] = 5;
				$buttons[] = ['text' => "ユーザー検索"];
				$cache[] = 6;

				$buttons[] = ['text' => "リスポーン地点"];
				$cache[] = 7;

				$buttons[] = ['text' => "目的地を設定しない"];
				$cache[] = 8;

				$navi = $playerData->getNavigating($this->isLivingArea);
				switch (true) {
					case $navi === null:
						$dest = "未設定";
					break;
					case $navi === true:
						$dest = "リスポーン地点";
					break;
					case is_array($navi):
						$dest = AreaProtector::getSectionCode($navi[0], $navi[1]);
					break;
					case is_string($navi):
						$dest = (Server::getInstance()->getPlayer($navi))? $navi: "なし"; 
					break;
					default :
						$dest = "不明";
					break;
				}

				$data = [
					'type'    => "form",
					'title'   => $title,
					'content' => "目的地を選択します。\n現在の目的地 ： {$dest}",
					'buttons' => $buttons
				];
			break;
			case 2:
				// 名前と住所を入力して登録
				$title = "GPS > 目的地設定 > 入力して登録";
				$custom[] = [
					'type' => "input",
					'text' => "",
					'placeholder' => "登録名"
				];
				$custom[] = [
					'type' => "input",
					'text' => "",
					'placeholder' => "住所(半角英数字と-記号で入力)"
				];
				$data = [
					'type'    => "custom_form",
					'title'   => $title,
					'content' => $custom
				];
				$cache[] = 2+self::NEXT;
			break;
			case 2+self::NEXT:
				"GPS > 目的地設定 > 入力して登録";
				$name = $this->lastData[0];
				$address = $this->lastData[1];
				$section = AreaProtector::getCoordinateFromSectionCode($address);
				if($section[0] === 0 && $section[1] === 0){
					$this->sendErrorModal("GPS > 目的地設定 > 入力して登録", "住所の形式が正しくないか、半角英数字と-記号以外の文字が含まれています。", 1);
				}else{
					// 名前を入力して登録
					$title = "GPS > 目的地設定 > 入力して登録 > 確認";
					$data = [
						'type'    => "form",
						'title'   => $title,
						'content' => "{$address}を{$name}として登録します。よろしいですか？",
						'buttons' => [
							['text' => "はい"],
							['text' => "いいえ"]
						]
					];
					$this->data = $section;
					$this->data[3] = $name;
					$cache = [2+self::NEXT*2, 1];
				}
			break;
			case 2+self::NEXT*2:
				$section = [$this->data[0], $this->data[1]];
				if($playerData->registerNavigationPoint($this->data[3], $section, $this->isLivingArea)){
					$playerData->getPlayer()->sendMessage(Chat::SystemToPlayer("「{$this->data[3]}」を登録しました。"));
					$this->close();
				}else{
					$title = "入力して登録 > 確認 > 上書き確認";
					$data = [
						'type'    => "form",
						'title'   => $title,
						'content' => "「{$this->data[3]}」は既に登録されています。上書きしますか？",
						'buttons' => [
							['text' => "はい"],
							['text' => "いいえ"]
						]
					];
					$cache = [2+self::NEXT*3, 2];
				}
			break;
			case 2+self::NEXT*3:
				$section = [$this->data[0], $this->data[1]];
				$playerData->registerNavigationPoint($this->data[3], $section, $this->isLivingArea, true);
				$playerData->getPlayer()->sendMessage(Chat::SystemToPlayer("「{$this->data[3]}」を登録しました。"));
				$this->close();
			break;
			case 3:
				// 名前を入力して登録
				$title = "GPS > 目的地設定 > 現在地点の住所を登録する";
				$player = $playerData->getPlayer();
				$x = round($player->x); $y = round($player->y); $z = round($player->z);
				$sx = AreaProtector::calculateSectionNo(round($x)); $sz = AreaProtector::calculateSectionNo(round($z));
				$address = AreaProtector::getSectionCode($sx, $sz);
				$this->data = [$sx, $sz];
				$custom[] = [
					'type' => "input",
					'text' => "住所 : {$address}",
					'placeholder' => "登録名"
				];
				$data = [
					'type'    => "custom_form",
					'title'   => $title,
					'content' => $custom
				];
				$cache[] = 3+self::NEXT;
			break;
			case 3+self::NEXT:
				// 名前を入力して登録
				$title = "GPS > 目的地設定 > 現在地点の住所を登録する > 確認";
				$section = $this->data;
				$address = AreaProtector::getSectionCode($section[0], $section[1]);
				$data = [
					'type'    => "form",
					'title'   => $title,
					'content' => "{$address}を{$this->lastData[0]}として登録します。よろしいですか？",
					'buttons' => [
						['text' => "はい"],
						['text' => "いいえ"]
					]
				];
				$this->data[3] = $this->lastData[0];
				$cache = [3+self::NEXT*2, 1];
			break;
			case 3+self::NEXT*2:
				$section = [$this->data[0], $this->data[1]];
				if($playerData->registerNavigationPoint($this->data[3], $section, $this->isLivingArea)){
					$playerData->getPlayer()->sendMessage(Chat::SystemToPlayer("「{$this->data[3]}」を登録しました。"));
					$this->close();
				}else{
					$title = "現在地点の住所を登録する > 確認 > 上書き確認";
					$data = [
						'type'    => "form",
						'title'   => $title,
						'content' => "「{$this->data[3]}」は既に登録されています。上書きしますか？",
						'buttons' => [
							['text' => "はい"],
							['text' => "いいえ"]
						]
					];
					$cache = [3+self::NEXT*3, 3];
				}
			break;
			case 3+self::NEXT*3:
				$section = [$this->data[0], $this->data[1]];
				$playerData->registerNavigationPoint($this->data[3], $section, $this->isLivingArea, true);
				$playerData->getPlayer()->sendMessage(Chat::SystemToPlayer("「{$this->data[3]}」を登録しました。"));
				$this->close();
			break;
			case 4:
				//自宅を案内先にする
				if(empty($playerData->getAddress())){
					$this->sendErrorModal("GPS > 目的地設定 > 自宅に設定", "自宅がありませんでした。", 1);
				}else{
					$playerData->setNavigating($playerData->getAddress(), $this->isLivingArea);
					$playerData->getPlayer()->sendMessage(Chat::SystemToPlayer("案内先を「自宅」に設定しました"));
					$this->close();
				}
			break;
			case 5:
				//登録済みの住所から選ぶ
				$navilist = $playerData->getNavigatingList($this->isLivingArea);
				$title = "GPS > 目的地設定 > 登録済みの住所から選ぶ";
				if(empty($navilist)){
					$this->sendErrorModal($title, "どこも登録されていません。\n[住所を入力して登録] または [現在地点の住所を登録する]で登録してください。", 1);
				}else{
					//ユーザー検索
					$list = ["(選択なし)"];
					$this->addressList = [];
					$cnt = 1;
					foreach($navilist as $name => $section){
						$address = AreaProtector::getSectionCode($section[0], $section[1]);
						$list[] = "{$name} ({$address})";
						$this->addressList[$cnt] = $section;
						$this->name[$cnt] = $name;
						++$cnt;
					}
					$custom[] = [
						'type' => "dropdown",
						'text' => "",
						'options' => $list
					];
					$custom[] = [
						'type' => "toggle",
						'text' => "削除する"
					];
					$data = [
						'type'    => "custom_form",
						'title'   => $title,
						'content' => $custom
					];
					$cache[] = 5+self::NEXT;
				}
			break;
			case 5+self::NEXT:
				if($this->lastData[0] === 0){ //選択なし
					$playerData->setNavigating(null, $this->isLivingArea);
					$playerData->getPlayer()->sendMessage(Chat::SystemToPlayer("案内先を未選択状態に設定しました"));
					$this->close();
				}else if($this->lastData[1]){
					$this->data = $this->name[$this->lastData[0]];
					$title = "GPS > 目的地設定 > 登録済みの住所から選ぶ > 削除";
					$data = [
						'type'    => "form",
						'title'   => $title,
						'content' => "「{$this->data}」を削除します。よろしいですか？",
						'buttons' => [
							['text' => "はい"],
							['text' => "いいえ"]
						]
					];
					$cache = [5+self::NEXT*2, 5];
				}else{
					$section = $this->addressList[$this->lastData[0]];
					$name = $this->name[$this->lastData[0]];
					$playerData->setNavigating($section, $this->isLivingArea);
					$playerData->getPlayer()->sendMessage(Chat::SystemToPlayer("案内先を「{$name}」に設定しました"));
					$this->close();
				}
			break;
			case 5+self::NEXT*2:
				$result = $playerData->diffNavigationPoint($this->data, $this->isLivingArea);
				if($result){
					$playerData->getPlayer()->sendMessage(Chat::SystemToPlayer("「{$this->data}」を削除をしました"));
					$this->close();
				}else{
					$playerData->getPlayer()->sendMessage(Chat::SystemToPlayer("「{$this->data}」は未登録でした"));
					$this->close();
				}
			break;
			case 6:
				//ユーザー検索
				$title = "GPS > 目的地設定 > ユーザー検索";
				$list = ["(選択なし)"];
				$this->onlinelist = [];
				$cnt = 1;
				foreach(Server::getInstance()->getOnlinePlayers() as $player){
					if($playerData->getPlayer()->getName() != $player->getName()){
						$list[] = $player->getName();
						$this->onlinelist[$cnt] = $player->getName();
						++$cnt;
					}
				}
				$custom[] = [
					'type' => "dropdown",
					'text' => "",
					'options' => $list
				];
				$data = [
					'type'    => "custom_form",
					'title'   => $title,
					'content' => $custom
				];
				$cache[] = 6+self::NEXT;
			break;
			case 6+self::NEXT:
				if($this->lastData[0] === 0){ //選択なし
					$playerData->setNavigating(null, $this->isLivingArea);
					$playerData->getPlayer()->sendMessage(Chat::SystemToPlayer("案内先を未選択状態に設定しました"));
					$this->close();
				}else{
					$name = $this->onlinelist[$this->lastData[0]];
					$title = "GPS > 目的地設定 > ユーザー検索 > {$name}";
					$data = [
						'type'    => "form",
						'title'   => $title,
						'content' => "案内先を選択してください。",
						'buttons' => [
							['text' => "{$name}さん"],
							['text' => "{$name}さんの自宅"],
							['text' => "戻る"]
						]
					];
					$this->data = $name;
					$cache = [6+self::NEXT*2, 6+self::NEXT*2, 6];
				}
			break;
			case 6+self::NEXT*2:
				$name = $this->data;
				if($this->lastData === 0){
					if(Server::getInstance()->getPlayer($name)){
						$playerData->setNavigating($name, $this->isLivingArea);
						$playerData->getPlayer()->sendMessage(Chat::SystemToPlayer("案内先を「{$name}さん」に設定しました"));
						$this->close();
					}else{
						$this->sendErrorModal("GPS > 目的地設定 > ユーザー検索 > {$name}", "そのプレイヤーは同じ区域にいませんでした。", 6);
					}
				}else{
					$ac = Account::getByName($name);
					if(!$ac instanceof Account){
						$this->sendErrorModal("GPS > 目的地設定 > ユーザー検索 > {$name}", "そのプレイヤーは同じ区域にいませんでした。", 6);
					}elseif(empty($ac->getAddress())){
						$this->sendErrorModal("ユーザー検索 > {$name} > {$name}さんの自宅", "{$name}さんの自宅はありませんでした。", 6);
					}else{
						$playerData->setNavigating($ac->getAddress(), $this->isLivingArea);
						$playerData->getPlayer()->sendMessage(Chat::SystemToPlayer("案内先を「{$name}さんの自宅」に設定しました"));
						$this->close();
					}
				}
			break;
			case 7:
				$playerData->setNavigating(true, $this->isLivingArea);
				$playerData->getPlayer()->sendMessage(Chat::SystemToPlayer("案内先を「リスポーン地点」に設定しました"));
				$this->close();
			break;
			case 8:
				$playerData->setNavigating(null, $this->isLivingArea);
				$playerData->getPlayer()->sendMessage(Chat::SystemToPlayer("案内先を未選択状態に設定しました"));
				$this->close();
			break;
		}
		
		// みせる
		if($cache){
			// sendErrorMoralのときとかは動かないように
			$this->lastSendData = $data;
			$this->cache = $cache;
			$this->show($id, $data);
		}
	}
}

class Navigation extends Task{

	public function onRun($tick){
		if(Connection::getPlace() instanceof Place){
			$isLivingArea = Connection::getPlace()->isLivingArea();
			foreach(Server::getInstance()->getOnlinePlayers() as $player){
				$playerData = Account::get($player);
				$target = $playerData->getNavigating($isLivingArea);
				if($target !== null){
					$pos = null;
					switch (true) {
						case $target === true: //リスポーン地点
							$pos = $player->getSpawn();
						break;
						case is_string($target): //プレイヤー名
							$p = Server::getInstance()->getPlayer($target);
							if($p){
								$pos = $p;
							}else{
								$playerData->setNavigating(null, $isLivingArea);
								$player->sendMessage(Chat::SystemToPlayer("そのプレイヤーは同じ区域にいませんでした"));
								$player->sendMessage(Chat::SystemToPlayer("案内先を未選択状態に設定しました"));
							}
						break;
						case is_array($target): //セクション
							$pos = new Vector3(AreaProtector::uncalculateSectionNo($target[0]), 0, AreaProtector::uncalculateSectionNo($target[1])); //どこかに動的に保存すれば軽量化できるかも
						break;
					}
					if($pos) AI::addGuideParticle($player, $pos);
				}
			}
		}
	}
}