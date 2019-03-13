<?php
namespace Eard\DBCommunication;


# Basic
use pocketmine\utils\MainLogger;
use pocketmine\item\Item;


class Place {

	const STAT_ONLINE     = 1;
	const STAT_OFFLINE    = 2;
	const STAT_PREPAREING = 3;
	const STAT_UNKNOWN	  = 4;

	private $place, $addr, $port;

	/**
	*	@param int 場所番号
	*/
	function __construct($place){
		$this->place = $place;
	}

	/**
	*	@return String
	*/
	public function getName(){
		$ar = [
			0 => "",
			1 => "生活区域",
			2 => "資源区域",
			8 => "開発区域-生活",
			9 => "開発区域-資源",
		];
		return isset($ar[$this->place]) ? $ar[$this->place] : "";
	}

	public function getAddr(){
		return $this->addr;
	}
	public function getPort(){
		return $this->port;
	}

	/**
	*	ここ(今空いているこの鯖)が、生活区域なのか資源区域なのか
	*	@return bool
	*/
	public function isLivingArea(){
		return $this->place === 1 or $this->place === 8;
	}
	public function isResourceArea(){
		return $this->place === 2 or $this->place === 9; //dev鯖でも、resourceAreaでtrueかえす
	}
	public function isDevServer(){
		return $this->place === 8 or $this->place === 9;
	}

/*	サーバー(place)自体のステータス
*/

	/**
	*	place番号における、ipとポートを、更新してdbに書き込み。
	 * @param string $ip
	 * @param int|string $port
	*/
	public function writeAddrInfo($ip, $port){
		$place = $this->place;
		if($place){
			$sql = "UPDATE statistics_server SET ip = '{$ip}', port = {$port} WHERE place = {$place}; ";
			$result = DB::get()->query($sql);
			if($result){
				MainLogger::getLogger()->notice("§aPlace: このサーバーのIPは{$ip}:{$port}で、これを PlaceNo {$place} として記録しました");
			}else{
				MainLogger::getLogger()->notice("§cPlace: エラー。サーバーのIPは記録されていません。");
			}
		}else{
			MainLogger::getLogger()->notice("§ePlace: エラー。このサーバーのplaceが設定されていません。サーバーのIPは記録されていません。");
		}
	}

	/**
	*	このplace番号における、ipとポートを、dbからロード。
	*	@return void
	*/
	public function loadAddrInfo(){
		$place = $this->place;
		if($place){
			$sql = "SELECT * from statistics_server WHERE place = {$place};";
			$result = DB::get()->query($sql);
			if($result){
				while($row = $result->fetch_assoc()){
					$ip = $row['ip']; $port = $row['port'];
					$this->addr = (string) $ip;
					$this->port = (int) $port;
				}
			}
		}
	}

	/**
	*	鯖をつけたとき、オンラインだよーって記録するやつ。
	*	placeの値が設定されていないときは、何もしない。
	*	@return void
	*/
	public function makeOnline(){
		$stat = self::STAT_ONLINE;
		$place = $this->place;
		if($place){
			$sql = "UPDATE statistics_server SET stat = {$stat}, lastupdate = now() WHERE place = {$place}; ";
			$result = DB::get()->query($sql);
			if($result){
				MainLogger::getLogger()->notice("§aPlace: §fサーバーを「§aオンライン状態§f」と記録しました");
			}else{
				MainLogger::getLogger()->notice("§cPlace: エラー。サーバーの状態は記録されていません");
			}
		}else{
			MainLogger::getLogger()->notice("§ePlace: このサーバーのplaceが設定されていません。サーバーの状態は記録されていません");
		}
	}

	/**
	*	鯖をけしたとき、オフラインだよーって記録するやつ。
	*	placeの値が設定されていないときは、何もしない。
	*	@return void
	*/
	public function makeOffline(){
		$stat = self::STAT_OFFLINE;
		$place = $this->place;
		if($place){
			$sql = "UPDATE statistics_server SET stat = {$stat}, lastupdate = now() WHERE place = {$place}; ";
			$result = DB::get()->query($sql);
			if($result){
				MainLogger::getLogger()->notice("§aPlace: §fサーバーを「§cオフライン状態§f」と記録しました");
			}else{
				MainLogger::getLogger()->notice("§cPlace: エラー。サーバーの状態は記録されていません");
			}
		}else{
			MainLogger::getLogger()->notice("§ePlace: このサーバーのplaceが設定されていません。サーバーの状態は記録されていません");
		}
	}

