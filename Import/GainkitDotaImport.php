<?php

namespace Import;

class GainkitDotaImport extends GainkitImportAbstract
{

    const GAME                  = 570;
    const GAINKIT_DB_FIELDS     = '/stock/fields/dota2';
    const GAINKIT_DB_GAME_URL   = '/stock/dota2';

    protected $keysToDiff = [
        'classid', 'instanceid', 'offers', 'price', 'price_updated'
    ];
}