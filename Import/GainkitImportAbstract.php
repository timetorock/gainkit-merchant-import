<?php

namespace Import;

use PDO;
use League\Csv\Reader;

/**
 * Gainkit database main import class.
 * Class GainkitImportAbstract
 * @package Import
 */
abstract class GainkitImportAbstract
{
	/**
	 * Your table to compare with.
	 */
	const TABLE_NAME = 'market';

    const INSERT_SPLIT      = 100;
	/**
	 * Main keys used to import work.
	 */
    const GAINKIT_PRIMARY_KEY = 'id';
    const GAINKIT_CLASSID     = 'classid';
    const GAINKIT_INSTANCEID  = 'instanceid';
    const GAINKIT_GAME        = 'game';
	const GAINKIT_PRICE       = 'price';
    const GAINKIT_OFFERS      = 'offers';
    const GAINKIT_UPDATEDAT   = 'updated_at';


	const GAINKIT_URL          = 'https://gainkit.com';
	const GAINKIT_API_VERSION  = '/api/v1';

	const API_REQUEST = self::GAINKIT_URL . self::GAINKIT_API_VERSION;

	const GAME                 = '';
	const GAINKIT_DB_FIELDS    = '';
	const GAINKIT_DB_GAME_URL  = '';
	/**
	 * @var PDO
	 */
	protected $db = null;
	/**
	 * @var string
	 */
	protected $dbconnection = '';
	protected $dblogin = '';
	protected $dbpassword = '';
	/**
	 * Import keys
	 * @var array
	 */
    protected $csvKeys = [];
	/**
	 * Merchant API key for Gainkit.
	 * @var string
	 */
	protected $apiKey = '';

    /**
     * What will be updated in our DB.
     * @var array
     */
    protected $keysToUpdate = [
        self::GAINKIT_OFFERS, self::GAINKIT_PRICE, self::GAINKIT_UPDATEDAT
    ];

	public function __construct($config)
	{
		if (empty($config['api_key'])) {
			$this->info('Wrong Gainkit API Key!');
			die();
		}

		if (empty($config['dbconnection'])) {
			$this->info('DB connection has wrong value.');
			die();
		}

		if (empty($config['dblogin'])) {
			$this->info('DB login has wrong value.');
			die();
		}

		if (empty($config['dbpassword'])) {
			$this->info('DB password has wrong value.');
			die();
		}

		$this->apiKey = $config['api_key'];
		$this->dbconnection = $config['dbconnection'];
		$this->dblogin = $config['dblogin'];
		$this->dbpassword = $config['dbpassword'];
	}

	/**
	 * Start import of gainkit database to local db.
	 * @author Alexander Natalenko
	 * @email       alejandronat@gmail.com
	 */
	public function start()
    {
	    $this->info('Start. ' . date("Y-m-d H:i:s"));

		$this->initDB();

	    // Create a stream
	    $opts = [
		    "http" => [
			    "method" => "GET",
			    "header" => "X-Api-Key: $this->apiKey\r\n"
		    ]
	    ];

	    try {
		    $this->csvKeys = json_decode(
			    file_get_contents(
				    self::API_REQUEST . static::GAINKIT_DB_FIELDS,
				    false,
				    stream_context_create($opts)
			    ), true);
	    } catch (\ErrorException $e) {
		    $this->info('Failed to get import fields from Gainkit. ' . date("Y-m-d H:i:s"));
		    return;
	    }

	    if (empty($this->csvKeys)) {
		    $this->info('CSV Import keys not valid!');
		    return;
	    }

	    try {
		    $actualDb = file_get_contents(self::API_REQUEST . static::GAINKIT_DB_GAME_URL, false, stream_context_create($opts));
	    } catch (\ErrorException $e) {
		    $this->info('Failed to get new DB from Gainkit. ' . date("Y-m-d H:i:s"));
		    return;
	    }

	    $this->info('Download finished. ' . date("Y-m-d H:i:s"));
	    $this->showMemory();

	    $inputCsv = Reader::createFromString($actualDb);
	    $inputCsv->setDelimiter(';');
	    $inputCsv->setOffset(1);
	    $newDbArray = iterator_to_array($inputCsv->fetchAssoc($this->csvKeys));
        $this->info('CSV in array.' . date("Y-m-d H:i:s"));
	    $this->showMemory();

        $getItemsSql = "select `id`, `game`, `classid`, `instanceid`, `price` from `market` where `game` = ?";

        $query = $this->db->prepare($getItemsSql);
        $query->execute([static::GAME]);

        $GKD2netDB = [];
        $this->info('Start fetchAll: ' . date("Y-m-d H:i:s"));

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $GKD2netDB[] = $row;
        }

