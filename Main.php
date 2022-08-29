<?php

namespace galaxygamer088\SupporterPlugin;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\item\ItemIds;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;

class Main extends PluginBase implements Listener{

public Config $message;
public Config $options;
public Config $playerList;
public string $profilePlayer;
public int $profileReportInt;

    public function onEnable() : void{
        @mkdir($this->getDataFolder()."Player");
        $this->saveResource("Options.yml");
        $this->saveResource("PlayerList.yml");
        $this->options = new Config($this->getDataFolder()."Options.yml", Config::YAML);
        $this->playerList = new Config($this->getDataFolder()."PlayerList.yml", Config::YAML);
        $this->message = new Config($this->getDataFolder()."Message.yml", Config::YAML);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->message->setNested("Message.Count", 1);
        $this->message->setNested("Message.1.Player", "Server");
        $this->message->setNested("Message.1.Text", $this->getMessage("ServerRestart"));
        $this->message->save();
    }

    public function login(PlayerLoginEvent $ev){
        $playerName = $ev->getPlayer()->getName();
        $playerConfig = new Config($this->getDataFolder()."Player/".$playerName.".yml", Config::YAML);
        if($this->isBlocked($playerName) == true){
            if($ev->getPlayer() instanceof Player){
                if($playerConfig->getNested("Banned.Art") == "PermaBan"){
                    $ev->getPlayer()->kick($this->getMessage("Table_16.PlayerPermaBan"));
                }
                if($playerConfig->getNested("Banned.Art") == "Ban"){
                    $ev->getPlayer()->kick($this->getMessage("Table_16.PlayerTimeBan")."\n> ".$this->getBannedTime($playerName));
                }
            }
        }
    }

    public function onJoin(PlayerJoinEvent $ev){
        $playerName = $ev->getPlayer()->getName();
        $count = $this->playerList->getNested("PlayerList.Count") + 1;
        if($this->getPlayerIdByName($playerName) == 0){
            $this->playerList->setNested("PlayerList.".$count, $playerName);
            $this->playerList->setNested("PlayerList.Count", $count);
            $this->playerList->save();

            $playerConfig = new Config($this->getDataFolder()."Player/".$playerName.".yml", Config::YAML);
            $playerConfig->setNested("JoinDate", date("d.m.Y H:i:s"));
            $playerConfig->setNested("BlockBreak.Coal", 0);
            $playerConfig->setNested("BlockBreak.Iron", 0);
            $playerConfig->setNested("BlockBreak.Gold", 0);
            $playerConfig->setNested("BlockBreak.Redstone", 0);
            $playerConfig->setNested("BlockBreak.Lapis", 0);
            $playerConfig->setNested("BlockBreak.Emerald", 0);
            $playerConfig->setNested("BlockBreak.Diamond", 0);
            $playerConfig->setNested("Banned.Art", "None");
            $playerConfig->setNested("Message.1", $this->getMessage("SystemMessage"));
            $playerConfig->setNested("Reports.Count", 0);
            $playerConfig->save();

            //todo nachrichten werden abgesucht nach verbotenen wörtern und gibt einen hinweis (report system) desto mehr, je weiter oben in der liste
        }
    }

    public function chat(PlayerChatEvent $ev){
        $playerConfig = new Config($this->getDataFolder()."Player/".$ev->getPlayer()->getName().".yml", Config::YAML);

        if($this->isBlocked($ev->getPlayer()->getName()) == true){
            if($ev->getPlayer() instanceof Player){
                $ev->getPlayer()->sendMessage($this->getMessage("Table_17.PlayerMute")."\n> ".$this->getBannedTime($ev->getPlayer()->getName()));
                $ev->cancel();
            }
        }

        $allMessage = $playerConfig->getAll()["Message"];
        foreach($allMessage as $count => $message){
            if($count <= $this->options->getNested("Options.RegisterMessages") - 1){
                $allMessage[$count + 1] = $message;
            }
        }
        $allMessage[1] = $ev->getMessage();
        $playerConfig->set("Message", $allMessage);
        $playerConfig->save();

        $allServerMessage = $this->message->getAll()["Message"];
        for($i = 1; $i <= $this->message->getNested("Message.Count"); $i++){
            if($i <= $this->options->getNested("Options.RegisterMessages") - 1){
                $allServerMessage[$i + 1]["Player"] = $this->message->getNested("Message.".$i.".Player");
                $allServerMessage[$i + 1]["Text"] = $this->message->getNested("Message.".$i.".Text");
            }
        }
        if($this->message->getNested("Message.Count") !== $this->options->getNested("Options.RegisterMessages")){
            $allServerMessage["Count"] = $this->message->getNested("Message.Count") + 1;
        }
        $allServerMessage[1]["Player"] = $ev->getPlayer()->getName();
        $allServerMessage[1]["Text"] = $ev->getMessage();
        $this->message->set("Message", $allServerMessage);
        $this->message->save();
    }

