<?php

include_once("include\\lookup.php");
include_once("include\\config.php");
include_once("include\\timex.php");

function LocateSaveGamePath(){
	global $config;
	
	static $saveFilePath = null;
	if($saveFilePath === null){
		$saveFilePath = $config["save_path"];
		if(empty($saveFilePath)){
			$localAppData = getenv("LOCALAPPDATA");
			if(empty($localAppData)){
				printf("%s\n", ColorStr("Unable to determine the AppData/Local folder", 255, 128, 128));
				printf("%s\n", ColorStr("Please set the path to OfflineSavegame.sav file in config.txt manually", 255, 128, 128));
				exit(1);
			}
			$saveFilePath = asDir($localAppData) . "IslandsofInsight\\Saved\\SaveGames\\OfflineSavegame.sav";
		}
		if(!is_file($saveFilePath)){
			printf("%s\n", ColorStr("Failed to find OfflineSavegame.sav, tried looking here:", 255, 128, 128));
			printf("%s\n", ColorStr($saveFilePath, 255, 192, 192));
			printf("%s\n", ColorStr("Make sure you've downloaded your offline save data from the game", 255, 128, 128));
			printf("%s\n", ColorStr("If so, please set the path to OfflineSavegame.sav file in config.txt manually", 255, 128, 128));
			exit(1);
		}
		
		printf("%s\n\n", ColorStr("Save file located in " . $saveFilePath, 128, 255, 128));
	}
	return $saveFilePath;
}

function DecodeSaveFile($inputPath, $outputPath){
	$uesavePath = "uesave\\uesave.exe";
	if(!is_file($uesavePath)){
		printf("%s\n", ColorStr("uesave.exe tool appears to be missing", 255, 128, 128));
		printf("%s\n", ColorStr("Try redownloading this program", 255, 128, 128));
		exit(1);
	}

	// Note: attempts to use stuff like passthru(), shell_exec() etc to avoid writing .json to disk failed.
	// Output takes several minutes or more to capture despite my best efforts to speed it up.
	// Ironically, just letting the program dump a ~40 MB file to disk and load it back is almost instant.
	//ob_start(); passthru($uesaveCmd); $uesaveResult = ob_get_contents(); ob_end_clean();
	//$uesaveResult = shell_exec($uesaveCmd);
	
	// Decoding the OfflineSavegame.sav with uesave.exe tool
	printf("%s\n", ColorStr("Attempting to decode the save data...", 160, 160, 160));
	$uesaveCmd = $uesavePath . " to-json < \"" . $inputPath . "\" > \"" . $outputPath . "\"";
	//printf("Executing command: %s\n", $uesaveCmd);
	$uesaveOutput = "";
	$uesaveResult = 0;
	exec($uesaveCmd, $uesaveOutput, $uesaveResult);
	if($uesaveResult != 0 || !is_file($outputPath)){
		printf("%s\n", ColorStr("uesave.exe tool failed to decode OfflineSavegame.sav", 255, 128, 128));
		printf("%s\n", ColorStr("Please report this on the Discord server", 255, 128, 128));
		exit(1);
	}
	printf("%s\n", ColorStr("Save file decoded as " . $outputPath, 128, 255, 128));
	return true;
}

function EncodeSaveFile($inputPath, $outputPath){
	$uesavePath = "uesave\\uesave.exe";
	if(!is_file($uesavePath)){
		printf("%s\n", ColorStr("uesave.exe tool appears to be missing", 255, 128, 128));
		printf("%s\n", ColorStr("Try redownloading this program", 255, 128, 128));
		exit(1);
	}
	
	printf("%s\n", ColorStr("Attempting to encode the save data...", 160, 160, 160));
	$uesaveCmd = $uesavePath . " from-json < \"" . $inputPath . "\" > \"" . $outputPath . "\"";
	printf("Executing command: %s\n", $uesaveCmd);
	$uesaveOutput = "";
	$uesaveResult = 0;
	exec($uesaveCmd, $uesaveOutput, $uesaveResult);
	if($uesaveResult != 0 || !is_file($outputPath)){
		printf("%s\n", ColorStr("uesave.exe tool failed to encode OfflineSavegame.json", 255, 128, 128));
		exit(1);
	}
	printf("%s\n", ColorStr("Save file encoded as " . $outputPath, 128, 255, 128));
	return true;
}

