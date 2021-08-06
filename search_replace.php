<?php


require_once 'DataBasePDO.php';
require_once 'DataBasePDOStatement.php';
require_once 'serializer.php';
require_once 'FileVisitor.php';


/**
 * @param DataBase $connection
 *
 * @return array
 *
 */
function listTables($connection)
{
    try {
        $tables = $connection->query('SELECT `table_name` as `name`, `update_time` as `updatedAt`, `data_length` AS `dataSize`, `table_rows` as `rowCount`
                                      FROM information_schema.TABLES 
                                      WHERE table_schema = :db_name AND table_type = :table_type AND engine IS NOT NULL
                                      ORDER BY `table_name` ', array(
            // The NULL `engine` tables usually have `table_comment` == "Table 'forrestl_wrdp1.wp_wpgmza_categories' doesn't exist in engine".
            'db_name'    => $connection->name,
            'table_type' => 'BASE TABLE',
        ))->fetchAll();
    } catch (Exception $e) {
        return array(
            'error' => $e->getMessage(),
            'ok'    => false,
        );
    }
    if (empty($tables)) {
        return array(
            'error' => "Empty database.",
            'ok'    => false,
        );
    }
    $result = array();
    foreach ($tables as $table) {
        $primaryKeyData = $connection->query("SHOW KEYS FROM `" . $table['name'] . "` WHERE Key_name = 'PRIMARY'")->fetch();
        $primaryKey     = false;
        if (!empty($primaryKeyData)) {
            $primaryKey = $primaryKeyData['Column_name'];
        }
        $columnsData = $connection->query("SHOW FULL COLUMNS FROM `" . $table['name'] . "`")->fetchAll();
        $columns      = [];
        foreach ($columnsData as $column) {
            //dodaj charset
            if (strpos($column['Type'], "varchar") === false && strpos($column['Type'], "longtext")) {
                continue;
            }
            $columns[] = array('field' => $column['Field'], 'collation' => $column['Collation']);
        }
        if (empty($columns)) {
            continue;
        }
        $result[]   = array(
            'name'       => $table['name'],
            'dataSize'   => (int)$table['dataSize'],
            'skip'       => false,
            'rowCount'   => $table['rowCount'],
            'updatedAt'  => $table['updatedAt'],
            'primaryKey' => $primaryKey,
            'columns'    => $columns
        );
    }
    return array(
        'ok'     => true,
        'tables' => $result,
    );
}

/**
 * @param array  $credentials
 *
 * @return DataBase
 * @throws DataBaseException
 */
function databaseConnection($credentials)
{
    if (extension_loaded('pdo_mysql') && PHP_VERSION_ID > 50206) {
        // We need PHP 5.2.6 because of this nasty PDO bug: https://bugs.php.net/bug.php?id=44251
        return new DataBasePDO($credentials);
    } else if (extension_loaded('mysqli')) {
        return new DataBaseMySQLi($credentials);
    }
    else {
        throw new DataBaseException("No drivers available for php mysql connection.");
    }
}

function getTables($dbData)
{
    if (empty($dbData) || empty($dbData['dbUser']) || empty($dbData['dbPassword']) || empty($dbData['dbName']) || empty($dbData['dbHost'])) {
        return array(
            'error' => "Database parameters missing",
            'ok'    => false,
        );
    }
    $connection = databaseConnection($dbData);
    $result     = listTables($connection);
    $connection->close();
    return $result;
}

