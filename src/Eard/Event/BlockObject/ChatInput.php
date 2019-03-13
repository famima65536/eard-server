<?php
namespace Eard\Event\BlockObject;


use pocketmine\Player;


interface ChatInput {

	//チャット入力があった際に、それを受け取るかどうか

	/**
	*	チャットがされた時
	*	キャンセル不可
	*	@return bool
	*/
	public function Chat(Player $player, String $txt);


}