function ParseAllPuzzles($mainNode, &$miscMap_ref){
	$puzzleMap = GetPuzzleMap(true);
	$puzzleCsvData = [];
	if(!isset($mainNode->PuzzleStatuses_0)){
		return $puzzleCsvData;
	}
	//$badSentinels = GetBadSentinels();
	$allPuzzlesNode = $mainNode->PuzzleStatuses_0->{"Array"}->value->Struct->value; // yes this really sucks
	foreach($allPuzzlesNode as $puzzleNode){
		// "BestScore_0": {                    // always 0
		// "LastSolveTimestamp_0": {           // seems to be always 0 in offline mode
		// "LeaderboardTime_0": {              // all-time PBs for florbs / skydrop challenge, mazes, and grids (lol)
		// "MiscStatus_0": {                   // 
		// "PlayerId_0": {                     // always empty
		// "PuzzleId_0": {                     // pid
		// "bOverride_BestScore_0": {          // always false
		// "bOverride_LastSolveTimestamp_0": { // always false
		// "bOverride_LeaderboardTime_0": {    // always false
		// "bOverride_MiscStatus_0": {         // always false
		// "bOverride_Reset_0": {              // always false
		// "bOverride_Unlocks_0": {            // always false
		// "bReset_0": {                       // always false
		// "bSolved_0": {                      // mostly true, can be false, see below
		$pid      = (int)    $puzzleNode->Struct->PuzzleId_0->{"Int"}->value;
		$isSolved = (bool)   $puzzleNode->Struct->bSolved_0->{"Bool"}->value;
		$score    = (int)    $puzzleNode->Struct->BestScore_0->{"Int"}->value;
		$ts       = (int)    $puzzleNode->Struct->LastSolveTimestamp_0->{"Int"}->value;
		$pb       = (float)  $puzzleNode->Struct->LeaderboardTime_0->{"Float"}->value;
		$misc     = (string) $puzzleNode->Struct->MiscStatus_0->{"Str"}->value;
		
		if(!isset($puzzleMap[$pid])){
			//printf("Skipping unknown solved puzzle %d\n", $pid);
			continue;
		}
		$actualPtype = $puzzleMap[$pid]->actualPtype;
		$zoneIndex = $puzzleMap[$pid]->actualZoneIndex;
		
		if(!empty($misc) && !in_array($actualPtype, [ "gyroRing" ])){
			//$misc = str_replace(["\r", "\n", "\t"], "", $misc);
			//$misc = str_replace(".000000", "", $misc);
			$misc = json_encode(json_decode($misc)); // un-shit the internal format
			//printf("%s\n", ColorStr(sprintf("Puzzle %5d %-20s has misc: %s", $pid, $actualPtype, $misc), 255, 255, 0));
			$miscMap_ref[$pid] = $misc;
		}
		
		if(!$isSolved){
			//printf("%s\n", ColorStr(sprintf("Puzzle %5d %-20s unsolved", $pid, $puzzleMap[$pid]->actualPtype), 255, 255, 0));
			// Note: *some* records are indeed unsolved. This seems to correlate with monoliths and quests resetting randomly online.
			// Note: scratch that. This is related to basically all dungeons being initially reset - records exist, but they're not solved.
			continue;
		}
		if($score != 0){
			// This also should't be the case. We don't store this field.
			//printf("%s\n", ColorStr(sprintf("Puzzle %5d %-20s has score %d", $pid, $puzzleMap[$pid]->actualPtype, $score), 255, 255, 0));
		}
		$csvEntry = [
			"pid"   => $pid,
			"ptype" => PuzzlePrettyName($actualPtype),
			"zone"  => ZoneToPrettyNoColor($zoneIndex),
			"tss"   => TimestampToTss($ts),
			"pb"    => $pb,
		];
		//$pidToData[$pid] = $csvEntry;
		$puzzleCsvData[] = $csvEntry;
		
		//if(in_array($pid, $badSentinels)){
		//	printf("%s\n", ColorStr("Discovered previously unseen Sentinel Stones puzzle " . $pid, 255, 128, 128));
		//	printf("%s\n", ColorStr("Your stats may be slightly incorrect. Please report this on Discord!", 255, 128, 128));
		//}
	}
	array_multisort(array_column($puzzleCsvData, "tss"), SORT_ASC, $puzzleCsvData);
	return $puzzleCsvData;
}

