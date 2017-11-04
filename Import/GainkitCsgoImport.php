<?php

namespace Import;

class GainkitCsgoImport extends GainkitImportAbstract
{
	const GAME = 730;
	const GAINKIT_DB_FIELDS = '/stock/fields/csgo';
	const GAINKIT_DB_GAME_URL = '/stock/csgo';

	protected $keysToDiff = [
		'classid', 'instanceid', 'offers', 'price', 'price_updated'
	];
}