function searchTables($dbData, $searchString, $excludeTables, $limit, $state) {

    if (empty($dbData) || empty($dbData['dbUser']) || empty($dbData['dbPassword']) || empty($dbData['dbName']) || empty($dbData['dbHost'])) {
        return array(
            'error' => "Database parameters missing",
            'ok'    => false,
        );
    }
    $startedAT  = microtime(true);
    $connection = databaseConnection($dbData);
    $allTables  = listTables($connection);

    if (!$allTables['ok']) {
        return array(
            'error' => $allTables['error'],
            'ok'    => false,
        );
    }
    $result  = array();
    $count   = 0;
    $visitor = new FileVisitor();
    for ($pointer=$state['tablePointer']; $pointer<count($allTables['tables']); $pointer++) {
        $table = $allTables['tables'][$pointer];
        $offset = $state['tableOffset'];
        if (isset($excludeTables[$table['name']]) || $table['skip' ] || $table['dataSize'] === 0 || $table['primaryKey'] === false) {
            continue;
        }

        $rowCount = 0;
        $fields   = "`" . $table['columns'][0]['field'] . "`";
        for ($i = 1; $i<count($table['columns']); $i++)  {
            $fields .= ", `" . $table['columns'][$i]['field'] . "`";
        }
        /** if we get mysql gone away error we will try to run query again with decreased query limit */
        $try       = true;
        $tryNumber = 0;
        while ($try && $tryNumber < 10) {
            try {
                $try = false;
                $tryNumber++;
                $found      = array();
                $endOfTable = false;
                while (!$endOfTable) {
                    $endOfTable  = true;
                    $content = $connection->query(
                        "SELECT `".$table['primaryKey']."`, ".$fields."
                           FROM  `".$table['name']."` LIMIT ".$offset.", ".$limit.";");
                    while ($row = $content->fetch()) {
                        $endOfTable = false;
                        foreach ($row as $key => $cell) {
                            // unapredi uporedjivanje stringova
                            if (strpos($cell, $searchString) === false) {
                                continue;
                            }
                            $count++;
                            $rowCount++;
                            $visitor->writeResult(['table_name'=> $table['name'], 'field_name' => $key, 'value' => $cell, 'primaryKey' => $table['primaryKey'], 'identifier' => $row[$table['primaryKey']]]);
                        }
                    }
                    $offset += $limit;
                }
            } catch (Exception $e) {
                /// check for error mysql gone away and decrease limit number;
                $try     = false;
                $found[] = ['error' => $e->getMessage()];
            }
        }
        if (empty($found)) {
            continue;
        }
        $result['result'][$table['name']]['count'] = $rowCount;
    }

    $finishedAt = microtime(true);
    $time = number_format( floatval( $finishedAt ) - floatval( $startedAT ), 8 );
    if ( $time < 0 ) {
        $time = $time * - 1;
    }

    $result['finishAt']  = $finishedAt;
    $result['startedAt']  = $startedAT;
    $result['time']  = $time;
    $result['count'] = $count;

    return $result;
}

function searchReplaceTables($dbData, $searchString, $replaceString, $data)
{
    if (empty($dbData) || empty($dbData['dbUser']) || empty($dbData['dbPassword']) || empty($dbData['dbName']) || empty($dbData['dbHost'])) {
        return array(
            'error' => "Database parameters missing",
            'ok'    => false,
        );
    }
    $connection    = databaseConnection($dbData);
    $count         = 0;
    $result        = array();
    $handler = fopen('result.txt', 'r');
    while ($line = fgets($handler)) {
        $data = json_decode($line, true);
        $fieldValue   = $data['value'];
        $updatedValue = "";
        if (in_array(substr($fieldValue, 0, 2), array('S:', 's:', 'a:', 'O:'), true)) {
            try {
                $updatedValue = cloner_serialized_replace($searchString, $fieldValue, $replaceString, $count);
                if (!$count) {
                    continue;
                }
                $isSerialized = true;
            } catch (Exception $e) {
            }
        }
        $isJSON = false;
        if (!$isSerialized && cloner_maybe_json_decode($fieldValue)) {
            $refs    = array();
            $updated = cloner_structure_walk_recursive('cloner_preg_replace', $fieldValue, $refs, $searchString, $replaceString);
            if (!$updated) {
                continue;
            }
            $updatedValue = json_encode($fieldValue);
            $isJSON       = true;
        }
        if (!$isJSON && !$isSerialized) {
            // unapredi
            $updatedValue = str_replace($searchString, $replaceString, $fieldValue);
        }
        if (empty($updatedValue)) {
            continue;
        }
        $updateSql    = sprintf("UPDATE `{$data['table_name']}` SET `{$data['field_name']}` = %s WHERE `{$data['primaryKey']}` = %s", $connection->escape($updatedValue), $connection->escape($data['identifier']));
        var_dump($updateSql);
        $count++;
        //$updateResult = $connection->query($updateSql);
        //$result[$tableName]['updateResult'][] = $updateResult;
        $result[$data['table_name']]['updateResult'][] = true;
    }

    return $result;

}


class InsufficientArgumentError extends Exception
{
    public $message = '';
    public function __construct($message)
    {
        parent::__construct("Insufficient argument" . $message);
    }
}

class DataBaseException extends Exception
{
    public $message = '';
    public function __construct($message)
    {
        parent::__construct("Insufficient argument" . $message);
    }
}