function ParseDecodedSaveFile($decodedJsonPath){
	
	$rawSaveDataJson = file_get_contents($decodedJsonPath);
	if(empty($rawSaveDataJson)){
		printf("%s\n", ColorStr("uesave.exe tool decoded OfflineSavegame.sav", 255, 128, 128));
		printf("%s\n", ColorStr("However, file " . $decodedJsonPath . " is missing or unreadable", 255, 128, 128));
		printf("%s\n", ColorStr("Please report this on the Discord server", 255, 128, 128));
		exit(1);
	}
	$saveDataJson = json_decode($rawSaveDataJson);
	if(empty($saveDataJson)){
		printf("%s\n", ColorStr("Failed to parse file " . $decodedJsonPath, 255, 128, 128));
		printf("%s\n", ColorStr("Please report this on the Discord server", 255, 128, 128));
		exit(1);
	}
	
	printf("%s\n", ColorStr("Attempting to parse the decoded save data...", 160, 160, 160));
	
	$mainNode = $saveDataJson->root->properties;
	
	$miscMap = [];
	$puzzleCsvData = ParseAllPuzzles($mainNode, $miscMap);
	printf("%s\n", ColorStr("Solved puzzles data obtained OK", 160, 160, 160));
	
	$normalSolvedPids = array_column($puzzleCsvData, "pid");
	
	$florbPbs = GetFlorbPbs($puzzleCsvData);
	$florbMedalMap = GetFlorbMedalMap($florbPbs);
	$florbMedalCounts = GetFlorbMedalCounts($florbMedalMap);
	
	$glidePbs = GetGlidePbs($puzzleCsvData);
	$glideMedalMap = GetGlideMedalMap($glidePbs);
	
	$skydropChallengePb = GetSkydropChallengeTime($puzzleCsvData);
	//$skydropChallengeMedalTier = GetSkydropChallengeMedalTier($skydropChallengePb);
	//$skydropChallengeMedal = MedalTierToName($skydropChallengeMedalTier);
	$skydropChallengeMedal = GetSkydropChallengeMedalTier($skydropChallengePb);
	
	$playingSince = GetPlayingSince($puzzleCsvData);
	$playTimeMinutes = GetIslandsPlaytime();
	
	$unlockPids = GetUnlocks($mainNode);
	$monolithFragmentPids = GetMonolithFragments($miscMap);
	$allSolvedPids = array_merge($normalSolvedPids, $unlockPids, $monolithFragmentPids);
	
	$quests = GetQuests($mainNode); // boring
	
	$masteryTable = GetMasteryTable($mainNode);
	$playerLevel = GetPlayerLevel($masteryTable);
	
	$sparks = GetSparks($mainNode);
	$buggedMirabilis = GetBuggedMirabilis($mainNode);
	
	$allRemainingArmillaries    = GetAllRemainingArmillaries($allSolvedPids);
	$allSolvedArmillaries       = GetAllSolvedArmillaries($allSolvedPids);
	$templeRemainingArmillaries = GetTempleRemainingArmillaries($allSolvedPids);
	$templeSolvedArmillaries    = GetTempleSolvedArmillaries($allSolvedPids);
	$staticRemainingArmillaries = GetStaticRemainingArmillaries($allSolvedPids);
	$staticSolvedArmillaries    = GetStaticSolvedArmillaries($allSolvedPids);

	$allHubProfile              = GetHubProfile();
	$staticRemainingPids        = GetStaticRemainingPids($allSolvedPids);
	$staticSolvedPids           = GetStaticSolvedPids($allSolvedPids);
	$hubSolvedProfile           = BuildProfile($allSolvedPids);
	$hubRemainingProfile        = SubtractProfiles($allHubProfile, $hubSolvedProfile);
	$hubSolvedPids              = ExtractAllPids($hubSolvedProfile);
	$hubRemainingPids           = ExtractAllPids($hubRemainingProfile);
	
	$remainingClusterMap        = GetClusterRemainingMap($allSolvedPids);
	$solvedClusterMap           = GetClusterSolvedMap($allSolvedPids);
	
	$remainingMysteries         = GetRemainingMysteries($allSolvedPids);
	$solvedMysteries            = GetSolvedMysteries($allSolvedPids);
	
	$cosmetics                  = GetCosmetics($mainNode);
	$hubRewards                 = GetHubRewards($hubSolvedProfile);
	$settings                   = GetSaveFileSettings($mainNode);
	$hasDeluxe                  = HasDeluxe($settings);
	
	// RewardProgressArray_0 contains what you've collected from leveling up on the Mastery tab - boring.
	// RewardProgress_0 contains what you've claimed from the hub track rewards. Not bad, but won't mention un-claimed ones.
	// Additionally we are interested in how many puzzles you have left till the remaining rewards so this becomes useless anyway.
	//$rewardTiers                = GetHubRewardTiers($mainNode);
	
	$saveJson = [
		"puzzleCsvData"              => $puzzleCsvData,              // 
		"allSolvedPids"              => $allSolvedPids,              // 

		"florbPbs"                   => $florbPbs,                   // 
		"florbMedalMap"              => $florbMedalMap,              // 
		"florbMedalCounts"           => $florbMedalCounts,           // 
		
		"glideMedalMap"              => $glideMedalMap,              //
		
		"skydropChallengePb"         => $skydropChallengePb,         // 
		"skydropChallengeMedal"      => $skydropChallengeMedal,      // 
		"playingSince"               => $playingSince,               // 
		"playTimeMinutes"            => $playTimeMinutes,            // 
		
		"playerLevel"                => $playerLevel,                // 
		"masteryTable"               => $masteryTable,               // 
		"sparks"                     => $sparks,                     // 
		"buggedMirabilis"            => $buggedMirabilis,            // 

		"allRemainingArmillaries"    => $allRemainingArmillaries,    // 
		"allSolvedArmillaries"       => $allSolvedArmillaries,       // 
		"templeRemainingArmillaries" => $templeRemainingArmillaries, // 
		"templeSolvedArmillaries"    => $templeSolvedArmillaries,    // 
		"staticRemainingArmillaries" => $staticRemainingArmillaries, // 
		"staticSolvedArmillaries"    => $staticSolvedArmillaries,    // 

		"staticRemainingPids"        => $staticRemainingPids,        // 
		"staticSolvedPids"           => $staticSolvedPids,           // 
		"hubSolvedProfile"           => $hubSolvedProfile,           // 
		"hubRemainingProfile"        => $hubRemainingProfile,        // 
		"hubSolvedPids"              => $hubSolvedPids,              // 
		"hubRemainingPids"           => $hubRemainingPids,           // 

		"remainingClusterMap"        => $remainingClusterMap,        // 
		"solvedClusterMap"           => $solvedClusterMap,           // 

		"remainingMysteries"         => $remainingMysteries,         // 
		"solvedMysteries"            => $solvedMysteries,            // 
		
		"cosmetics"                  => $cosmetics,                  // list of unlocked cosmetics
		"hubRewards"                 => $hubRewards,                 // rewards you've obtained AND how much longer to the remaining ones
		"settings"                   => $settings,                   // mostly character customization, also stuff like "Accepted EULA" or "Crouch Toggle Mode", show ping/fps etc
		"hasDeluxe"                  => $hasDeluxe,                  // deluxe edition flag
		
		"quests"                     => $quests,                     // absolutely uninteresting
	//	"unlockPids"                 => $unlockPids,                 // included in allSolvedPids
	//	"monolithFragmentPids"       => $monolithFragmentPids,       // included in allSolvedPids
	//	"skydropChallengeMedalTier"  => $skydropChallengeMedalTier,  // redundant
	//	"rewardTiers"                => $rewardTiers,                // hub track rewards that you've *claimed*; we track available+claimed rewards instead
	];
	
	ksort($saveJson);
	printf("%s\n", ColorStr("Player stats read OK", 160, 160, 160));
	printf("\n");
	
	return (object)$saveJson;
}

