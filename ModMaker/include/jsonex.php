<?php

function CreateJson($data, array $options = []){
	$defaultOptions = [
		"spaces"  => 4,
		"tabs" => 1,
		"useTabs" => false,
		"escapeSlashes" => true,
		"prettyPrint" => false,
		"onePerLine" => false,
	];
	$options = array_merge($defaultOptions, $options);
	//$x           = $options["x"];
	//$y           = $options["y"];
	//$align       = $options["align"];
	////$angle       = $options["angle"];
	//$colorCode   = $options["colorCode"];
	//$fontSize    = ParseFontSize($options["fontSize"]);
	//$fontName    = $options["fontName"];
	//$isCentered  = (bool)$options["centered"];
	////$strokeWidth = round(max(0, $options["strokeWidth"]));
	//$strokeWidth = Clamp(round($options["strokeWidth"]), 0, 2);
	//$strokeColor = $options["strokeColor"];
	//$cardColor   = $options["cardColor"];
	//$cardMargin  = round(max(0, $options["cardMargin"]));
	//$cardRadius  = round(max(0, $options["cardRadius"]));
	//$fitWidth    = $options["fitWidth"];  // not implemented
	//$fitHeight   = $options["fitHeight"]; // not implemented
	//$maxWidth    = $options["maxWidth"];
	//$maxHeight   = $options["maxHeight"];
	
	//$final = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	// Magic (and slow) function that prints a json, except with 2 spaces for tabs instead of 4 (which is what the asset dumper does).
	$final = preg_replace_callback('/^(?: {4})+/m', function($m) { return str_repeat(' ', 2 * (strlen($m[0]) / 4)); }, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	return $final;
}
