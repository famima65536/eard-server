<?php
namespace Eard\MeuHandler;


# Basic
use pocketmine\utils\MainLogger;

# Eard
use Eard\Utils\Chat;


/****
*
*	通貨に関する記述
*/
class Meu {

	//いまんとこPlayerDataを入れておくメリットをあまり感じない 20170616
	//PlayerDataをUniqueNoにした。会社からの送金があるかもしれない。20170702

	/**
	*	@param int $amount | そのmeuの量
	*	@param MeuHandler (Account, Governmentそのた「meuをオブジェクトに持つ」もの)
	*	@return Meu
	*/
	public static function get($amount, MeuHandler $meuHandler){
		$meu = new Meu();
		$meu->amount = $amount;
		$meu->meuHandler = $meuHandler;
		return $meu;
	}

	/**
	*	政府しか使うな!!!
	 * @param int $amount
	*/
	public function setAmount($amount){
		$this->amount = $amount;
	}

	/**
	*	@return MeuHandler このオブジェクトの所有者
	*/
	public function getMeuHandler(){
		return $this->meuHandler;
	}

	public function getName(){
		return "{$this->amount}μ";
	}

	/**
	*	@return int
	*/
	public function getAmount(){
		return $this->amount;
	}

	/**
	*	amount以上あるか確認する
	*	@param int $amount
	*	@return bool
	*/
	public function sufficient($amount){
		return $amount <= $this->amount;
	}

	/**
	*	分割する。 playerの全額面のなかから、一部だけを切り取りたい時に。
	*	@param int $spilitAmount 取り出したいmeu
	*	@return Meu | null
	*/
	public function spilit($spilitAmount){
		if($spilitAmount <= $this->amount){
			//残りが0以下にならないように
			$this->amount = $this->amount - $spilitAmount;
			return self::get($spilitAmount, $this->meuHandler);
		}else{
			//残りが0以下になっちゃう
			return null;
		}
	}

	/**
	*	合算する。だれかのmeuを、こいつのものにする。
	*	@param Meu | 吸収するMeu
	*	@param String $reason そのお金を使った理由、例 Earmazon: ～を購入 / とか
	*	@param String $reason2 お金を送る側に、別のメッセージを送りたい場合は入れろ。上記と同じメッセージで良いなら入れるな。
	*	@return bool
	*/
	public function merge(Meu $meu, $reason, $reason2 = ""){
		$this->amount = $this->amount + $meu->getAmount();

		// 金の流通が発生したとconsoleに表示
		$senderName = $meu->getMeuHandler()->getName();
		$receiverName = $this->getMeuHandler()->getName();
		$subject = "§f{$senderName} §7==={$meu->getName()}==> §f{$receiverName} ({$reason})";
		MainLogger::getLogger()->info(Chat::Format("システム", "§6Console", $subject));


		// お金を受け取る側がPlayerDataであれば
		if($this->getMeuHandler() instanceof Account){
			$playerData = $this->getMeuHandler();

			// 金の使用用途を書く
			$playerData->addHistory($meu->getAmount(), $reason);

			// PMMPからであれば、通知を表示
			$player = $playerData->getPlayer();
			if($player){
				$player->sendMessage(Chat::Format("§8送金処理", "§6個人", $subject));
			}
		}

		// お金を送る側がPlayerDataであれば
		if($meu->getMeuHandler() instanceof Account){
			$playerData = $meu->getMeuHandler();

			// 金の使用用途を書く
			$playerData->addHistory(-1 * $meu->getAmount(), $reason2 ? $reason2 : $reason);

			// PMMPからであれば、通知を表示
			$player = $playerData->getPlayer();
			if($player){
				$player->sendMessage(Chat::Format("§8送金処理", "§6個人", $subject));
			}
		}
		return true;
	}

	private $amount, $meuHandler;

}