        $this->info('Finish fetchAll: ' . date("Y-m-d H:i:s"));
	    $this->showMemory();

        $oldDbUniqueKeys = $this->unifyKeyForDB($GKD2netDB);

        $toUpdate = [];
        $toInsert = [];
        $hasOffers = [];
        $this->info('Start preparing insert/update: ' . date("Y-m-d H:i:s"));
	    /**
	     * Start compare new & old databases, make desigion on compare result.
	     */
        for ($i=1, $n=count($newDbArray); $i < $n; $i++) {
            $dbItem = &$newDbArray[$i];
            //add Game
            $dbItem[static::GAINKIT_GAME] = static::GAME;
            //Create unique keys from classid, instanceid.
            $key = "{$dbItem[static::GAINKIT_GAME]}{$dbItem[static::GAINKIT_CLASSID]}{$dbItem[static::GAINKIT_INSTANCEID]}";
            $value = $dbItem;

            $value[self::GAINKIT_PRICE] = self::integerCents($value[self::GAINKIT_PRICE]);
	        $value[self::GAINKIT_UPDATEDAT] = date("Y-m-d H:i:s");
            
            //If item already exist in our DB.
            if (isset($oldDbUniqueKeys[$key])) {
                $primaryId = $oldDbUniqueKeys[$key][static::GAINKIT_PRIMARY_KEY];
                $hasOffers[] = $primaryId;             
                //remove primary key of inserted item for diff.
                unset($oldDbUniqueKeys[$key][static::GAINKIT_PRIMARY_KEY]);
                //Check if field need to be updated.
                if (self::diffImport($oldDbUniqueKeys[$key], $value)) {
                    $item = [];
                    //update only selected fields.
                    foreach ($this->keysToUpdate as $ktu) {
                        $item[$ktu] = $value[$ktu];
                    }
                    //add datetime of processing.
                    $toUpdate[$primaryId] = $item;
                }
                $oldDbUniqueKeys[$key] = null;
            } else {
                $value[self::GAINKIT_UPDATEDAT] = date("Y-m-d H:i:s");
                $toInsert[] = $value;
            }
	        /**
	         * Slow down, so script don't eat all CPU at once .
	         */
            usleep(100);
        }

        $newDbArray = null;
        $oldDbUniqueKeys = null;

        $this->info('Preparing insert/update finished. ' . date("Y-m-d H:i:s"));

	    if (count($hasOffers) > 0) {
		    $this->info('Hide not used items. ' . date("Y-m-d H:i:s") );

		    $query = $this->db->prepare("UPDATE market SET offers = ? WHERE game = ? AND id NOT IN (" . implode(', ', array_fill(0, sizeof($hasOffers), '?')) . ")");

		    array_unshift($hasOffers, static::GAME);
		    array_unshift($hasOffers, 0);

		    $query->execute($hasOffers);

		    $this->info('Hidden items: ' . $query->rowCount());
	    }

	    $this->info('Start insert/update. ' . date("Y-m-d H:i:s"));

        //Insert new items to DB if we have any.
        if (!empty($toInsert)) {
            $notInserted = 0;
	        $inserted = 0;
            $this->info('To Insert:' . count($toInsert));
            //Insert by N items at a time.
            $splitedInsert = array_chunk($toInsert, static::INSERT_SPLIT);
            foreach ($splitedInsert as $insert) {
	            $keysValues = self::arrayToInsertParams($insert);
                try {
	                $query = $this->db->prepare("INSERT INTO market (" . $keysValues['keys'] . ") VALUES " . $keysValues['marks']);
	                $query->execute($keysValues['values']);
	                $inserted += $query->rowCount();
                } catch (\Exception $e) {
                    $notInserted += count($insert);
                    $this->info('Insert fail with exception.' . $e->getMessage());
                }

            }
	        $this->info(($inserted) . ' Items Inserted.' . date("Y-m-d H:i:s"));
	        $this->info($notInserted . ' inserts failed.' . date("Y-m-d H:i:s"));
        } else {
            $this->info('Nothing to Insert.');
        }
        