function GetFlorbPbs($puzzleCsvData){
	$florbPbs = [];
	foreach($puzzleCsvData as $entry){
		if($entry["ptype"] == PuzzlePrettyName("racingBallCourse")){
			$florbPbs[$entry["pid"]] = $entry["pb"];
		}
	}
	ksort($florbPbs);
	return $florbPbs;
}

function GetFlorbMedalMap($florbPbs){
	$florbTiers = GetFlorbTiers();
	$tierCount = reset($florbTiers);
	$florbMedalMap = [[],[],[],[]];
	foreach($florbPbs as $pid => $pb){
		if(!isset($florbTiers[$pid])){
			//printf("Skipping unknown florb %d\n", $pid);
			continue;
		}
		$currTiers = $florbTiers[$pid];
		$bestTier = 0;
		while($bestTier < count($currTiers) && $pb < $currTiers[$bestTier] + 1e-3){
			++$bestTier;
		}
		--$bestTier; // -1 for no medal, 0..3 for medals
		if($bestTier < 0 || $bestTier > 3){
			printf("%s\n", ColorStr("Best tier for flow orb " . $pid . " sucks: recorded score is " . $pb . ", minimum for bronze is " . $currTiers[0] . " - counting this as bronze", 192, 128, 128));
			//continue;
			$bestTier = 0;
		}
		$florbMedalMap[$bestTier][] = $pid;
	}
	foreach($florbMedalMap as &$pidList_ref){
		sort($pidList_ref);
	}
	
	return $florbMedalMap;
}

function GetGlidePbs($puzzleCsvData){
	$glidePbs = [];
	foreach($puzzleCsvData as $entry){
		if($entry["ptype"] == PuzzlePrettyName("racingRingCourse")){
			$glidePbs[$entry["pid"]] = intval(round($entry["pb"]));
		}
	}
	ksort($glidePbs);
	return $glidePbs;
}

function GetGlideMedalMap($glidePbs){
	$glideTiers = GetGlideTiers();
	$tierCount = reset($glideTiers);
	$glideMedalMap = [[],[],[],[]];
	foreach($glidePbs as $pid => $pb){
		if(!isset($glideTiers[$pid])){
			printf("Skipping unknown glide %d\n", $pid);
			continue;
		}
		$currTiers = $glideTiers[$pid];
		$bestTier = 0;
		while($bestTier < count($currTiers) && $pb >= $currTiers[$bestTier]){
			++$bestTier;
		}
		--$bestTier; // -1 for no medal, 0..3 for medals
		if($bestTier < 0 || $bestTier > 3){
			// -1 shouldn't be possible as there wouldn't be a solve to begin with.
			printf("%s\n", ColorStr("Best tier for glide ring " . $pid . " sucks: recorded score is " . $pb . ", minimum for bronze is " . $currTiers[0] . " - counting this as bronze", 192, 128, 128));
			//continue;
			$bestTier = 0;
		}
		$glideMedalMap[$bestTier][] = $pid;
	}
	foreach($glideMedalMap as &$pidList_ref){
		sort($pidList_ref);
	}
	
	return $glideMedalMap;
}

