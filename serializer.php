<?php


/**
 * Walks through the PHP serialized structure and replaces strings using
 * preg_replace_callback with the provided callback. It returns the new
 * structure with replacements done and optionally sets the count to
 * total number of done replacements. Returns empty string if the provided
 * argument is an empty string or not a string at all.
 *
 * @param string   $search   Regex to search for in strings.
 * @param string   $data     PHP serialized data.
 * @param callable $callback Callback to use in preg_replace_callback call.
 * @param int      $count    Number of replacements done.
 *
 * @return string
 * @throws ClonerSerializedReaderException
 */
function cloner_serialized_replace($search, $data, $callback, &$count = null)
{
    if ($count === null) {
        $count = 0;
    }
    if (!is_string($data) || strlen($data) === 0) {
        return "";
    }
    return cloner_serialized_replace_internal(new ClonerSerializedReader($data), $search, $callback, $count);
}

/**
 * @throws ClonerSerializedReaderException
 */
function cloner_serialized_replace_internal(ClonerSerializedReader $r, $search, $callback, &$count)
{
    $start = $r->cursor;
    $type  = $r->readByte();
    switch ($type) {
        case 'R':
            // R:1;
        case 'r':
            // r:1;
        case 'b':
            // b:0;
            // b:1;
            /** @noinspection PhpMissingBreakStatementInspection */
        case 'i':
            // i:0;
            $r->readExpect(':');
            $r->readInt();
        case 'N':
            // N;
            $r->readExpect(';');
            return substr($r->data, $start, $r->cursor - $start);
        case 'd':
            // d:1;
            // d:0.1;
            // d:9.223372036854776E+19;
            // d:INF;
            // d:-INF;
            // d:NAN;
            $r->readExpect(':');
            $r->readFloat();
            $r->readExpect(';');
            return substr($r->data, $start, $r->cursor - $start);
        case 'C':
            // C:5:"Test2":6:{foobar}
            $r->readExpect(':');
            $classNameLen = $r->readInt();
            $r->readExpect(':"');
            $r->read($classNameLen);
            $r->readExpect('":');
            $len = $r->readInt();
            $r->readExpect(':{');
            $r->read($len);
            $r->readExpect('}');
            return substr($r->data, $start, $r->cursor - $start);
        /** @noinspection PhpMissingBreakStatementInspection */
        case 'O':
            // O:3:"foo":1:{s:4:"test";s:3:"foo";}
            $r->readExpect(':');
            $classNameLen = $r->readInt();
            $r->readExpect(':"');
            $r->read($classNameLen);
            $r->readExpect('"');
        case 'a':
            // a:1:{i:1;s:3:"foo";}
            $r->readExpect(':');
            $fieldsLen = $r->readInt();
            $r->readExpect(':{');
            $serialized = substr($r->data, $start, $r->cursor - $start);
            $oldCount   = $count;
            for ($i = 0; $i < $fieldsLen; $i++) {
                $serialized .= cloner_serialized_replace_internal($r, $search, null, $count);
                $serialized .= cloner_serialized_replace_internal($r, $search, $callback, $count);
            }
            $r->readExpect('}');
            $serialized .= '}';
            if ($oldCount === $count) {
                // No replacements made, return original substring.
                return substr($r->data, $start, $r->cursor - $start);
            }
            return $serialized;
        default:
            throw new ClonerSerializedReaderException($r->cursor, "unexpected token: $type");
        case 's':
            // s:4:"test";
            $r->readExpect(':');
            $len = $r->readInt();
            $r->readExpect(':"');
            $value = $r->read($len);
            break;
        case 'S':
            // S:3:"\61 b";
            $r->readExpect(':');
            $len = $r->readInt();
            $r->readExpect(':"');
            $value = '';
            while (strlen($value) < $len) {
                $byte = $r->readByte();
                if ($byte === '\\') {
                    $value .= chr(intval($r->read(2), 16));
                    continue;
                }
                $value .= $byte;
            }
            break;
    }
    // Fallthrough 's' and 'S' handling.
    $countReplace = 0;
    if ($callback !== null) {
        $value = preg_replace_callback($search, $callback, $value, -1, $countReplace);
        if ($value === null) {
            $err = error_get_last();
            throw new ClonerSerializedReaderException($r->cursor, $err['message']);
        }
        $count += $countReplace;
    }
    $r->readExpect('";');
    if ($countReplace === 0) {
        return substr($r->data, $start, $r->cursor - $start);
    }
    return sprintf('s:%d:"%s";', strlen($value), $value);
}

