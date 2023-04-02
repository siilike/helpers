<?php

use CommonX\IllegalArgumentException;
use MongoDB\Driver\WriteResult;

function createDBRef($name, $id)
{
	return [
		'$ref' => $name,
		'$id' => $id,
	];
}

function iterator_to_map($iterator, $idKey = '_id')
{
	$ret = array();

	foreach($iterator as $a)
	{
		$k = $a[$idKey];

		$ret[is_scalar($k) ? $k : (string)$k] = $a;
	}

	return $ret;
}

function mongo_to_map($iterator, $idKey = '_id')
{
	$ret = array();

	foreach($iterator as $a)
	{
		$ret[(string)$a[$idKey]] = mongo_to_array($a);
	}

	return $ret;
}

function mongo_to_array($d)
{
	if($d === null)
	{
		return array();
	}
	else if(!($d instanceof \MongoDB\Model\BSONDocument) && !($d instanceof \MongoDB\Model\BSONArray) && !is_array($d))
	{
		throw new IllegalArgumentException("Input is ".get_class($d));
	}

	if(isset($d['$id']) && isset($d['$ref']))
	{
		return (string)$d['$id'];
	}

	$a = array();

	foreach($d as $k => $v)
	{
		if(is_scalar($v) || $v === null)
		{
			$a[$k] = $v;
		}
		else if($v instanceof \MongoDB\Model\BSONArray)
		{
			$a[$k] = mongo_array_to_array($v);
		}
		else if($v instanceof \MongoDB\BSON\UTCDateTime)
		{
			$a[$k] = (int)(string) $v; // FIXME: use a datetime object instead!
		}
		else if($v instanceof \MongoDB\Model\BSONDocument)
		{
			$a[$k] = mongo_to_array($v);
		}
		else
		{
			$a[$k] = (string)$v;
		}
	}

	return $a;
}

function mongo_array_to_array($d)
{
	if($d === null)
	{
		return array();
	}
	else if(!($d instanceof \MongoDB\Model\BSONArray))
	{
		throw new IllegalArgumentException("Input is ".get_class($d));
	}

	$a = array();

	foreach($d as $v)
	{
		if(is_scalar($v) || $v === null)
		{
			$a[] = $v;
		}
		else if($v instanceof \MongoDB\Model\BSONArray)
		{
			$a[] = mongo_array_to_array($v);
		}
		else if($v instanceof \MongoDB\BSON\UTCDateTime)
		{
			$a[] = (int)(string) $v;
		}
		else if($v instanceof \MongoDB\Model\BSONDocument)
		{
			$a[] = mongo_to_array($v);
		}
		else
		{
			$a[] = (string)$v;
		}
	}

	return $a;
}

function mongo_to_human($d)
{
	if($d === null)
	{
		return array();
	}
	else if(!($d instanceof \MongoDB\Model\BSONDocument) && !($d instanceof \MongoDB\Model\BSONArray) && !is_array($d))
	{
		throw new IllegalArgumentException("Input is ".get_class($d));
	}

	if(isset($d['$id']) && isset($d['$ref']))
	{
		return (string)$d['$id'];
	}

	$a = array();

	foreach($d as $k => $v)
	{
		if(is_bool($v))
		{
			$a[$k] = $v ? 1 : 0;
		}
		else if(is_scalar($v) || $v === null)
		{
			$a[$k] = $v;
		}
		else if($v instanceof \MongoDB\Model\BSONArray)
		{
			$a[$k] = join(", ", mongo_array_to_human($v));
		}
		else if($v instanceof \MongoDB\BSON\UTCDateTime)
		{
			$a[$k] = $v->toDateTime()->format('Y-m-d H:i:s');
		}
		else if($v instanceof \MongoDB\Model\BSONDocument)
		{
			// TODO
			$a[$k] = json_encode(mongo_to_human($v));
		}
		else
		{
			$a[$k] = (string)$v;
		}
	}

	return $a;
}

function mongo_array_to_human($d)
{
	if($d === null)
	{
		return array();
	}
	else if(!($d instanceof \MongoDB\Model\BSONArray))
	{
		throw new IllegalArgumentException("Input is ".get_class($d));
	}

	$a = array();

	foreach($d as $v)
	{
		if(is_bool($v))
		{
			$a[] = $v ? 1 : 0;
		}
		else if(is_scalar($v) || $v === null)
		{
			$a[] = $v;
		}
		else if($v instanceof \MongoDB\Model\BSONArray)
		{
			$a[] = join(", ", mongo_array_to_human($v));
		}
		else if($v instanceof \MongoDB\BSON\UTCDateTime)
		{
			$a[] = $v->toDateTime()->format('Y-m-d H:i:s');
		}
		else if($v instanceof \MongoDB\Model\BSONDocument)
		{
			// TODO
			$a[] = json_encode(mongo_to_human($v));
		}
		else
		{
			$a[] = (string)$v;
		}
	}

	return $a;
}