function GetFlorbMedalCounts($florbMedalMap){
	$florbMedalCounts = array_map("count", $florbMedalMap);
	$result = [];
	foreach($florbMedalCounts as $tier => $count){
		$result[MedalTierToName($tier)] = $count;
	}
	//return $florbMedalCounts;
	return $result;
}

function GetSkydropChallengeTime($puzzleCsvData){
	foreach($puzzleCsvData as $entry){
		if($entry["pid"] != GetSkydropChallengePid()){
			continue;
		}
		$pb = $entry["pb"];
		$sscTiers = GetSkydropChallengeTiers();
		if($pb > $sscTiers[0]){
			// By default, skydrop challenge is automatically solved with time below Bronze.
			return -1;
		}
		return $entry["pb"];
	}
	return -1;
}

function GetSkydropChallengeMedalTier($pb){
	if($pb < 0){
		return -1;
	}
	$sscTiers = GetSkydropChallengeTiers();
	$bestTier = 0;
	while($bestTier < count($sscTiers) && $pb < $sscTiers[$bestTier] + 1e-3){
		++$bestTier;
	}
	--$bestTier;
	return $bestTier;
}

function WriteRawPuzzleSolves($saveJson, $outputPath){
	$result = @file_put_contents($outputPath, FormCsv($saveJson->puzzleCsvData));
	if($result){
		//printf("%s\n", ColorStr("Writing ". $outputPath, 128, 192, 255));
	}else{
		printf("%s\n", ColorStr("Failed to write ". $outputPath . " - most likely Excel is blocking it, close Excel first", 255, 128, 128));
	}
}

function WriteFlorbPbs($saveJson, $outputPath){
	$florbMedalMap = $saveJson->florbMedalMap;
	$florbPbs = $saveJson->florbPbs;
	$csv = [];
	$puzzleMap = GetPuzzleMap(true);
	foreach($florbMedalMap as $tier => $pidList){
		foreach($pidList as $pid){
			$data = $puzzleMap[$pid];
			$csv[] = [
				"pid" => $pid,
				"medal" => $tier,
				"type" => (int)$data->isDungeonPuzzle,
				"zone" => $data->actualZoneIndex,
				"pb" => number_format($florbPbs[$pid], 2, ".", ""),
				//"cetus" => "https://cetus.torstenindustries.com/Puzzle/" . $pid,
			];
		}
	}
	array_multisort(
					array_column($csv, "medal"), SORT_ASC,  SORT_NATURAL,
					array_column($csv, "type" ), SORT_DESC, SORT_NATURAL,
					array_column($csv, "zone" ), SORT_ASC,  SORT_NATURAL,
					array_column($csv, "pid"  ), SORT_ASC,  SORT_NATURAL,
					$csv);
	
	// Finalize csv for user readability.
	foreach($csv as &$entry_ref){
		$zoneIndex = $entry_ref["zone"];
		$entry_ref["type"] = ($entry_ref["type"] ? "Enclave" : "Hub");
		$entry_ref["zone"] = ($zoneIndex >= 2 && $zoneIndex <= 7 ? ZoneToPrettyNoColor($entry_ref["zone"]) : "Enclave");
		$entry_ref["medal"] = MedalTierToName($entry_ref["medal"]);
		
	}
	$formedCsv = FormCsv($csv);
	if(empty($formedCsv)){
		printf("%s\n", ColorStr("No florbs solved at all - skipping " . $outputPath, 200, 200, 40));
	}else{
		$result = @file_put_contents($outputPath, $formedCsv);
		if($result){
			printf("%s\n", ColorStr("Writing ". $outputPath, 128, 192, 255));
		}else{
			printf("%s\n", ColorStr("Failed to write ". $outputPath . " - most likely Excel is blocking it, close Excel first", 255, 128, 128));
		}
	}
}

function WriteFullSaveData($saveJson, $outputPath){
	// But like, why do this?
	unset($saveJson->puzzleCsvData);
	file_put_contents($outputPath, json_encode($saveJson, JSON_PRETTY_PRINT));
}

function GetUnlocks($mainNode){
	$fakePids = [];
	if(!isset($mainNode->Unlockables_0)){
		return $fakePids;
	}
	$unlocksNode = $mainNode->Unlockables_0->{"Array"}->value->Struct->value;
	foreach($unlocksNode as $node){
		$subNode = $node->Struct->Unlocks_0->{"Array"}->value->Base->Str;
		foreach($subNode as $title){
			//printf("|%s|\n", $title);
			$pid = UnlockTitleToPid($title);
			if($pid === FALSE){
				continue;
			}
			$fakePids[] = $pid;
		}
	}
	return $fakePids;
}