    public function isBlocked(string $profilePlayer) : bool{
        $playerConfig = new Config($this->getDataFolder()."Player/".$profilePlayer.".yml", Config::YAML);
        $art = $playerConfig->getNested("Banned.Art");
        if($art == "PermaBan"){
            return true;
        }
        if($art == "Ban" or $art == "Mute"){
            if($this->isBannedTimeOver($profilePlayer, "Y") == "true"){
                if($this->isBannedTimeOver($profilePlayer, "m") == "true"){
                    if($this->isBannedTimeOver($profilePlayer, "d") == "true"){
                        if($this->isBannedTimeOver($profilePlayer, "H") == "true"){
                            if($this->isBannedTimeOver($profilePlayer, "i") == "true"){
                                $playerConfig->setNested("Banned.Art", "None");
                                $playerConfig->save();
                                return false;
                            }else{
                                if($this->isBannedTimeOver($profilePlayer, "i") == "finish"){
                                    return false;
                                }
                            }
                        }else{
                            if($this->isBannedTimeOver($profilePlayer, "H") == "finish"){
                                return false;
                            }
                        }
                    }else{
                        if($this->isBannedTimeOver($profilePlayer, "d") == "finish"){
                            return false;
                        }
                    }
                }else{
                    if($this->isBannedTimeOver($profilePlayer, "m") == "finish"){
                        return false;
                    }
                }
            }else{
                if($this->isBannedTimeOver($profilePlayer, "Y") == "finish"){
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    public function isBannedTimeOver(string $profilePlayer, string $time) : string{
        $playerConfig = new Config($this->getDataFolder()."Player/".$profilePlayer.".yml", Config::YAML);

        if(date($time) >= $this->getBannedTime($profilePlayer, $time)){
            if(date($time) > $this->getBannedTime($profilePlayer, $time)){
                $playerConfig->setNested("Banned.Art", "None");
                $playerConfig->save();
                return "finish";
            }else{
                return "true";
            }
        }else{
            return "false";
        }
    }

    public function isIgnoreWorld(World $world) : bool{
        for($i = 1; $i <= count($this->options->getAll("IgnoreWorldFromEvents")); $i++){
            if($world->getFolderName() == $this->options->getNested("IgnoreWorldFromEvents.".$i)){
                return true;
            }
        }
        return false;
    }

    public function break(BlockBreakEvent $ev){
        $playerConfig = new Config($this->getDataFolder()."Player/".$ev->getPlayer()->getName().".yml", Config::YAML);
        if($this->isIgnoreWorld($ev->getBlock()->getPosition()->getWorld()) == false){
            $blockId = $ev->getBlock()->getId();

            if($blockId == ItemIds::COAL_ORE){
                $playerConfig->setNested("BlockBreak.Coal", $playerConfig->getNested("BlockBreak.Coal") + 1);
                $playerConfig->save();
            }

            if($blockId == ItemIds::IRON_ORE){
                $playerConfig->setNested("BlockBreak.Iron", $playerConfig->getNested("BlockBreak.Iron") + 1);
                $playerConfig->save();
            }

            if($blockId == ItemIds::GOLD_ORE){
                $playerConfig->setNested("BlockBreak.Gold", $playerConfig->getNested("BlockBreak.Gold") + 1);
                $playerConfig->save();
            }

            if($blockId == ItemIds::REDSTONE_ORE or $blockId == ItemIds::GLOWING_REDSTONE_ORE){
                $playerConfig->setNested("BlockBreak.Redstone", $playerConfig->getNested("BlockBreak.Redstone") + 1);
                $playerConfig->save();
            }

            if($blockId == ItemIds::LAPIS_ORE){
                $playerConfig->setNested("BlockBreak.Lapis", $playerConfig->getNested("BlockBreak.Lapis") + 1);
                $playerConfig->save();
            }

            if($blockId == ItemIds::EMERALD_ORE){
                $playerConfig->setNested("BlockBreak.Emerald", $playerConfig->getNested("BlockBreak.Emerald") + 1);
                $playerConfig->save();
            }

            if($blockId == ItemIds::DIAMOND_ORE){
                $playerConfig->setNested("BlockBreak.Diamond", $playerConfig->getNested("BlockBreak.Diamond") + 1);
                $playerConfig->save();
            }
        }
    }

    public function getPlayerIdByName(string $playerName) : int{
        $count = $this->playerList->getNested("PlayerList.Count");
        if($count !== 0){
            for($i = 1; $i <= $count; $i++){
                if($this->playerList->getNested("PlayerList.".$i) == $playerName){
                    return $i;
                }
            }
            return 0;
        }else{
            return 0;
        }
    }

    public function onCommand(CommandSender $p, Command $cmd, string $label, array $args) : bool{
        $cmdname = $cmd->getName();
        $pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
        $rank = $pp->getUserDataMgr()->getGroup($p)->getName();
        $id = $this->getIdByRank($rank);

        if($p instanceof Player){
            if($cmdname == "playerlist"){
                $this->PlayerList($p);
            }
            if($cmdname == "report"){
                $this->PlayerList($p);
            }
            if($cmdname == "reportlist"){
                if($id !== 0 or $this->getServer()->isOp($p->getName())){
                    if($this->options->getNested("Ranks.".$this->getIdByRank($rank).".EditReports") == "true" or $this->getServer()->isOp($p->getName())){
                        $this->ReportList($p);
                    }
                }
            }
            if($cmdname == "banlistui"){
                if($id !== 0 or $this->getServer()->isOp($p->getName())){
                    if($this->options->getNested("Ranks.".$this->getIdByRank($rank).".EditReports") == "true" or $this->getServer()->isOp($p->getName())){
                        $this->BanList($p);
                    }
                }
            }
        }
    return true;
    }

    public function getLogo() : string{
        return $this->options->getNested("Message.Logo");
    }

    public function getMessage(string $message) : string{
        return $this->options->getNested("Message.".$message);
    }

    public function BanList(Player $p){
        $this->listPlayer = [];
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $p, int $data = null){
            $result = $data;
            if($result === null){
                return true;
            }

            if($result !== 0){
                $this->PlayerProfile($p, $this->listPlayer[$result]);
            }

            return true;
        });

        $form->setTitle($this->getLogo()." ".$this->getMessage("Table_19.Title"));
        $form->setContent($this->getMessage("Table_19.Content"));
        $form->addButton($this->getMessage("Table_19.Close"));

        $counter = 1;
        foreach($this->playerList->getAll()["PlayerList"] as $id => $player){
            if($id !== "Count"){
                $playerConfig = new Config($this->getDataFolder()."Player/".$player.".yml", Config::YAML);
                if($playerConfig->getNested("Banned.Art") == "PermaBan"){
                    $form->addButton("[".$counter."] [PermaBan] - ".$player);
                    $this->listPlayer[$counter] = $player;
                    $counter++;
                }
                if($playerConfig->getNested("Banned.Art") == "Ban"){
                    $form->addButton("[".$counter."] [Ban] - ".$player);
                    $this->listPlayer[$counter] = $player;
                    $counter++;
                }
                if($playerConfig->getNested("Banned.Art") == "Mute"){
                    $form->addButton("[".$counter."] [Mute] - ".$player);
                    $this->listPlayer[$counter] = $player;
                    $counter++;
                }
            }
        }

        $form->sendToPlayer($p);

        return $form;
    }

    public function PlayerList(Player $p){
        $this->listPlayer = [];
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $p, int $data = null){
            $result = $data;
            if($result === null){
                return true;
            }

            if($result == 1){
                $this->SearchPlayer($p);
            }
            if($result == 2){
                $this->MyProfile($p);
            }
            if($result !== 0 and $result !== 1 and $result !== 2){
                $this->PlayerProfile($p, $this->listPlayer[$result]);
            }

        return true;
        });

        $form->setTitle($this->getLogo()." ".$this->getMessage("Table_1.Title"));
        $form->setContent($this->getMessage("Table_1.Content"));
        $form->addButton($this->getMessage("Table_1.Close"));
        $form->addButton($this->getMessage("Table_1.Search"));
        $form->addButton($this->getMessage("Table_1.MyProfile"));

        $counter = 1;
        foreach($this->playerList->getAll()["PlayerList"] as $id => $player){
            if($id !== "Count"){
                if($this->options->getNested("Options.SeeOwnProfile") == "false"){
                    if($player !== $p->getName()){
                        $form->addButton("[".$counter."] - ".$player);
                        $this->listPlayer[$counter + 2] = $player;
                        $counter++;
                    }
                }else{
                    $form->addButton("[".$counter."] - ".$player);
                    $this->listPlayer[$counter + 2] = $player;
                    $counter++;
                }
            }
        }

        $form->sendToPlayer($p);

        return $form;
    }