        //Update items if we have any for update.
        if (!empty($toUpdate)) {
            $this->info('To Update:' . count($toUpdate));
	        $updated = 0;
            foreach ($toUpdate as $pkey => $update) {
	            $keys = self::arrayToUpdateParams($update);
	            $query = $this->db->prepare("UPDATE market SET $keys WHERE id = ?");
	            $query->execute([$pkey]);
	            $updated += $query->rowCount();
            }
	        $this->info('Updated items: ' . $updated);
        } else {
            $this->info('Nothing to Update.');
        }

        $this->info('Insert/update finished. ' . date("Y-m-d H:i:s"));
        $this->info('Finished. ' . count($hasOffers) . ' - ' . date("Y-m-d H:i:s") );
        $this->showMemory();
    }

    /**
     * Create unique key from concatenating composite in DB.
     * @author Alexander Natalenko
     * @email       alejandronat@gmail.com
     * @param       array                    $newDbArray [description]
     * @return      array                    Items in DB with unique key.
     */
    public function unifyKeyForDB(array $newDbArray)
    {
        $time1 = date("Y-m-d H:i:s");

        $newDbUniqueKeys = [];

        for ($i=0, $n=count($newDbArray); $i < $n; $i++) {
            $array = &$newDbArray[$i];
            //create unique id
            $newDbUniqueKeys["{$array[static::GAINKIT_GAME]}{$array[static::GAINKIT_CLASSID]}{$array[static::GAINKIT_INSTANCEID]}"] = $array;
            unset($newDbArray[$i]);
	        /**
	         * Slow down, so script don't eat all CPU at once .
	         */
            usleep(100);
        }

        $this->info('DB Array unified for:' . $time1 . '-' . date("Y-m-d H:i:s"));
	    $this->showMemory();

        return $newDbUniqueKeys;
    }

	/**
	 * Initiate connection to our database.
	 */
	protected function initDB() {
		$this->db = new PDO(
			$this->dbconnection,
			$this->dblogin, $this->dbpassword,
			[PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]
		);
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	/**
	 * @param array $params
	 * @return string
	 */
	protected static function arrayToUpdateParams(array $params) : string {

		$updateStringParams = [];

		foreach ($params as $key => $param) {
			$updateStringParams[] = $key . '="' . $param . '"';
		}

		return implode(',' , $updateStringParams);
	}

	/**
	 * @param array $params
	 * @return array
	 */
	protected static function arrayToInsertParams(array $params) : array {

		$keys = [];
		$values = [];
		$marks = [];

		foreach ($params as $key => $value) {
			foreach ($value as $k => $v) {
				if (!in_array($k, $keys, true)) {
					$keys[] = $k;
				}
			}

			$values = array_merge($values, array_values($value));
			$marks[] = "(" . implode(', ', array_fill(0, sizeof($value), '?')) . ")";
		}

		return [
			'keys' => implode(', ', $keys),
			'values' => $values,
			'marks' => implode(', ', $marks),
		];
	}

    /**
     * Compare only fields that important to us.
     * @author Alexander Natalenko
     * @email       alejandronat@gmail.com
     * @return boolean Notify if we need to update price.
     */
    protected static function diffImport($oldRow, $newRow)
    {
        //If prices are equal - return false.
        return boolval(round($newRow[self::GAINKIT_PRICE]) <=> round($oldRow[self::GAINKIT_PRICE]));
    }

	/**
	 * Show if there any memory leaks.
	 */
	protected static function showMemory() {
		self::info('Memory usage:' . self::convert(memory_get_usage(true)));
	}

	/**
	 * @param $size
	 * @return string
	 */
    protected static function convert($size)
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }

	/**
	 * @param $text
	 */
	protected static function info($text) {
		echo $text . "\n";
	}

	/**
	 * @param $cents
	 * @return float
	 */
	protected static function integerCents($cents) {
		return round($cents, 0);
	}
}