function GetQuests($mainNode){
	// This one is completely uninteresting.
	// It just contains the data for daily quests for sparks (including Wanderer ones).
	// And the progression data for the main campaign. You know, solve tutorial island, go to verdant, earn your wings...
	$quests = [];
	if(!isset($mainNode->Quests_0)){
		return $quests;
	}
	$questsNode = $mainNode->Quests_0->{"Array"}->value->Struct->value;
	foreach($questsNode as $node){
		$questName = $node->Struct->QuestID_0->Str->value;
		if(str_starts_with($questName, "Daily") || str_starts_with($questName, "Auto")){
			continue;
		}
		$questStatusString = $node->Struct->QuestStatus_0->Str->value;
		$questDataRaw = json_decode($questStatusString);
		if(!isset($questDataRaw->ObjData)){
			// Should always be present.
			continue;
		}
		$questData = $questDataRaw->ObjData;
		$test = json_encode($questData);
		$test = str_replace("QuestObjectiveState", "Q", $test);
		$test = str_replace("false", "0", $test);
		$test = str_replace("true", "1", $test);
		//printf("%s: %s\n", $questName, json_encode($questData));
		//printf(ColorStr(sprintf("%s: %s\n", $questName, $test), 255, 255, 0));
		$quests[$questName] = $test;
	}
	return $quests;
}

function GetMonolithFragments($miscMap){
	$puzzleMap = GetPuzzleMap(true);
	$megaTable = GetMegaTable();
	$monolithFragmentPids = [];
	foreach($miscMap as $obeliskPid => $miscString){
		$json = json_decode($miscString);
		if(empty($json) || !isset($json->Found)){
			continue;
		}
		$boolArray = $json->Found;
		if(!isset($puzzleMap[$obeliskPid])){
			continue;
		}
		$data = $puzzleMap[$obeliskPid];
		$actualPtype = $data->actualPtype;
		if($actualPtype != "obelisk"){
			continue;
		}
		$actualZoneIndex = $data->actualZoneIndex;
		//printf("%5d (%d): %s\n", $obeliskPid, $actualZoneIndex, $miscString);
		foreach($boolArray as $fragmentIndex => $status){
			if($status == false){
				continue;
			}
			$fakePid = MonolithFragmentToFakePid($actualZoneIndex, $fragmentIndex);
			$monolithFragmentPids[] = $fakePid;
		}
	}
	return $monolithFragmentPids;
}

function GetPlayingSince($puzzleCsvData){
	// puzzleCsvData assumed to be already sorted by tss
	$playingSinceTss = reset($puzzleCsvData)["tss"];
	if($playingSinceTss == TimestampToTss(0)){
		return "";
	}
	$playingSince = (TssToLocalDateTime($playingSinceTss))->format("M j, Y");
	return $playingSince;
}

function GetMasteryTable($mainNode){
	$masteryTable = [];
	$knownPtypes = GetKnownPtypes();
	if(count($knownPtypes) != 24){
		printf("%s\n", ColorStr("Yo Rushin, fix your code, GetKnownPtypes is all wrong", 255, 128, 128));
		exit(1);
	}
	foreach($knownPtypes as $ptype){
		$masteryTable[$ptype] = [
			"ptype" => $ptype,
			"level" => 0,
			"xp"    => 0,
			"pct"   => "0.00%",
			"title"  => "Novice",
			"border" => "none",
			"skin"   => 0,
		];
	}
	if(isset($mainNode->Achievements_0)){
		$masteryNode = $mainNode->Achievements_0->{"Array"}->value->Struct->value;
		foreach($masteryNode as $node){
			$masteryInternalName = $node->Struct->AchievementId_0->Str->value;
			$xp    = $node->Struct->Value_0->Struct->value->Struct->Progress_0->{"Int"}->value;
			//$level = ((int)$node->Struct->Value_0->Struct->value->Struct->LastCompletedTier_0->{"Int"}->value) + 1; // don't trust the given value
			$ptype = PuzzleInternalName(str_replace(["Mastery", "-"], "", $masteryInternalName));
			// Note: last completed tier starts with 0. So completing tier 0 advances you from level 0 to 1.
			// The final completed tier is 98 which advances you from 98 to 99.
			// Also: in offline mode completed tier is always zero for all masteries.
			//printf("%-30s %7d %2d\n", $masteryInternalName, $xp, $level);
			//printf("|%s| -> |%s|\n", $masteryInternalName, $ptype);
			if(empty($ptype)){
				// This will ignore total xp across all masteries which is a useless stat.
				continue;
			}
			$level = XpToLevel($xp);
			$pct = 100.0 * $xp / GetTotalXpTo99();
			$extraInfo = GetXpLevelInfo($level);
			$masteryTable[$ptype] = [
				"ptype"  => $ptype,
				"level"  => $level,
				"xp"     => $xp, //number_format($xp, 0, ".", " "), // don't number format this aight?
				"pct"    => number_format($pct, 2, ".", "") . "%",
				"title"  => $extraInfo["title"],
				"border" => $extraInfo["border"],
				"skin"   => $extraInfo["skin"],
			];
		}
	}
	$masteryTable = array_values($masteryTable);
	array_multisort(array_column($masteryTable, "xp"), SORT_DESC, SORT_NUMERIC, $masteryTable);
	return $masteryTable;
}

