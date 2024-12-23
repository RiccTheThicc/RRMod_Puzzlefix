<?php

define("SORT_UASSET_NODES", false); // broken

function &ParseUassetValueNode(string $type, &$node_ref){
	
	//if(!is_array($node_ref) || count($node_ref) != 10){ var_dump($node_ref); }
	
	static $error = null;
	
	switch($type){
		
		case "UAssetAPI.ExportTypes.NormalExport, UAssetAPI":
		case "UAssetAPI.ExportTypes.LevelExport, UAssetAPI":
			$dataArr = [];
			foreach($node_ref as $index => &$subNode_ref){
				if(isset($subNode_ref->Value)){
					$subName = $subNode_ref->Name;
					$subType = $subNode_ref->{'$type'};
					$dataArr[$subName] = &ParseUassetValueNode($subType, $subNode_ref->Value);
				}
			}unset($subNode_ref);
			//return (object)$dataArr;
			return $dataArr;
			
		case "UAssetAPI.PropertyTypes.Objects.SetPropertyData, UAssetAPI":
		case "UAssetAPI.PropertyTypes.Objects.ArrayPropertyData, UAssetAPI":
			//$isStruct = ($node_ref->ArrayType == "StructProperty");
			if(count($node_ref) == 1){
				if(isset($node_ref[0]->Value)){
					return ParseUassetValueNode($node_ref[0]->{'$type'}, $node_ref[0]->Value);
				}else{
					return null;
				}
			}
			$valueArr = [];
			foreach($node_ref as $index => &$subNode_ref){
				if(isset($subNode_ref->Value)){
					$subType = $subNode_ref->{'$type'};
					$valueArr[] = &ParseUassetValueNode($subType, $subNode_ref->Value);
				}
			}unset($subNode_ref);
			return $valueArr;
			
		case "UAssetAPI.PropertyTypes.Objects.BoolPropertyData, UAssetAPI":
		case "UAssetAPI.PropertyTypes.Objects.EnumPropertyData, UAssetAPI":
		case "UAssetAPI.PropertyTypes.Objects.IntPropertyData, UAssetAPI":
		case "UAssetAPI.PropertyTypes.Objects.FloatPropertyData, UAssetAPI":
		case "UAssetAPI.PropertyTypes.Objects.StrPropertyData, UAssetAPI":
		case "UAssetAPI.PropertyTypes.Objects.ObjectPropertyData, UAssetAPI":
		case "UAssetAPI.PropertyTypes.Objects.NamePropertyData, UAssetAPI": // ?
		case "UAssetAPI.PropertyTypes.Objects.TextPropertyData, UAssetAPI":
		case "UAssetAPI.PropertyTypes.Structs.GuidPropertyData, UAssetAPI":
		case "UAssetAPI.PropertyTypes.Structs.ColorPropertyData, UAssetAPI":
			return $node_ref;
			
		case "UAssetAPI.PropertyTypes.Structs.StructPropertyData, UAssetAPI":
		case "UAssetAPI.PropertyTypes.Objects.MulticastSparseDelegatePropertyData, UAssetAPI":
		
			$fieldArr = [];
			if(count($node_ref) == 1){
				if(isset($node_ref[0]->Value)){
					return ParseUassetValueNode($node_ref[0]->{'$type'}, $node_ref[0]->Value);
				}else{
					return $error;
				}
			}
			foreach($node_ref as $index => &$subNode_ref){
				if(isset($subNode_ref->Value)){
					$subName = $subNode_ref->Name;
					$subType = $subNode_ref->{'$type'};
					
					$finalSubName = $subName;
					$i = 2;
					while(isset($fieldArr[$finalSubName])){
						$finalSubName = $subName . $i;
						++$i;
					}
					//if(isset($fieldArr[$finalSubName])){
					//	printf("ERROR: duplicate field name %s\n", $finalSubName);
					//	exit(1);
					//}
					$fieldArr[$finalSubName] = &ParseUassetValueNode($subType, $subNode_ref->Value);
				}
			}unset($subNode_ref);
			//return "WHY?";
			if(SORT_UASSET_NODES){ uksort($fieldArr, "strnatcasecmp"); }
			return $fieldArr;
			//return "dirka";
			//return json_encode($fieldArr);
			
			
		case "UAssetAPI.PropertyTypes.Structs.BoxPropertyData, UAssetAPI":
			//print_r($type); print_r($node_ref);
			$custom = (object)[
				"MinX" => &$node_ref->{'Min'}->X,
				"MinY" => &$node_ref->{'Min'}->Y,
				"MinZ" => &$node_ref->{'Min'}->Z,
				"MaxX" => &$node_ref->{'Max'}->X,
				"MaxY" => &$node_ref->{'Max'}->Y,
				"MaxZ" => &$node_ref->{'Max'}->Z,
			];
			return $custom;
		
		case "UAssetAPI.PropertyTypes.Structs.QuatPropertyData, UAssetAPI":
			$custom = (object)[
				"X" => &$node_ref->X,
				"Y" => &$node_ref->Y,
				"Z" => &$node_ref->Z,
				"W" => &$node_ref->W,
			];
			return $custom;
		
		case "UAssetAPI.PropertyTypes.Structs.Vector2DPropertyData, UAssetAPI":
			$custom = (object)[
				"X" => &$node_ref->X,
				"Y" => &$node_ref->Y,
			];
			return $custom;
			
		case "UAssetAPI.PropertyTypes.Structs.VectorPropertyData, UAssetAPI":
			$custom = (object)[
				"X" => &$node_ref->X,
				"Y" => &$node_ref->Y,
				"Z" => &$node_ref->Z,
			];
			return $custom;
			
		case "UAssetAPI.PropertyTypes.Structs.RotatorPropertyData, UAssetAPI":
			$custom = (object)[
				"Pitch" => &$node_ref->Pitch,
				"Yaw"   => &$node_ref->Yaw,
				"Roll"  => &$node_ref->Roll,
			];
			return $custom;
		
		case "UAssetAPI.PropertyTypes.Objects.SoftObjectPropertyData, UAssetAPI":
		case "UAssetAPI.PropertyTypes.Structs.SoftClassPathPropertyData, UAssetAPI":
			return $node_ref->AssetPath->AssetName;
		
		case "UAssetAPI.PropertyTypes.Objects.BytePropertyData, UAssetAPI":
			return $error; // ?
			
		case "UAssetAPI.PropertyTypes.Objects.MapPropertyData, UAssetAPI":
			if(count($node_ref) == 0){
				return (object)[];
			}
			$mapName = null;
			$map = [];
			foreach($node_ref as $index => &$subNode_ref){
				if(!is_array($subNode_ref) || count($subNode_ref) != 2){
					printf("Malformed MapPropertyData\n");
					exit(1);
				}
				if(isset($subNode_ref[1]->Value)){
					$mapKey_ref   = &($subNode_ref[0])->Value;
					$mapValue_ref = &($subNode_ref[1])->Value;
					if(!is_scalar($mapKey_ref)){
						printf("Error: non-scalar MapPropertyData key |%s|\n", json_encode($mapKey_ref));
						exit(1);
					}
					if($mapName === null){
						$mapName = $subNode_ref[0]->Name;
					}
					$map[$mapKey_ref] = &ParseUassetValueNode($subNode_ref[1]->{'$type'}, $mapValue_ref);
					unset($mapKey_ref);
					unset($mapValue_ref);
				}
			}unset($subNode_ref);
			//return (object)[ $mapName => $map ];
			if(SORT_UASSET_NODES){ uksort($map, "strnatcasecmp"); }
			return $map;
			
		case "UAssetAPI.PropertyTypes.Objects.DelegatePropertyData, UAssetAPI":
			return $node_ref->Delegate; // ?
			
		
		case "System.Byte[], System.Private.CoreLib":
		case "UAssetAPI.CustomVersion, UAssetAPI":
		case "UAssetAPI.ExportTypes.FURL, UAssetAPI":
		case "UAssetAPI.FEngineVersion, UAssetAPI":
		case "UAssetAPI.FGenerationInfo, UAssetAPI":
		case "UAssetAPI.Import, UAssetAPI":
		case "UAssetAPI.PropertyTypes.Objects.FSoftObjectPath, UAssetAPI":
		case "UAssetAPI.PropertyTypes.Objects.FTopLevelAssetPath, UAssetAPI":
		case "UAssetAPI.UAsset, UAssetAPI":
		case "UAssetAPI.UnrealTypes.FQuat, UAssetAPI":
		case "UAssetAPI.UnrealTypes.FRotator, UAssetAPI":
		case "UAssetAPI.UnrealTypes.FVector, UAssetAPI":
		case "UAssetAPI.UnrealTypes.TBox`1[[UAssetAPI.UnrealTypes.FVector, UAssetAPI]], UAssetAPI":
			// Nothing. Handle later
	}
	
	printf("\nERROR: did not parse |%s|:\n%s\n\n", $type, json_encode($node_ref));
	exit(1);
}

