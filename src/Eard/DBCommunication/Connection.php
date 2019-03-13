<?php
namespace Eard\DBCommunication;


# Basic
use pocketmine\Server;
use pocketmine\utils\MainLogger;

# Eard
use Eard\Utils\DataIO;
use Eard\MeuHandler\Account;
use Eard\Utils\Chat;


class Connection {


	//placeをオブジェクトごとに分ける作業 2017/7/19
	private static $placeNo = 0; //このサバは、どちらに当たるのかを指定
	private static $places = [];

/*	プレイヤーの鯖間転送
*/

	/**
	*	生活側で使うメソッド。飛ばせるか確認の処理などをすべてこちらで行う。
	 * @param Account $PlayerData
	 * @param Place $place
	*	@return Int 	-1 ~ 1 (-1..エラー発生, 0...不一致のため入れず 1...はいれる)
	*/
	public static function Transfer(Account $PlayerData, Place $place){
		$player = $PlayerData->getPlayer();
		if(!$player){
			MainLogger::getLogger()->notice("§cConnection: Player not found...");
			return -1;
		}

		// この辺にインベントリを圧縮する処理?


		// このplaceがセットされているか？
		if(!self::$placeNo){
			MainLogger::getLogger()->notice("§eConnection: Transfer has been canceled. You should set your 'placeNo' immedeately!");
			return -1;
		}

		// 転送先が開いているかチェック
		if($place->getStatus() !== Place::STAT_ONLINE){
			$statustxt = $place->getStatusTxt();
			$msg = Chat::SystemToPlayer("現在転送先は {$statustxt} の状態なので、転送はできません。");
			$player->sendMessage($msg);
			return -1;
		}

		// 接続先情報
		$addr = $place->getAddr();
		$port = $place->getPort();
		if(!$addr or !$port){
			$msg = Chat::SystemToPlayer("転送先の情報が不明です。管理者に報告してください。");		
			$player->sendMessage($msg);
			// echo "{$addr}:{$port}";
			return -1;
		}

		// 転送モードに移行、これをいれると、quitの時のメッセージが変わる
		$PlayerData->setNowTransfering(true);

		// 実際飛ばす処理
		$player->transfer($addr, $port); // あらゆる処理の最後に持ってくるべき
		return 1;
	}

	/**
	*	資源側で使うメソッド。1以外が帰った場合には、ログイン不可画面を出す。
	*	@return Int 	-1 ~ 1 (-1..エラー発生, 0...不一致のため入れず 1...はいれる)
	*/
	public static function canLoginToResourceArea(Account $PlayerData){
		$player = $PlayerData->getPlayer();
		if(!$player){
			MainLogger::getLogger()->notice("§cConnection: Player not found...");
			return -1;
		}

		// 直前まで、古いほうにログインしてたか
		$result = self::isLoggedIn($player->getName());
		if($result === 1){
			return 1;
		}else{
			if($result === -1){
				return -1;
			}else{
				return 0;
			}
		}
	}


/*	全般
*/

	/**
	*	今空いている、このさばの、placeを返す。webからは使うな。
	*	@return Place | null
	*/
	public static function getPlace(){
		return isset(self::$places[self::$placeNo]) ? self::$places[self::$placeNo] : null;
	}

	/**
	*	placeの番号からPlaceを取得する。webからつかってもおっけー
	*	@param int placeNo
	*	@return Place
	*/
	public static function getPlaceByNo($placeNo){
		return self::$places[$placeNo];
	}

	/**
	*	初期セットアップ。データベースのセットアップ。
	*/
	public static function setup(){
		$sql = "INSERT INTO statistics_server (place, name, stat) VALUES ('1', '生活区域', '".Place::STAT_PREPAREING."'); ".
				"INSERT INTO statistics_server (place, name, stat) VALUES ('2', '資源区域', '".Place::STAT_PREPAREING."'); ".
				"INSERT INTO statistics_server (place, name, stat) VALUES ('8', '開発区域-生活', '".Place::STAT_PREPAREING."'); ".
				"INSERT INTO statistics_server (place, name, stat) VALUES ('9', '開発区域-資源', '".Place::STAT_PREPAREING."'); ".
				"";
				echo $sql;
		$db = DB::get();
		$result = $db->query($sql);
	}

/*	プレイヤーのステータス
*/

	/**
	*	webで使うだろうからpublic
	*	該当プレイヤーが、ログインしていると記録してあるかをチェック。どちらかのサバに？
	*	@param String 	プレイヤー名
	*	@return Int  	-1 ~ 2 (-1...取得/接続不可 0...いない 1...生活区域 2...資源区域)
	*/
	public static function isLoggedIn($name){
		$sql = "SELECT * FROM statistics_player WHERE name = '{$name}'; ";
		$result = DB::get()->query($sql);
		if($result){
			$placeNo = 0;
			while($row = $result->fetch_assoc()){
				$placeNo = $row['place'];
			}
			return $placeNo;
		}else{
			return -1;
		}
	}

/*	クラスで使うデータ
*/

	public static function load(bool $fromweb = false){
		$data = DataIO::load('Connection');
		if($data){
			self::$placeNo = (int) $data[0];
			MainLogger::getLogger()->notice("§aConnection: place data has been loaded");
		}else{
			MainLogger::getLogger()->notice("§eConnection: Cannnot load place data. You should set your 'connection place' immedeately!");
		}
		self::$places[1] = new Place(1);
		self::$places[2] = new Place(2);
		self::$places[8] = new Place(8); //開発用のさばの場合
		self::$places[9] = new Place(9); //開発用のさばの場合

		// この鯖の情報を書き込む。オンラインにする。
		if(!$fromweb and self::$placeNo){
			$place = self::$places[self::$placeNo];

			// この鯖のIPをportの情報を書き込み
			$ip = self::getIpOfThis();
			$port = Server::getInstance()->getPort();
			$place->writeAddrInfo($ip, $port);

			// この鯖をオンラインと記録
			$place->makeOnline();

			// かこのぷれいやーばいばい
			$place->erase();
		}

		// 他のサバのIPやportを取得
		foreach(self::$places as $placeNo => $p){
			$p->loadAddrInfo();
		}
	}

	public static function close(){
		if(self::$placeNo){
			// オフラインと記録する処理
			$place = self::$places[self::$placeNo];
			$place->makeOffline();

			// 一応全員消し去る
			$place->erase();
		}
	}

	public static function writePlace($placeNo){
		self::$placeNo = $placeNo;
		//毎回セーブする必要はない。起動中に書き換わらないから。コマンドでセーブするのみ。
		$data = [
			self::$placeNo
		];

		$result = DataIO::save('Connection', $data);
		if($result){
			MainLogger::getLogger()->notice("§aConnection: place data has been saved");
		}
	}

	/**
	*	urlにアクセスして、得られたjsonをarrayにして返す
	*	@param String URL (http://からはじまる)
	*	@return array
	*/
	private static function curlUnit($url){
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		// curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
		// curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
		$response = curl_exec($curl);
		$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if($httpcode){
			if($httpcode < 300){
				return json_decode($response, true);
			}else{
				return false; //404
			}
		}else{
			return false;
		}
	}

	/**
	*	この鯖のIPを取得する。開ける場所によって異なる。
	*/
	public static function getIpOfThis(){
		$data = self::curlUnit("http://eard.32ki.net/lib/api/ip.php");
		if(!$data){
			MainLogger::getLogger()->notice("§cConnection: IPデータ取得失敗");
			return false;
		}

		return $data["ip"];
	}
}