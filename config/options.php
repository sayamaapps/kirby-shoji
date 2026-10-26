<?php

// Options Shoji reads, relative to the plugin: `sayamaapps.shoji.<key>` (OPT-1).
// Each value is null on purpose. Options::get() gives the default at every read,
// and a default here would hide the 1.x spelling `shoji.<key>` (fallback).
return [
	'active'    => null,
	'language'  => null,
	'open'      => null,
	'privacy'   => null,
	'providers' => null,
	'script'    => null,
	'secret'    => null,
	'texts'     => null,
	'url'       => null,
];