function GetPlayerLevel($masteryTable){
	$puzzleLevels = array_column($masteryTable, "level");
	$totalPuzzleLevels = array_sum($puzzleLevels);
	$playerLevel = intval(floor(1.01 + ($totalPuzzleLevels / 12.0)));
	return $playerLevel;
}

function WriteMasteries($saveJson, $outputPath){
	$formedCsv = FormCsv($saveJson->masteryTable);
	$result = @file_put_contents($outputPath, $formedCsv);
	if($result){
		//printf("%s\n", ColorStr("Writing ". $outputPath, 128, 192, 255));
	}else{
		printf("%s\n", ColorStr("Failed to write ". $outputPath . " - most likely Excel is blocking it, close Excel first", 255, 128, 128));
	}
}

function GetCurrency($mainNode, $currencyName){
	if(!isset($mainNode->Wallet_0)){
		return 0;
	}
	$walletNode = $mainNode->Wallet_0->{"Array"}->value->Struct->value;
	foreach($walletNode as $node){
		$currency = $node->Struct->Currency_0->Str->value;
		if($currency == $currencyName){
			$amount = $node->Struct->Balance_0->{"Int"}->value;
			return $amount;
		}
	}
	return 0;
}

function GetSparks($mainNode){
	return (GetCurrency($mainNode, "coins"));
}

function GetBuggedMirabilis($mainNode){
	return (GetCurrency($mainNode, "blue-orbs"));
}

function GetAllRemainingArmillaries($allSolvedPids){
	$allArmillaries = GetAllArmillaries();
	$remainingArmillaries = array_values(array_diff($allArmillaries, $allSolvedPids));
	return $remainingArmillaries;
}

function GetAllSolvedArmillaries($allSolvedPids){
	$allArmillaries = GetAllArmillaries();
	$solvedArmillaries = array_values(array_intersect($allArmillaries, $allSolvedPids));
	return $solvedArmillaries;
}

function GetTempleRemainingArmillaries($allSolvedPids){
	$templeArmillaries = GetTempleArmillaries();
	$remainingTempleArmillaries = array_values(array_diff($templeArmillaries, $allSolvedPids));
	return $remainingTempleArmillaries;
}

function GetTempleSolvedArmillaries($allSolvedPids){
	$templeArmillaries = GetTempleArmillaries();
	$solvedTempleArmillaries = array_values(array_intersect($templeArmillaries, $allSolvedPids));
	return $solvedTempleArmillaries;
}

function GetStaticRemainingArmillaries($allSolvedPids){
	$staticArmillaries = GetStaticArmillaries();
	$remainingStaticArmillaries = array_values(array_diff($staticArmillaries, $allSolvedPids));
	return $remainingStaticArmillaries;
}

function GetStaticSolvedArmillaries($allSolvedPids){
	$staticArmillaries = GetStaticArmillaries();
	$solvedStaticArmillaries = array_values(array_intersect($staticArmillaries, $allSolvedPids));
	return $solvedStaticArmillaries;
}

function GetStaticRemainingPids($allSolvedPids){
	$allStaticPids = GetAllStaticPids();
	$remainingStaticPids = array_values(array_diff($allStaticPids, $allSolvedPids));
	return $remainingStaticPids;
}

function GetStaticSolvedPids($allSolvedPids){
	$allStaticPids = GetAllStaticPids();
	$solvedStaticPids = array_values(array_intersect($allStaticPids, $allSolvedPids));
	return $solvedStaticPids;
}

function GetClusterRemainingMap($allSolvedPids){
	$clusterMap = GetClusterMap();
	$remainingClusterMap = [];
	foreach($clusterMap as $pool => $pidList){
		$remainingClusterMap[$pool] = array_values(array_diff($pidList, $allSolvedPids));
		//printf("remaining %d of %d in cluster %s\n", count($remainingClusterMap[$pool]), count($pidList), $pool);
	}
	return $remainingClusterMap;
}

function GetClusterSolvedMap($allSolvedPids){
	$clusterMap = GetClusterMap();
	$solvedClusterMap = [];
	foreach($clusterMap as $pool => $pidList){
		$solvedClusterMap[$pool] = array_values(array_intersect($pidList, $allSolvedPids));
		//printf("solved %d of %d in cluster %s\n", count($solvedClusterMap[$pool]), count($pidList), $pool);
	}
	return $solvedClusterMap;
}

function GetRemainingMysteries($allSolvedPids){
	$mysteryMap = GetMysteryMap();
	$remainingMysteries = [];
	foreach($mysteryMap as $pid => $mysteryId){
		if(!in_array($pid, $allSolvedPids)){
			$remainingMysteries[] = $mysteryId;
		}
	}
	return $remainingMysteries;
}