function to_oid($id)
{
	if(is_scalar($id))
	{
		return new \MongoDB\BSON\ObjectId($id);
	}
	else if($id instanceof \MongoDB\BSON\ObjectId)
	{
		return $id;
	}
	else if($id === null)
	{
		return null;
	}

	throw new IllegalArgumentException("Invalid OID");
}

function oid_eq($a, $b)
{
	try
	{
		return !empty($a) && !empty($b) && (string) to_oid($a) === (string) to_oid($b);
	}
	catch(\Exception $e)
	{
		return false;
	}
}

function to_oid_array($ids)
{
	return array_values(array_map('to_oid', $ids));
}

function oid_in_query($v, $not = false)
{
	if(is_array($v))
	{
		if(count($v) === 1)
		{
			$v = current($v);
		}
		else
		{
			return [ ($not ? '$nin' : '$in') => to_oid_array(array_values($v)) ];
		}
	}

	if($not)
	{
		return [ '$ne' => to_oid($v) ];
	}

	return to_oid($v);
}

function in_query($v, $not = false, $unique = false)
{
	if(is_array($v))
	{
		if($unique)
		{
			$v = array_unique($v, SORT_REGULAR);
		}

		if(count($v) === 1)
		{
			$v = current($v);
		}
		else
		{
			return [ ($not ? '$nin' : '$in') => array_values($v) ];
		}
	}

	if($not)
	{
		return [ '$ne' => $v ];
	}

	return $v;
}

function op_query($op, $v)
{
	if(is_array($v))
	{
		if(count($v) === 1)
		{
			$v = current($v);
		}
		else
		{
			return [ $op => $v ];
		}
	}

	return $v;
}

function get_mongo_collation()
{
	return [
		'locale' => explode('_', get_current_locale())[0],
		'caseFirst' => 'off',
		'caseLevel' => false,
		'strength' => 3,
		'numericOrdering' => true,
//		'alternate' => 'shifted',
		'alternate' => 'non-ignorable',
		'maxVariable' => 'punct',
		'backwards' => false,
	];
}

function mongo_to_model($a, $model)
{
	return new $model($a);
}

function mongo_to_model_array($results, $model)
{
	return to_model_array(iterator_to_array($results), $model);
}

function to_model_array($results, $model)
{
	$ret = array();

	foreach($results as $a)
	{
		$m = new $model($a);

		$ret[$m->getId()] = $m;
	}

	return $ret;
}

function json_query_to_mongo($a)
{
	$ret = $a;

	if(is_array($a))
	{
		$ret = array();

		foreach($a as $k => $v)
		{
			if(strpos($k, '#') === 0)
			{
				$k = '$'.substr($k, 1);
			}

			$ret[$k] = json_query_to_mongo($v);
		}
	}
	else if($a instanceof stdClass)
	{
		$ret = new stdClass();

		foreach(get_object_vars($a) as $k => $v)
		{
			if(strpos($k, '#') === 0)
			{
				$k = '$'.substr($k, 1);
			}

			$ret->$k = json_query_to_mongo($v);
		}
	}
	else if(is_scalar($a))
	{
		$matches = array();

		if(preg_match('|#dfDateTime\((.*?)\)|', $a, $matches))
		{
			$ret = new \MongoDB\BSON\UTCDateTime(strtotime($matches[1]) * 1000);
		}
		else if($a === '#dfCurrentDateTime') // deprecated
		{
			$ret = date('c');
		}
	}

	return $ret;
}

function mongo_search_regex($s, $clean = false)
{
	if($clean)
	{
		$s = str_replace([ '.', ',', ';', ':', '"', "'" ], '', $s);
	}

	$s = array_filter(array_map("trim", explode(" ", $s)), fn($a) => $a !== '');
	$s = join(" ", array_map(fn($a) => '.*'.preg_quote($a).'.*', $s));

	return new \MongoDB\BSON\Regex($s, 'i');
}

function pg_search_like($s, $clean = false)
{
	if($clean)
	{
		$s = str_replace([ '.', ',', ';', ':', '"', "'" ], '', $s);
	}

	$s = array_filter(array_map("trim", explode(" ", $s)), fn($a) => $a !== '');
	$s = '%'.join("%", $s).'%';

	return $s;
}