function &ParseUassetExports(&$json_ref){
	$exports_ref = &$json_ref->Exports;
	$result = [];
	foreach($exports_ref as $index => &$object_ref){
		$objectName = $object_ref->ObjectName;
		$type = $object_ref->{'$type'};
		
		$info = [];
		
		//$info['Data'] = ParseUassetNode($object_ref->Data);
		
		//$dataArr = [];
		//foreach($object_ref->Data as $index => &$dataNode_ref){
		//	$name = $dataNode_ref->Name;
		//	$value_ref = &$dataNode_ref->Value;
		//	$dataArr[$name] = ParseUassetValueNode($value_ref);
		//}
		//$info['Data'] = $dataArr;
		//var_dump($dataArr);

		$info['Data'] = &ParseUassetValueNode($type, $object_ref->Data);
		if(SORT_UASSET_NODES){ uksort($info['Data'], "strnatcasecmp"); }
		$info['Data'] = (object)$info['Data'];
		
		//printf("\nParsed object: |%s|\n", $objectName);
		//print_r($info['Data']);
		//exit(1);
		
		//$objAsArray = (array)$object_ref;
		$objKeys = array_keys((array)$object_ref);
		foreach($objKeys as $key){
			static $dontInclude = [ '$type', 'Data', 'ObjectName' ];
			if(in_array($key, $dontInclude)){
				continue;
			}
			$value_ref = &$object_ref->$key;
			$info[$key] = &$value_ref;
			unset($value_ref);
		}
		//natcasesort($info);
		//ksort($info);
		if(SORT_UASSET_NODES){ uksort($info, "strnatcasecmp"); }

		//if(isset($result[$objectName])){
		//	printf("ERROR: duplicate export object name %s\n", $objectName);
		//	exit(1);
		//}
		//$result[$objectName] = (object)($info);
		
		$finalName = $objectName;
		$i = 2;
		while(isset($result[$finalName])){
			$finalName = $objectName . "_" . $i;
			++$i;
		}
		$result[$finalName] = (object)($info);
	}unset($object_ref);
	unset($exports_ref);
	return $result;
}

function LoadDecodedUasset(string $path){
	if(!is_file($path)){
		printf("File not found: %s\n", $path);
		exit(1);
	}
	printf("Loading %s...\n", $path);
	$raw = file_get_contents($path);
	if(empty($raw)){
		printf("Failed to load %s\n", $path);
		exit(1);
	}
	$json = json_decode($raw);
	if(empty($json)){
		printf("Failed to load %s\n", $path);
		exit(1);
	}
	return $json;
}

function SaveDecodedUasset(string $path, &$json){
	printf("Saving %s...\n", $path);
	file_put_contents($path, CreateJson($json));
}