    public function SearchPlayer(Player $p){
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $p, int $data = null){
            $result = $data;
            if($result === null){
                return true;
            }

            if($result == 0){
                $this->SearchPlayerChat($p);
            }
            if($result == 1){
                $this->SearchPlayerName($p);
            }
            if($result == 2){
                $this->SearchPlayerId($p);
            }
            if($result == 3){
                $this->SearchPlayerWorld($p);
            }
            if($result == 4){
                $this->SearchPlayerArea($p);
            }

            return true;
        });

        $form->setTitle($this->getLogo()." ".$this->getMessage("Table_10.Title"));
        $form->setContent($this->getMessage("Table_10.Content"));
        $form->addButton($this->getMessage("Table_10.PlayerChat"));
        $form->addButton($this->getMessage("Table_10.PlayerName"));
        $form->addButton($this->getMessage("Table_10.PlayerId"));
        $form->addButton($this->getMessage("Table_10.InWorld"));
        $form->addButton($this->getMessage("Table_10.InArea"));
        $form->addButton($this->getMessage("Table_10.Close"));

        $form->sendToPlayer($p);

        return $form;
    }

    public function SearchPlayerChat(Player $p){
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createCustomForm(function (Player $p, array $data = null){
            if($data === null){
                return true;
            }

            $player = $this->message->getNested("Message.".$data[1].".Player");
            if($player !== "Server"){
                if($this->options->getNested("Options.SeeOwnProfile") == "false"){
                    if($p->getName() !== $player){
                        $this->PlayerProfile($p, $player);
                    }
                }else{
                    $this->PlayerProfile($p, $player);
                }
            }

            return true;
        });
        $form->setTitle($this->getMessage("Table_11.Title"));

        $allMessages = $this->getMessage("Table_11.Content")."\n\n§l".$this->getMessage("Table_11.LastMessages")."§r";
        for($i = 1; $i <= $this->message->getNested("Message.Count"); $i++){
            $allMessages .= "\n[".$i."] [".$this->message->getNested("Message.".$i.".Player")."] > ".$this->message->getNested("Message.".$i.".Text");
        }

        $form->addLabel($allMessages);
        $form->addSlider($this->getMessage("Table_11.ChooseMessage"), 1, $this->message->getNested("Message.Count"));

        $form->sendToPlayer($p);
        return $form;
    }

    public function SearchPlayerName(Player $p){
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createCustomForm(function (Player $p, array $data = null){
            if($data === null){
                return true;
            }

            if(isset($data[1]) and $this->getPlayerIdByName($data[1]) !== 0){
                if($this->options->getNested("Options.SeeOwnProfile") == "false"){
                    if($p->getName() !== $data[1]){
                        $this->PlayerProfile($p, $data[1]);
                    }
                }else{
                    $this->PlayerProfile($p, $data[1]);
                }
            }else{
                $p->sendMessage($this->getLogo()." ".$this->getMessage("FailSearchPlayer"));
            }

            return true;
        });
        $form->setTitle($this->getMessage("Table_12.Title"));
        $form->addLabel($this->getMessage("Table_12.Content"));
        $form->addInput(' ', $this->getMessage("Table_10.PlayerName"));

        $form->sendToPlayer($p);
        return $form;
    }

    public function SearchPlayerId(Player $p){
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createCustomForm(function (Player $p, array $data = null){
            if($data === null){
                return true;
            }

            if(is_numeric($data[1]) and $data[1] <= $this->playerList->getNested("PlayerList.Count")){
                $player = $this->playerList->getNested("PlayerList.".$data[1]);
                if($this->options->getNested("Options.SeeOwnProfile") == "false"){
                    if($p->getName() !== $player){
                        $this->PlayerProfile($p, $player);
                    }
                }else{
                    $this->PlayerProfile($p, $player);
                }
            }else{
                $p->sendMessage($this->getLogo()." ".$this->getMessage("FailSearchPlayer"));
            }

            return true;
        });
        $form->setTitle($this->getMessage("Table_13.Title"));
        $form->addLabel($this->getMessage("Table_13.Content"));
        $form->addInput(' ', $this->getMessage("Table_10.PlayerId"));

        $form->sendToPlayer($p);
        return $form;
    }

    public function SearchPlayerWorld(Player $p){
        $this->searchPlayer = [];
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $p, int $data = null){
            $result = $data;
            if($result === null){
                return true;
            }

            if($result == 0){
                $this->SearchPlayer($p);
            }
            if($result !== 0){
                $this->PlayerProfile($p, $this->searchPlayer[$result]);
            }

            return true;
        });

        $form->setTitle($this->getLogo()." ".$this->getMessage("Table_14.Title"));
        $form->setContent($this->getMessage("Table_14.Content"));
        $form->addButton($this->getMessage("Table_14.Close"));

        $counter = 1;
        foreach($p->getWorld()->getPlayers() as $player){
            if($this->options->getNested("Options.SeeOwnProfile") == "false"){
                if($player->getName() !== $p->getName()){
                    $form->addButton("[".$counter."] ".$player->getName());
                    $this->searchPlayer[$counter] = $player->getName();
                    $counter++;
                }
            }else{
                $form->addButton("[".$counter."] ".$player->getName());
                $this->searchPlayer[$counter] = $player->getName();
                $counter++;
            }
        }

        $form->sendToPlayer($p);

        return $form;
    }

    public function SearchPlayerArea(Player $p){
        $this->searchPlayer = [];
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $p, int $data = null){
            $result = $data;
            if($result === null){
                return true;
            }

            if($result == 0){
                $this->SearchPlayer($p);
            }
            if($result !== 0){
                $this->PlayerProfile($p, $this->searchPlayer[$result]);
            }

            return true;
        });

        $form->setTitle($this->getLogo()." ".$this->getMessage("Table_15.Title"));
        $form->setContent($this->getMessage("Table_15.Content"));
        $form->addButton($this->getMessage("Table_15.Close"));

        $counter = 1;
        foreach($p->getWorld()->getPlayers() as $player){
            if($this->contains($p->getPosition(), $player->getPosition()) == true){
                if($this->options->getNested("Options.SeeOwnProfile") == "false"){
                    if($player->getName() !== $p->getName()){
                        $form->addButton("[".$counter."] ".$player->getName());
                        $this->searchPlayer[$counter] = $player->getName();
                        $counter++;
                    }
                }else{
                    $form->addButton("[".$counter."] ".$player->getName());
                    $this->searchPlayer[$counter] = $player->getName();
                    $counter++;
                }
            }
        }

        $form->sendToPlayer($p);

        return $form;
    }

    public function contains(Position $pos1, Position $pos2) : bool{
        $radius = $this->options->getNested("Options.SearchPlayerRadius");
        $Min_X = $pos1->getX() - $radius;
        $Max_X = $pos1->getX() + $radius;
        $Min_Y = $pos1->getY() - $radius;
        $Max_Y = $pos1->getY() + $radius;
        $Min_Z = $pos1->getZ() - $radius;
        $Max_Z = $pos1->getZ() + $radius;
        if(min($Min_X, $Max_X) <= $pos2->getX() and max($Min_X, $Max_X) >= $pos2->getX() and min($Min_Y, $Max_Y) <= $pos2->getY() and max($Min_Y, $Max_Y) >= $pos2->getY() and min($Min_Z, $Max_Z) <= $pos2->getZ() and max($Min_Z, $Max_Z) >= $pos2->getZ()){
            return true;
        }else{
            return false;
        }
    }

    public function PlayerMute(Player $p, string $profilePlayer){
        $this->profilePlayer = $profilePlayer;
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createCustomForm(function (Player $p, array $data = null){
            if($data === null){
                return true;
            }

            $playerConfig = new Config($this->getDataFolder()."Player/".$this->profilePlayer.".yml", Config::YAML);
            $player = $this->getServer()->getPlayerByPrefix($this->profilePlayer);

            $playerConfig->setNested("Banned.Art", "Mute");
            $playerConfig->save();
            $this->setBannedConfig($this->profilePlayer, $data[1], $data[2], $data[3], $data[4], $data[5]);
            if($player instanceof Player){
                $player->sendMessage($this->getMessage("Table_17.PlayerMute")."\n> ".$this->getBannedTime($this->profilePlayer)."\n\n".$this->getMessage("Table_16.Reason")."\n> ".$data[6]);
            }

            return true;
        });
        $form->setTitle($this->getMessage("Table_17.Title"));
        $form->addLabel($this->getMessage("Table_17.Content"));

        $form->addSlider($this->getMessage("Table_16.Minutes"), 0, 59);
        $form->addSlider($this->getMessage("Table_16.Hours"), 0, 23);
        $form->addSlider($this->getMessage("Table_16.Days"), 0, 30);
        $form->addSlider($this->getMessage("Table_16.Months"), 0, 11);
        $form->addSlider($this->getMessage("Table_16.Years"), 0, 10);
        $form->addInput(' ', $this->getMessage("Table_16.Reason"));

        $form->sendToPlayer($p);
        return $form;
    }

    public function PlayerKick(Player $p, string $profilePlayer){
        $this->profilePlayer = $profilePlayer;
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createCustomForm(function (Player $p, array $data = null){
            if($data === null){
                return true;
            }

            $player = $this->getServer()->getPlayerByPrefix($this->profilePlayer);
            if($player instanceof Player){
                $player->kick($this->getMessage("Table_18.PlayerKick")."\n> ".$this->getBannedTime($this->profilePlayer)."\n\n".$this->getMessage("Table_16.Reason")."\n> ".$data[1]);
            }

            return true;
        });
        $form->setTitle($this->getMessage("Table_18.Title"));
        $form->addLabel($this->getMessage("Table_18.Content"));

        $form->addInput(' ', $this->getMessage("Table_16.Reason"));

        $form->sendToPlayer($p);
        return $form;
    }

    public function PlayerBan(Player $p, string $profilePlayer){
        $this->profilePlayer = $profilePlayer;
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createCustomForm(function (Player $p, array $data = null){
            if($data === null){
                return true;
            }

            $playerConfig = new Config($this->getDataFolder()."Player/".$this->profilePlayer.".yml", Config::YAML);
            $player = $this->getServer()->getPlayerByPrefix($this->profilePlayer);
            $pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
            $rank = $pp->getUserDataMgr()->getGroup($p)->getName();

            if($this->options->getNested("Ranks.".$this->getIdByRank($rank).".PermaBan") == "true" or $this->getServer()->isOp($p->getName())){
                if($data[1] == true){
                    $playerConfig->setNested("Banned.Art", "PermaBan");
                    $playerConfig->save();
                    if($player instanceof Player){
                        $player->kick($this->getMessage("Table_16.PlayerPermaBan")."\n\n".$this->getMessage("Table_16.Reason")."\n> ".$data[7]);
                    }
                }else{
                    $playerConfig->setNested("Banned.Art", "Ban");
                    $playerConfig->save();
                    $this->setBannedConfig($this->profilePlayer, $data[2], $data[3], $data[4], $data[5], $data[6]);
                    if($player instanceof Player){
                        $player->kick($this->getMessage("Table_16.PlayerTimeBan")."\n> ".$this->getBannedTime($this->profilePlayer)."\n\n".$this->getMessage("Table_16.Reason")."\n> ".$data[7]);
                    }
                }
            }else{
                $playerConfig->setNested("Banned.Art", "Ban");
                $playerConfig->save();
                $this->setBannedConfig($this->profilePlayer, $data[1], $data[2], $data[3], $data[4], $data[5]);
                if($player instanceof Player){
                    $player->kick($this->getMessage("Table_16.PlayerTimeBan")."\n> ".$this->getBannedTime($this->profilePlayer)."\n\n".$this->getMessage("Table_16.Reason")."\n> ".$data[6]);
                }
            }

            return true;
        });
        $pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
        $rank = $pp->getUserDataMgr()->getGroup($p)->getName();

        $form->setTitle($this->getMessage("Table_16.Title"));
        $form->addLabel($this->getMessage("Table_16.Content"));

        if($this->options->getNested("Ranks.".$this->getIdByRank($rank).".PermaBan") == "true" or $this->getServer()->isOp($p->getName())){
            $form->addToggle($this->getMessage("Table_16.PermaBan"), false);
        }
        $form->addSlider($this->getMessage("Table_16.Minutes"), 0, 59);
        $form->addSlider($this->getMessage("Table_16.Hours"), 0, 23);
        $form->addSlider($this->getMessage("Table_16.Days"), 0, 30);
        $form->addSlider($this->getMessage("Table_16.Months"), 0, 11);
        $form->addSlider($this->getMessage("Table_16.Years"), 0, 10);
        $form->addInput(' ', $this->getMessage("Table_16.Reason"));

        $form->sendToPlayer($p);
        return $form;
    }

    public function getBannedTime(string $profilePlayer, string $time = "all") : int | string{
        $playerConfig = new Config($this->getDataFolder()."Player/".$profilePlayer.".yml", Config::YAML);
        $minute = $playerConfig->getNested("Banned.Minute");
        $hour = $playerConfig->getNested("Banned.Hour");
        $day = $playerConfig->getNested("Banned.Day");
        $month = $playerConfig->getNested("Banned.Month");
        $year = $playerConfig->getNested("Banned.Year");

        if($time == "all"){
            return $day.".".$month.".".$year." ".$hour.":".$minute." Uhr";
        }
        if($time == "i"){
            return $minute;
        }
        if($time == "H"){
            return $hour;
        }
        if($time == "d"){
            return $day;
        }
        if($time == "m"){
            return $month;
        }
        if($time == "Y"){
            return $year;
        }
        return $day.".".$month.".".$year." ".$hour.":".$minute." Uhr";
    }

    public function setBannedConfig(string $profilePlayer, int $minute, int $hour, int $day, int $month, int $year){
        $playerConfig = new Config($this->getDataFolder()."Player/".$profilePlayer.".yml", Config::YAML);
        $playerConfig->setNested("Banned.Minute", date("i", mktime(date("H") + $hour, date("i") + $minute, 0, date("m") + $month, date("d") + $day, date("Y") + $year)));
        $playerConfig->setNested("Banned.Hour", date("H", mktime(date("H") + $hour, date("i") + $minute, 0, date("m") + $month, date("d") + $day, date("Y") + $year)));
        $playerConfig->setNested("Banned.Day", date("d", mktime(date("H") + $hour, date("i") + $minute, 0, date("m") + $month, date("d") + $day, date("Y") + $year)));
        $playerConfig->setNested("Banned.Month", date("m", mktime(date("H") + $hour, date("i") + $minute, 0, date("m") + $month, date("d") + $day, date("Y") + $year)));
        $playerConfig->setNested("Banned.Year", date("Y", mktime(date("H") + $hour, date("i") + $minute, 0, date("m") + $month, date("d") + $day, date("Y") + $year)));
        $playerConfig->save();
    }

    public function getIdByRank(string $rang) : int{
        for($i = 1; $i <= count($this->options->getNested("Ranks")); $i++){
            if($this->options->getNested("Ranks.".$i.".RankName") == $rang){
                return $i;
            }
        }
        return 0;
    }

    public function PlayerProfile(Player $p, string $profilePlayer){
        $this->profilePlayer = $profilePlayer;
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $p, int $data = null){
            $result = $data;
            if($result === null){
                return true;
            }

            if($result == 0){
                $this->InfoPlayer($p, $this->profilePlayer);
            }
            if($result == 1){
                $this->ReportPlayer($p, $this->profilePlayer);
            }
            if($result == 2){
                $this->MessageHistory($p, $this->profilePlayer);
            }
            if($result == 3){
                $this->PlayerList($p);
            }
            $pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
            $rank = $pp->getUserDataMgr()->getGroup($p)->getName();
            $id = $this->getIdByRank($rank);
            if($id !== 0 or $this->getServer()->isOp($p->getName())){
                if($result == 3){
                    if($this->options->getNested("Ranks.".$id.".Mute") == "true" or $this->getServer()->isOp($p->getName())){
                        $this->PlayerMute($p, $this->profilePlayer);
                    }
                }
                if($result == 4){
                    if($this->options->getNested("Ranks.".$id.".Kick") == "true" or $this->getServer()->isOp($p->getName())){
                        $this->PlayerKick($p, $this->profilePlayer);
                    }
                }
                if($result == 5){
                    if($this->options->getNested("Ranks.".$id.".Kick") == "true" or $this->getServer()->isOp($p->getName())){
                        $this->PlayerBan($p, $this->profilePlayer);
                    }
                }
                if($result == 6){
                    $this->PlayerList($p);
                }
            }

            return true;
        });
        $pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
        $rank = $pp->getUserDataMgr()->getGroup($p)->getName();

        $form->setTitle($this->getLogo()." ".$this->getMessage("Table_2.Title")." ".$profilePlayer);
        $form->setContent($this->getMessage("Table_2.Content"));
        $form->addButton($this->getMessage("Table_2.Info"));
        $form->addButton($this->getMessage("Table_2.Report"));
        $form->addButton($this->getMessage("Table_2.MassageHistory"));

        $id = $this->getIdByRank($rank);
        if($id !== 0 or $this->getServer()->isOp($p->getName())){
            if($this->options->getNested("Ranks.".$id.".Mute") == "true" or $this->getServer()->isOp($p->getName())){
                $form->addButton($this->getMessage("Table_2.Mute"));
            }
            if($this->options->getNested("Ranks.".$id.".Kick") == "true" or $this->getServer()->isOp($p->getName())){
                $form->addButton($this->getMessage("Table_2.Kick"));
            }
            if($this->options->getNested("Ranks.".$id.".Ban") == "true" or $this->getServer()->isOp($p->getName())){
                $form->addButton($this->getMessage("Table_2.Ban"));
            }
        }

        $form->addButton($this->getMessage("Table_2.Close"));
        $form->sendToPlayer($p);

        return $form;
    }

    public function MyProfile(Player $p){
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $p, int $data = null){
            $result = $data;
            if($result === null){
                return true;
            }

            if($result == 0){
                $this->PlayerList($p);
            }

            return true;
        });
        $playerConfig = new Config($this->getDataFolder()."Player/".$p->getName().".yml", Config::YAML);

        $form->setTitle($this->getLogo()." ".$this->getMessage("Table_3.Title")." ".$p->getName());
        $form->setContent("§l".$this->getMessage("Table_3.JoinDate")."§r\n".$playerConfig->getNested("JoinDate")."\n\n§l".$this->getMessage("Table_3.PlayerId")."§r ".$this->getPlayerIdByName($p->getName())."\n\n§l".$this->getMessage("Table_3.OreBreak")."§r\n".$this->getMessage("Table_3.Coal").": ".$playerConfig->getNested("BlockBreak.Coal")."\n".$this->getMessage("Table_3.Iron").": ".$playerConfig->getNested("BlockBreak.Gold")."\n".$this->getMessage("Table_3.Redstone").": ".$playerConfig->getNested("BlockBreak.Redstone")."\n".$this->getMessage("Table_3.Lapis").": ".$playerConfig->getNested("BlockBreak.Lapis")."\n".$this->getMessage("Table_3.Emerald").": ".$playerConfig->getNested("BlockBreak.Emerald")."\n".$this->getMessage("Table_3.Diamond").": ".$playerConfig->getNested("BlockBreak.Diamond"));
        $form->addButton($this->getMessage("Table_3.Close"));
        $form->sendToPlayer($p);

        return $form;
    }

    public function InfoPlayer(Player $p, string $profilePlayer){
        $this->profilePlayer = $profilePlayer;
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $p, int $data = null){
            $result = $data;
            if($result === null){
                return true;
            }

            if($result == 0){
                $this->PlayerProfile($p, $this->profilePlayer);
            }

            return true;
        });
        $playerConfig = new Config($this->getDataFolder()."Player/".$profilePlayer.".yml", Config::YAML);

        $form->setTitle($this->getLogo()." ".$this->getMessage("Table_3.Title")." ".$profilePlayer);
        $form->setContent("§l".$this->getMessage("Table_3.JoinDate")."§r\n".$playerConfig->getNested("JoinDate")."\n\n§l".$this->getMessage("Table_3.PlayerId")."§r ".$this->getPlayerIdByName($profilePlayer)."\n\n§l".$this->getMessage("Table_3.OreBreak")."§r\n".$this->getMessage("Table_3.Coal").": ".$playerConfig->getNested("BlockBreak.Coal")."\n".$this->getMessage("Table_3.Iron").": ".$playerConfig->getNested("BlockBreak.Gold")."\n".$this->getMessage("Table_3.Redstone").": ".$playerConfig->getNested("BlockBreak.Redstone")."\n".$this->getMessage("Table_3.Lapis").": ".$playerConfig->getNested("BlockBreak.Lapis")."\n".$this->getMessage("Table_3.Emerald").": ".$playerConfig->getNested("BlockBreak.Emerald")."\n".$this->getMessage("Table_3.Diamond").": ".$playerConfig->getNested("BlockBreak.Diamond"));
        $form->addButton($this->getMessage("Table_3.Close"));
        $form->sendToPlayer($p);

        return $form;
    }

    public function getStringFromId(int $id) : string{
        $strings = [1 => $this->getMessage("Table_4.Hacking"), 2 => $this->getMessage("Table_4.Advertising"), 3 => $this->getMessage("Table_4.Insult"), 4 => $this->getMessage("Table_4.Spamming"), 5 => $this->getMessage("Table_4.Griefing"), 6 => $this->getMessage("Table_4.BugAbusing"), 7 => $this->getMessage("Table_4.WrongIdentity")];
        return $strings[$id];
    }

    public function ReportPlayer(Player $p, string $profilePlayer){
        $this->profilePlayer = $profilePlayer;
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createCustomForm(function (Player $p, array $data = null){
            if($data === null){
                return true;
            }

            $reportText = "§l".$this->getMessage("Table_4.ReportFrom")." ".$p->getName()."§r\n";
            $reportText .= $this->getMessage("Table_4.CreatedAt").": ".date("d.m.Y H:i:s")."\n\n";
            $reportText .= "§l".$this->getMessage("Table_4.ReasonReport")."§r\n";
            for($i = 1; $i <= 7; $i++){
                if($data[$i] == true){
                    $reportText .= "> ".$this->getStringFromId($i)."\n";
                }
            }
            if(isset($data[8])){
                $reportText .= "\n\n".$this->getMessage("Table_4.ExtraInfo")."\n> ".$data[8];
            }

            $playerConfig = new Config($this->getDataFolder()."Player/".$this->profilePlayer.".yml", Config::YAML);
            $number = $playerConfig->getNested("Reports.Count") + 1;
            $playerConfig->setNested("Reports.".$number.".Finished", "false");
            $playerConfig->setNested("Reports.".$number.".ReportFrom", $p->getName());
            $playerConfig->setNested("Reports.".$number.".ReportText", $reportText);
            $playerConfig->setNested("Reports.Count", $number);
            $playerConfig->save();

            $p->sendMessage($this->getLogo()." ".$this->getMessage("Reported"));
            foreach($this->getServer()->getOnlinePlayers() as $player){
                $pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
                $rank = $pp->getUserDataMgr()->getGroup($player)->getName();
                $Id = $this->getIdByRank($rank);
                if($Id !== 0 and $this->options->getNested("Ranks.".$Id.".EditReports") == "true"){
                    $p->sendMessage($this->getMessage("ReportInfo"));
                }
                if($this->getServer()->isOp($player->getName())){
                    $p->sendMessage($this->getMessage("ReportInfo"));
                }
            }

        return true;
        });
        $form->setTitle($this->getLogo()." ".$this->getMessage("Table_4.Title")." ".$profilePlayer);
        $form->addLabel($this->getMessage("Table_4.Content"));
        $form->addToggle($this->getStringFromId(1), false);
        $form->addToggle($this->getStringFromId(2), false);
        $form->addToggle($this->getStringFromId(3), false);
        $form->addToggle($this->getStringFromId(4), false);
        $form->addToggle($this->getStringFromId(5), false);
        $form->addToggle($this->getStringFromId(6), false);
        $form->addToggle($this->getStringFromId(7), false);
        $form->addInput(' ', $this->getMessage("Table_4.ExtraInfo"));

        $form->sendToPlayer($p);
        return $form;
    }

    public function MessageHistory(Player $p, string $profilePlayer){
        $this->profilePlayer = $profilePlayer;
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $p, int $data = null){
            $result = $data;
            if($result === null){
                return true;
            }

            if($result == 0){
                $this->PlayerProfile($p, $this->profilePlayer);
            }

            return true;
        });
        $form->setTitle($this->getLogo()." ".$this->getMessage("Table_9.Title"));

        $playerConfig = new Config($this->getDataFolder()."Player/".$profilePlayer.".yml", Config::YAML);
        $text = "§l".$this->getMessage("Table_2.MassageHistory")."§r";
        $Chat = $playerConfig->getAll()["Message"];
        ksort($Chat);
        $allChats = array_slice($Chat, 0 , $this->options->getNested("Options.RegisterMessages"));
        krsort($allChats);
        foreach($allChats as $count => $message){
            $text .= "\n[".($count + 1)."] ".$message;
        }
        $form->setContent($text);

        $form->addButton($this->getMessage("Table_9.Close"));

        $form->sendToPlayer($p);

        return $form;
    }

    public function ReportList(Player $p){
        $this->reportPlayer = [];
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $p, int $data = null){
            $result = $data;
            if($result === null){
                return true;
            }

            if($result !== 0){
                $this->EditReportsFromPlayer($p, $this->reportPlayer[$result]);
            }

            return true;
        });
        $form->setTitle($this->getLogo()." ".$this->getMessage("Table_5.Title"));
        $form->setContent($this->getMessage("Table_5.Content"));
        $form->addButton($this->getMessage("Table_5.Close"));

        $counter = 1;
        for($i = 1; $i <= $this->playerList->getNested("PlayerList.Count"); $i++){
            $profilePlayer = $this->playerList->getNested("PlayerList.".$i);
            $playerConfig = new Config($this->getDataFolder()."Player/".$profilePlayer.".yml", Config::YAML);
            $number = $playerConfig->getNested("Reports.Count");
            if($number !== 0){
                $reportCount = 0;
                for($o = 1; $o <= $number; $o++){
                    if($playerConfig->getNested("Reports.".$o.".Finished") == "false"){
                        $reportCount++;
                    }
                }
                if($reportCount !== 0){
                    $form->addButton("[".$counter."] ".$profilePlayer." - ".$reportCount);
                    $this->reportPlayer[$counter] = $profilePlayer;
                    $counter++;
                }
            }
        }

        $form->sendToPlayer($p);

        return $form;
    }

    public function EditReportsFromPlayer(Player $p, string $profilePlayer){
        $this->profilePlayer = $profilePlayer;
        $this->profileReport = [];
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $p, int $data = null){
            $result = $data;
            if($result === null){
                return true;
            }

            if($result == 0){
                $this->ReportList($p);
            }
            if($result == 1){
                $this->OldReportsFromPlayer($p, $this->profilePlayer);
            }
            if($result !== 0 and $result !== 1){
                $this->OpenReportFromPlayer($p, $this->profilePlayer, $this->profileReport[$result]);
            }

            return true;
        });
        $form->setTitle($this->getLogo()." ".$this->getMessage("Table_6.Title")." ".$profilePlayer);
        $form->setContent($this->getMessage("Table_6.Content"));
        $form->addButton($this->getMessage("Table_6.Close"));

        $playerConfig = new Config($this->getDataFolder()."Player/".$profilePlayer.".yml", Config::YAML);
        $number = $playerConfig->getNested("Reports.Count");
        $count = 2;
        if($number !== 0){
            $reportCount = 0;
            for($i = 1; $i <= $number; $i++){
                if($playerConfig->getNested("Reports.".$i.".Finished") == "true"){
                    $reportCount++;
                }
            }
            $form->addButton($this->getMessage("Table_6.OldReports")." - ".$reportCount);

            for($i = 1; $i <= $number; $i++){
                if($playerConfig->getNested("Reports.".$i.".Finished") == "false"){
                    $form->addButton("[".($count - 1)."] ".$this->getMessage("Table_4.ReportFrom")." ".$playerConfig->getNested("Reports.".$i.".ReportFrom"));
                    $this->profileReport[$count] = $i;
                    $count++;
                }
            }
        }

        $form->sendToPlayer($p);

        return $form;
    }

    public function OldReportsFromPlayer(Player $p, string $profilePlayer){
        $this->profilePlayer = $profilePlayer;
        $this->profileReport = [];
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $p, int $data = null){
            $result = $data;
            if($result === null){
                return true;
            }

            if($result == 0){
                $this->ReportList($p);
            }
            if($result !== 0){
                $this->OpenReportFromPlayer($p, $this->profilePlayer, $this->profileReport[$result]);
            }

            return true;
        });
        $form->setTitle($this->getLogo()." ".$this->getMessage("Table_8.Title")." ".$profilePlayer);
        $form->setContent($this->getMessage("Table_8.Content"));
        $form->addButton($this->getMessage("Table_8.Close"));

        $playerConfig = new Config($this->getDataFolder()."Player/".$profilePlayer.".yml", Config::YAML);
        $number = $playerConfig->getNested("Reports.Count");
        $count = 1;
        if($number !== 0){
            for($i = 1; $i <= $number; $i++){
                if($playerConfig->getNested("Reports.".$i.".Finished") == "true"){
                    $form->addButton("[".$count."] ".$this->getMessage("Table_4.ReportFrom")." ".$playerConfig->getNested("Reports.".$i.".ReportFrom"));
                    $this->profileReport[$count] = $i;
                    $count++;
                }
            }
        }

        $form->sendToPlayer($p);

        return $form;
    }

    public function OpenReportFromPlayer(Player $p, string $profilePlayer, int $profileReport){
        $this->profilePlayer = $profilePlayer;
        $this->profileReportInt = $profileReport;
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $p, int $data = null){
            $result = $data;
            if($result === null){
                return true;
            }

            $playerConfig = new Config($this->getDataFolder()."Player/".$this->profilePlayer.".yml", Config::YAML);
            if($playerConfig->getNested("Reports.".$this->profileReportInt.".Finished") == "false"){
                if($result == 0){
                    $playerConfig->setNested("Reports.".$this->profileReportInt.".Finished", "true");
                    $playerConfig->setNested("Reports.".$this->profileReportInt.".ReportText", $playerConfig->getNested("Reports.".$this->profileReportInt.".ReportText")."\n\n§l".$this->getMessage("Table_7.EditFrom")." ".$p->getName()."§r");
                    $playerConfig->save();
                    $this->OpenReportFromPlayer($p, $this->profilePlayer, $this->profileReportInt);
                }
                if($result == 1){
                    $this->PlayerProfile($p, $this->profilePlayer);
                }
                if($result == 2){
                    $this->EditReportsFromPlayer($p, $this->profilePlayer);
                }
            }else{
                if($result == 0){
                    $this->PlayerProfile($p, $this->profilePlayer);
                }
                if($result == 1){
                    $this->EditReportsFromPlayer($p, $this->profilePlayer);
                }
            }


            return true;
        });
        $playerConfig = new Config($this->getDataFolder()."Player/".$profilePlayer.".yml", Config::YAML);

        $form->setTitle($this->getLogo()." ".$this->getMessage("Table_7.Title")." ".$profilePlayer);
        $form->setContent($playerConfig->getNested("Reports.".$profileReport.".ReportText"));
        if($playerConfig->getNested("Reports.".$profileReport.".Finished") == "false"){
            $form->addButton($this->getMessage("Table_7.CompleteReport"));
        }
        $form->addButton($this->getMessage("Table_7.PlayerProfile"));
        $form->addButton($this->getMessage("Table_7.Close"));

        $form->sendToPlayer($p);

        return $form;
    }
}