function GetSolvedMysteries($allSolvedPids){
	$mysteryMap = GetMysteryMap();
	$solvedMysteries = [];
	foreach($mysteryMap as $pid => $mysteryId){
		if(in_array($pid, $allSolvedPids)){
			$solvedMysteries[] = $mysteryId;
		}
	}
	return $solvedMysteries;
}
	
function GetCosmetics($mainNode){
	$cosmetics = [];
	if(!isset($mainNode->Inventory_0)){
		return $cosmetics;
	}
	$inventoryNode = $mainNode->Inventory_0->{"Array"}->value->Struct->value;
	foreach($inventoryNode as $node){
		$item = $node->Struct->ObjectId_0->Str->value;
		$qty = $node->Struct->quantity_0->{"Int64"}->value;
		//$cosmetics[] = $item . " (" . $qty . ")";
		if($qty != 1){
			printf("%s\n", ColorStr("Cosmetic item \"" . $item . "\" has quantity " . $qty, 255, 128, 128));
			printf("%s\n", ColorStr("Please report this on the Discord server", 255, 128, 128));
			exit(1);
		}
		$cosmetics[] = $item;
	}
	return $cosmetics;
}

function GetHubRewardTiers($mainNode){
	$rewardTiers = [];
	$zoneCategories = GetZoneCategories();
	foreach($zoneCategories as $zoneIndex => $pcatList){
		$rewardTiers[$zoneIndex] = [];
		foreach($pcatList as $pcat){
			$rewardTiers[$zoneIndex][$pcat] = 0;
		}
	}
	
	if(!isset($mainNode->RewardProgress_0)){
		return $rewardTiers;
	}
	$rewardsNode = $mainNode->RewardProgress_0->{"Array"}->value->Struct->value;
	$regex = "/^([A-Z][a-z]*)([A-Z][a-zA-Z]*)SandboxProgression$/";
	foreach($rewardsNode as $node){
		$trackName = $node->Struct->ProgressId_0->Str->value;
		if(!preg_match($regex, $trackName, $matches) || count($matches) < 3){
			continue;
		}
		$zoneIndex = ZoneNameToInt($matches[1]);
		$pcat      = PuzzleCategoryInternalName($matches[2]);
		$trackTier = $node->Struct->RewardedLevel_0->{"Int"}->value;
		//printf("%-18s %-14s %d\n", ZoneToPrettyNoColor($zoneIndex), PuzzleCategoryPrettyName($pcat), $trackTier);
		if(!IsHubZone($zoneIndex) || empty($pcat) || $trackTier < 0){
			continue;
		}
		$rewardTiers[$zoneIndex][$pcat] = $trackTier;
		//printf("REWARD TIER: |%s| %d\n", $trackName, $trackTier);
		//printf("%s\n", $trackName);
	}
	return $rewardTiers;
}

function GetHubRewards($hubSolvedProfile){
	$reduced = ReduceProfileToCategories($hubSolvedProfile);
	$rewardsMap = LoadHubTrackRewards();
	$myRewards = [];
	
	foreach($rewardsMap as $zoneIndex => $pcatMap){
		$myRewards[$zoneIndex] = [];
		foreach($pcatMap as $pcat => $tierInfo){
			$myRewards[$zoneIndex][$pcat] = [];
			foreach($tierInfo as $pcount => $reward){
				//printf("%-18s %-18s %4d %s\n", ZoneToPrettyNoColor($zoneIndex), PuzzleCategoryPrettyName($pcat), $pcount, $reward);
				$myCount = count($reduced[$zoneIndex][$pcat]);
				$myRewards[$zoneIndex][$pcat][] = [ //(object)[
					"reward" => $reward,
					"isObtained" => (bool)($myCount >= $pcount),
					"offset" => ($pcount - $myCount),
				];
			}
		}
	}
	return $myRewards;
}

function GetSaveFileSettings($mainNode){
	$settings = [];
	if(!isset($mainNode->Settings_0)){
		return $settings;
	}
	$settingsNode = $mainNode->Settings_0->{"Array"}->value->Struct->value;
	foreach($settingsNode as $node){
		$option = $node->Struct->OptionId_0->Str->value;
		$value  = $node->Struct->Value_0->Str->value;
		if($option == "unlocked_zones"){
			//$value = json_encode(json_decode($value)); // awful formatting
			$value = implode(",", array_map("intval", array_values((array)json_decode($value))));
		}
		//$option = str_pad($option, 23, " ", STR_PAD_RIGHT); // debug
		$settings[$option] = $value;
	}
	ksort($settings, SORT_NATURAL);
	return $settings;
}

function HasDeluxe(array $settings){
	$str = "Has Deluxe Edition";
	return (isset($settings[$str]) && $settings[$str] == 1);
}