function mongo_prefix_object($prefix, $object, $args = [])
{
	@[
		'include' => $include,
		'exclude' => $exclude,
	] = $args;

	$input =
	[
		'$objectToArray' => $object,
	];

	if(!empty($include) && !empty($exclude))
	{
		throw new IllegalArgumentException();
	}
	else if(!empty($include) || !empty($exclude))
	{
		$input =
		[
			'$filter' =>
			[
				'input' => $input,
				'as' => 'el',
				'cond' => !empty($include) ? [ '$in' => [ '$$el.k', $include ] ] : [ '$nin' => [ '$$el.k', $exclude ] ],
			],
		];
	}

	return [
		'$arrayToObject' =>
		[
			'$map' =>
			[
				'input' => $input,
				'as' => 'el',
				'in' =>
				[
					'k' => [ '$concat' => [ $prefix, [ '$cond' => [ [ '$eq' => [ '$$el.k', '_id' ] ], 'id', '$$el.k' ] ] ] ],
					'v' => '$$el.v',
				],
			],
		],
	];
}

function mongo_merge_with_root($fields)
{
	return [
		[
			'$replaceRoot' =>
			[
				'newRoot' =>
				[
					'$mergeObjects' => array_merge([ '$$ROOT' ], array_map(fn($a) => '$'.$a, $fields)),
				],
			],
		],
		[
			'$project' => array_map(fn($a) => 0, array_flip($fields)),
		],
	];
}

function mongo_sync_scope(\MongoDB\Collection $collection, \MongoDB\Collection $foreignCollection, $rawFilters, $localField, $additionalProjeciton = [])
{
	$results = $collection->aggregate(
	[
		[
			'$match' => $rawFilters,
		],
		[
			'$lookup' =>
			[
				'from' => $foreignCollection->getCollectionName(),
				'localField' => $localField,
				'foreignField' => '_id',
				'as' => '_entity',
			],
		],
		[
			'$unwind' =>
			[
				'path' => '$_entity',
				'preserveNullAndEmptyArrays' => true,
			],
		],
		[
			'$project' => array_merge($additionalProjeciton,
			[
				'_id' => '$_id',
				'scope' =>
				[
					'$concatArrays' =>
					[
						[ '$_id' ],
						[ '$ifNull' => [ '$_entity.scope', [] ] ],
					],
				],
			]),
		],
	]);

/*
		[
			'$merge' =>
			[
				'into' => $collection->getCollectionName(),
				'on' => '_id',
				'whenMatched' => 'merge',
				'whenNotMatched' => 'discard',
			],
		],
*/

	return mongo_bulk($results, $collection, [ 'ordered' => false ], 5000, function($a, &$ctx)
	{
		return [
			'updateOne' =>
			[
				[
					'_id' => to_oid($a['_id']),
				],
				[
					'$set' => array_filter((array)$a, fn($k) => $k !== '_id', ARRAY_FILTER_USE_KEY),
				],
			],
		];
	});
}

function mongo_bulk($input, \MongoDB\Collection $collection, ?array $opts, ?int $batchSize, \Closure $fn, ?\Closure $postQueryFn = null, ?\Closure $errorHandlerFn = null)
{
	$start = mtime();

	if($batchSize === null)
	{
		$batchSize = 5000;
	}

	$ret =
	[
		'results' => [],
		'stats0' =>
		[
			'duration' => 0,
			'queries' => 0,
			'deleted' => 0,
			'inserted' => 0,
			'matched' => 0,
			'modified' => 0,
			'upserted' => 0,
			'skipped' => 0,
			'errors' => 0,
		],
	];

	$queries = [];
	$ctx = [];

	$process = function(&$queries, &$ctx) use(&$ret, $collection, $opts, $postQueryFn, $errorHandlerFn)
	{
		$result = null;

		try
		{
			$result = $collection->bulkWrite($queries, $opts);
		}
		catch(\MongoDB\Driver\Exception\BulkWriteException $e)
		{
			$result = $e->getWriteResult();

			if($errorHandlerFn === null || empty($result->getWriteErrors()) || $result->getWriteConcernError())
			{
				throw $e;
			}
		}

		$ret['results'][] = $result;

		$retry = false;
		$errorQueries = null;
		$errorCtx = null;

		if($result instanceof WriteResult && !empty($result->getWriteErrors()))
		{
			$errorCtx = [];
			$errorQueries = [];
			$errors = [];
			foreach($result->getWriteErrors() as $error)
			{
				$errorCtx[$error->getIndex()] = $ctx[$error->getIndex()];
				$errorQueries[$error->getIndex()] = $queries[$error->getIndex()];
				$errors[$error->getIndex()] = $error;
			}

			trace0("Write errors are {}", array_map(fn($a) => $a->getMessage(), $result->getWriteErrors()));
			trace0("Write errors queries are {}", $errorQueries);

			$ret['stats0']['errors'] += count($errorQueries);

			$ctx = array_diff_key($ctx, $errorCtx);

			if($errorHandlerFn === true)
			{
				// ignore errors
			}
			else
			{
				$retry = $errorHandlerFn($errorCtx, $errorQueries, $errors) === true;
			}
		}

		if($postQueryFn)
		{
			$postQueryFn($ctx, $errorCtx);
		}

		$ret['stats0']['queries'] += count($queries);

		if($retry)
		{
			expectEmpty(array_diff_key($errorQueries, $errorCtx));

			$queries = array_values($errorQueries);
			$ctx = array_values($errorCtx);
		}
		else
		{
			$queries = [];
			$ctx = [];
		}
	};

	foreach($input as $k => $a)
	{
		$query = $fn($a, $ctx, $k);

		if($query === null)
		{
			$ret['stats0']['skipped']++;
			continue;
		}
		else if($query instanceof \Generator)
		{
			foreach($query as $query0)
			{
				$queries[] = $query0;
			}
		}
		else
		{
			$queries[] = $query;
		}

		if(count($queries) >= $batchSize)
		{
			$process($queries, $ctx);
		}
	}

	while(!empty($queries))
	{
		$process($queries, $ctx);
	}

	foreach($ret['results'] as $a)
	{
		$ret['stats0']['deleted']  += $a->getDeletedCount();
		$ret['stats0']['inserted'] += $a->getInsertedCount();
		$ret['stats0']['matched']  += $a->getMatchedCount();
		$ret['stats0']['modified'] += $a->getModifiedCount();
		$ret['stats0']['upserted'] += $a->getUpsertedCount();
	}

	$ret['stats0']['duration'] = mtime() - $start;
	$ret['stats'] = array_filter($ret['stats0'], fn($a) => $a > 0);

	return $ret;
}