class ClonerSerializedReader
{
    public $cursor = 0;
    public $data = '';

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function read($len)
    {
        if ($this->cursor + $len > strlen($this->data)) {
            throw new ClonerSerializedReaderException($this->cursor, sprintf('expected to read %d bytes, only %d remain', $len, strlen($this->data) - $this->cursor));
        }
        $value        = substr($this->data, $this->cursor, $len);
        $this->cursor += $len;
        return $value;
    }

    public function readByte()
    {
        if ($this->cursor >= strlen($this->data)) {
            throw new ClonerSerializedReaderException($this->cursor, 'reached end of stream');
        }
        $byte = $this->data[$this->cursor];
        $this->cursor++;
        return $byte;
    }

    public function readInt()
    {
        // preg_match's $offset option ignores ^, so we use a substring.
        if (!preg_match('{^([+-]?[0-9]+)}', substr($this->data, $this->cursor), $matches)) {
            throw new ClonerSerializedReaderException($this->cursor, 'expected number');
        }
        $this->cursor += strlen($matches[0]);
        return intval($matches[0]);
    }

    public function readFloat()
    {
        // preg_match's $offset option ignores ^, so we use a substring.
        if (!preg_match('{^(?:NAN|-?INF|[+-]?(?:[0-9]+\.[0-9]*|[0-9]*\.[0-9]+|[0-9]+)(?:[eE][+-]?[0-9]+)?)}', substr($this->data, $this->cursor), $matches)) {
            throw new ClonerSerializedReaderException($this->cursor, 'expected number');
        }
        $this->cursor += strlen($matches[0]);
        switch ($matches[0]) {
            case 'INF':
                return INF;
            case '-INF':
                return -INF;
            case 'NAN';
                return NAN;
            default:
                return floatval($matches[0]);
        }
    }

    public function readExpect($expect)
    {
        $got = $this->read(strlen($expect));
        if ($got !== $expect) {
            throw new ClonerSerializedReaderException($this->cursor, sprintf('expected "%s", got "%s"', $expect, $got));
        }
    }
}


/**
 * @param callable $function
 * @param mixed    $structure
 * @param string[] $walkedRefs
 *   SPL object hash map of objects that have been walked through.
 * @param mixed    ...$args
 *
 * @return int Number of updated occurrences.
 * @throws Exception
 */
function cloner_structure_walk_recursive($function, &$structure, &$walkedRefs = array(), $args = null)
{
    $args = func_get_args();
    array_shift($args);
    array_shift($args);
    array_shift($args);

    switch ($type = gettype($structure)) {
        case 'integer':
        case 'boolean':
        case 'float':
        case 'double':
        case 'NULL':
            return 0;
        case 'string':
            return call_user_func_array($function, array_merge(array(&$structure), $args));
        /** @noinspection PhpMissingBreakStatementInspection */
        case 'object':
            if ($structure instanceof Iterator) {
                // PHP error: iterator cannot be used with foreach by reference
                return 0;
            }
            // Handle recursion.
            // __PHP_Incomplete_Class will return false on is_object() call. Luckily, we can still get its object hash.
            $objectHash = spl_object_hash($structure);
            if (isset($walkedRefs[$objectHash])) {
                return 0;
            }
            $walkedRefs[$objectHash] = true;
        // Fall through.
        case 'array':
            $updated = 0;
            // Object and array are by default traversable.
            foreach ($structure as &$value) {
                $updated += call_user_func_array(__FUNCTION__, array_merge(array($function, &$value, &$walkedRefs), $args));
            }

            return $updated;
        default:
            throw new Exception('Unsupported structure passed: '.$type);
    }
}

function cloner_maybe_json_decode(&$value)
{
    if (!is_string($value)) {
        return false;
    }

    $startsWith = substr($value, 0, 1);

    if (in_array($startsWith, array('[', '{'), true)) {
        $newValue = json_decode($value, true);
        if ($newValue !== null || $value === 'null') {
            $value = $newValue;
            return true;
        }
    }
    return false;
}

function cloner_preg_replace(&$value, $search, $replace)
{
    if (!is_string($value)) {
        return 0;
    }
    $value = preg_replace_callback($search, $replace, $value, -1, $count);
    return $count;
}

class ClonerSerializedReaderException extends Exception
{
    public $offset;

    public function __construct($offset, $message)
    {
        $this->offset = $offset;
        parent::__construct(sprintf("cloner_serialized_replace error at offset %d: %s", $offset, $message));
    }
}