	/**
	*	placeに割り当てられているサーバーの状態を確認する。タイムアウトになっていた場合は4が帰る
	*	@param int place(場所番号/鯖番号) (生活区域 = 1, 資源区域 = 2)
	*	@return int 0 ~ 4 (0...エラー 1-4 ステータス表示)
	*/
	public function getStatus(){
		$place = $this->place;
		$sql = "SELECT stat,lastupdate FROM statistics_server WHERE place = {$place}; ";
		$result = DB::get()->query($sql);
		if($result){
			$stat = 0;
			$updatedTime = 0;
			while($row = $result->fetch_assoc()){
				$stat = (int) $row['stat'];
				$updatedTime = $row['lastupdate'];
			}
			if(time() < strtotime($updatedTime) + 180){//3分以上前のデータであったらタイムアウトで4を返す
				$stat = self::STAT_UNKNOWN;
			}
			return $stat;
		}else{
			return 0;
		}
	}

	/**
	*	@return String
	*/
	public function getStatusTxt(){
		$stat = $this->getStatus();
		switch($stat){
			case self::STAT_ONLINE:
				$out = "Opened (転送可能)"; break;
			case self::STAT_OFFLINE:
				$out = "Closed (転送不可能)"; break;
			case self::STAT_PREPAREING:
				$out = "Prepareing (転送不可。政府が調査中)"; break;
			case self::STAT_UNKNOWN:
				$out = "Lost Connection (Eardとの通信不良)"; break;
			default:
				$out = "[ERROR]"; break;
		}
		return $out;
	}

/*	プレイヤーのオンラインオフラインとか
*/


	/**
	*	web用
	*	ログインしていると記録されているプレイヤーを全員返す
	*	@param String 	プレイヤー名
	*	@return String[] プレイヤー名がはいってるarray
	*/
	public function getLoginPlayersName(){
		$place = $this->place; // 場所番号/鯖番号 (生活区域 = 1, 資源区域 = 2)
		$sql = "SELECT * FROM statistics_player WHERE place = {$place}; ";
		$result = DB::get()->query($sql);
		if($result){
			$out = [];
			while($row = $result->fetch_assoc()){
				$out[] = $row['name'];
			}
			return $out;
		}else{
			return ['32ki_dummy'];
		}
	}

	/**
	*	そのプレイヤーがログインしたよーってことをDBに記録する。
	*	webから、オンラインかオフラインか見れるようにするため
	*	@param String 	プレイヤー名
	*	@return bool 	クエリが成功したか
	*/
	public function recordLogin($name){
		$place = $this->place; // 場所番号/鯖番号 (生活区域 = 1, 資源区域 = 2)
		if($place){
			$sql = "INSERT INTO statistics_player (name, place, date) VALUES ('{$name}', '{$place}', now() ); ";
			$result = DB::get()->query($sql);
			return $result;
		}else{
			MainLogger::getLogger()->notice("§ePlace: このサーバーのplaceが設定されていません。Method Place::recordLogin() failed.");
		}
	}

	/**
	*	そのプレイヤーがログアウトしたよーってことをDBに記録する。(削除する)
	*	@param String 	プレイヤー名
	*	@param Int 		場所番号(生活区域 = 1, 資源区域 = 2)
	*	@return int 	-1 ~ 1 (-1...取得/接続不可 0...クエリ失敗 1...クエリ成功)
	*/
	public function recordLogout($name){
		$place = $this->place; // 場所番号/鯖番号 (生活区域 = 1, 資源区域 = 2)
		if($place){
			$sql = "DELETE FROM statistics_player WHERE name = '{$name}'; ";
			$result = DB::get()->query($sql);
			return $result;
		}else{
			MainLogger::getLogger()->notice("§ePlace: このサーバーのplaceが設定されていません。Method Place::recordLogout() failed.");
		}
	}

	/**
	*	プレイヤーのいる場所が移った時に、記録する。Transferの場合のみなのでprivate
	*	@param String 	プレイヤー名
	*	@param Int 		場所番号(生活区域 = 1, 資源区域 = 2)
	*	@return bool 	クエリが成功したか
	*/
	/*
	// duplicated 20170907
	private function recordUpdate($name){
		$place = $this->place; // 場所番号/鯖番号 (生活区域 = 1, 資源区域 = 2)
		$sql = "UPDATE statistics_player SET place = {$place} WHERE name = '{$name}'; ";
		$result = DB::get()->query($sql);
		return $result;
	}
	*/

	/**
	*	そのplaceにいる、と記録されているプレイヤーを全削除する
	*	@param String 	プレイヤー名
	*	@param Int 		場所番号(生活区域 = 1, 資源区域 = 2)
	*	@return int 	-1 ~ 1 (-1...取得/接続不可 0...クエリ失敗 1...クエリ成功)
	*/
	public function erase(){
		$place = $this->place; // 場所番号/鯖番号 (生活区域 = 1, 資源区域 = 2)
		if($place){
			$sql = "DELETE FROM statistics_player WHERE place = {$place}; ";
			$result = DB::get()->query($sql);
			return $result;
		}else{
			MainLogger::getLogger()->notice("§ePlace: このサーバーのplaceが設定されていません。Method Place::erase() failed.");
		}
	}



}