function mongo_transaction(\MongoDB\Client $client, \Closure $callback)
{
	$error = null;

	for($i = 0; $i < 10; $i++)
	{
		$session = $client->startSession();

		try
		{
			$callback($session);
			return;
		}
		catch(\MongoDB\Driver\Exception\BulkWriteException $e)
		{
			if($e->getCode() !== 112)
			{
				throw $e;
			}

			warn("Write conflict: {}", $e);

			$error = $e;

			usleep($i * \rand(100, 500) * 1000);
		}
		finally
		{
			$session->endSession();
		}
	}

	throw $error;
}

function maybe_mongo_transaction(\MongoDB\Client $client, ?\MongoDB\Driver\Session $session, \Closure $callback)
{
	if($session)
	{
		$callback($session);
	}
	else
	{
		mongo_transaction($client, function($session) use($callback)
		{
			$session->startTransaction();

			$callback($session);

			$session->commitTransaction();
		});
	}
}

function mongo_convert_timezone($input, $timeZone)
{
	return [
		'$dateFromString' =>
		[
			'dateString' =>
			[
				'$dateToString' =>
				[
					'date' => $input,
					'format' => '%Y-%m-%dT%H:%M:%S.%L',
				],
			],
			'timezone' => $timeZone,
		],
	];
}

function array_to_mongo_switch($field, $array, $default = null)
{
	return [
		'$switch' => array_filter_nulls(
		[
			'branches' => array_to_mongo_switch0($field, $array),
			'default' => $default,
		]),
	];
}

function array_to_mongo_switch0($field, $array)
{
	$ret = [];

	foreach($array as $k => $v)
	{
		$ret[] =
		[
			'case' => [  '$eq' => [ '$'.$field, $k ] ],
			'then' => $v,
		];
	}

	return $ret;
}

function encode_mongo_key($input)
{
	return str_replace(
	[
		'¤',
		'.',
		'$',
		'\\',
	],
	[
		'¤¤',
		'¤d',
		'¤m',
		'¤e',
	],
	$input);
}

function decode_mongo_key($input)
{
	$input = preg_replace('#(?<!¤)(¤¤)*¤d#', '$1.', $input);
	$input = preg_replace('#(?<!¤)(¤¤)*¤m#', '$1$', $input);
	$input = preg_replace('#(?<!¤)(¤¤)*¤e#', '$1\\', $input);
	$input = str_replace('¤¤', '¤', $input);

	return $input;
}

function encode_mongo_keys($input)
{
	$ret = [];

	if(is_array($input))
	{
		foreach($input as $k => $v)
		{
			$k = is_numeric($k) ? $k : encode_mongo_key($k);
			$v = is_array($v) ? encode_mongo_keys($v) : $v;

			$ret[$k] = $v;
		}
	}

	return $ret;
}

function decode_mongo_keys($input)
{
	$ret = [];

	if(is_array($input))
	{
		foreach($input as $k => $v)
		{
			$k = is_numeric($k) ? $k : decode_mongo_key($k);
			$v = is_array($v) ? decode_mongo_keys($v) : $v;

			$ret[$k] = $v;
		}
	}

	return $ret;
}

function random_object_id()
{
	return new \MongoDB\BSON\ObjectId('00'.bin2hex(random_bytes(11)));
}
