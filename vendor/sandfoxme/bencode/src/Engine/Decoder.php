<?php

namespace SandFox\Bencode\Engine;

use SandFox\Bencode\Exceptions\InvalidArgumentException;
use SandFox\Bencode\Exceptions\ParseErrorException;
use SandFox\Bencode\Util\Util;

/**
 * Class Decoder
 * @package SandFox\Bencode\Engine
 * @author Anton Smirnov
 * @license MIT
 */
class Decoder
{
    private $stream;
    private $decoded;
    private $options;

    private $state;
    private $stateStack;
    private $value;
    private $valueStack;

    const STATE_ROOT = 1;
    const STATE_LIST = 2;
    const STATE_DICT = 3;

    const DEFAULT_OPTIONS = [
        'listType' => 'array',
        'dictType' => 'array',
        'useGMP' => false,
    ];

    public function __construct($stream, array $options = [])
    {
        Util::detectMbstringOverload();

        $this->stream = $stream;
        $this->options = array_merge(self::DEFAULT_OPTIONS, $options);

        if (!is_resource($this->stream) || get_resource_type($this->stream) !== 'stream') {
            throw new InvalidArgumentException('Input is not a valid stream');
        }
    }

    public function decode()
    {
        $this->state        = self::STATE_ROOT;
        $this->stateStack   = [];
        $this->decoded      = null;
        $this->valueStack   = [];

        while (!feof($this->stream)) {
            $this->processChar();
        }

        if ($this->state !== self::STATE_ROOT || $this->decoded === null) {
            throw new ParseErrorException('Unexpected end of file');
        }

        return $this->decoded;
    }

    private function processChar()
    {
        $c = fread($this->stream, 1);

        if (feof($this->stream) || $c === '') {
            return;
        }

        if ($this->decoded !== null && $this->state === self::STATE_ROOT) {
            throw new ParseErrorException('Probably some junk after the end of the file');
        }

        switch ($c) {
            case 'i':
                $this->processInteger();
                return;

            case 'l':
                $this->push(self::STATE_LIST);
                return;

            case 'd':
                $this->push(self::STATE_DICT);
                return;

            case 'e':
                $this->finalizeContainer();
                return;

            default:
                $this->processString();
        }
    }

    private function readInteger(string $delimiter)
    {
        $pos = ftell($this->stream);
        $readLength = 32; // More than enough for 64 bit int (19 digits + minus + delimiter)

        do {
            fseek($this->stream, $pos, SEEK_SET);
            $int = fread($this->stream, $readLength);

            $position = strpos($int, $delimiter);

            if ($position !== false) {
                $int = substr($int, 0, $position);
                fseek($this->stream, $pos + $position + 1, SEEK_SET);

                return $int;
            }

            fread($this->stream, 1); // trigger feof
            $readLength *= 32; // grow exponentially
        } while (!feof($this->stream) && $readLength < PHP_INT_MAX);

        return false;
    }

    private function processInteger()
    {
        $intStr = $this->readInteger('e');

        if ($intStr === false) {
            throw new ParseErrorException("Unexpected end of file while processing integer");
        }

        if (!is_numeric($intStr)) {
            throw new ParseErrorException("Invalid integer format or integer overflow: '{$intStr}'");
        }

        $int = (int)$intStr;

        if ((string)$int === $intStr) {
            $this->finalizeScalar($int);
            return;
        }

        if ($this->options['useGMP']) {
            $int = gmp_init($intStr);

            if ((string)$int === $intStr) {
                $this->finalizeScalar($int);
                return;
            }
        }

        if ((string)$int !== $intStr) {
            throw new ParseErrorException("Invalid integer format or integer overflow: '{$intStr}'");
        }
    }

    private function processString()
    {
        // rewind back 1 character because it's a part of string length
        fseek($this->stream, -1, SEEK_CUR);

        $lenStr = $this->readInteger(':');

        if ($lenStr === false) {
            throw new ParseErrorException('Unexpected end of file while processing string');
        }

        $len = (int)$lenStr;

        if ((string)$len !== $lenStr || $len < 0) {
            throw new ParseErrorException("Invalid string length value: '{$lenStr}'");
        }

        // we have length, just read all string here now

        $str = $len === 0 ? '' : fread($this->stream, $len);

        if (strlen($str) !== $len) {
            throw new ParseErrorException('Unexpected end of file while processing string');
        }

        $this->finalizeScalar($str);
    }

    private function finalizeContainer()
    {
        switch ($this->state) {
            case self::STATE_LIST:
                $this->finalizeList();
                break;

            case self::STATE_DICT:
                $this->finalizeDict();
                break;

            default:
                // @codeCoverageIgnoreStart
                // This exception means that we have a bug in our own code
                throw new ParseErrorException('Parser entered invalid state while finalizing container');
                // @codeCoverageIgnoreEnd
        }
    }

    private function finalizeList()
    {
        $value = $this->convertArrayToType($this->value, 'listType');

        $this->pop($value);
    }

    private function finalizeDict()
    {
        $dict = [];

        $prevKey = null;

        // we have an array [key1, value1, key2, value2, key3, value3, ...]
        while (count($this->value)) {
            $dictKey = array_shift($this->value);
            if (is_string($dictKey) === false) {
                throw new ParseErrorException('Non string key found in the dictionary');
            }
            if (count($this->value) === 0) {
                throw new ParseErrorException("Dictionary key without corresponding value: '{$dictKey}'");
            }
            if ($prevKey && strcmp($prevKey, $dictKey) >= 0) {
                throw new ParseErrorException("Invalid order of dictionary keys: '{$dictKey}' after '{$prevKey}'");
            }
            $dictValue = array_shift($this->value);

            $dict[$dictKey] = $dictValue;
            $prevKey = $dictKey;
        }

        $value = $this->convertArrayToType($dict, 'dictType');

        $this->pop($value);
    }

    /**
     * Push previous layer to the stack and set new state
     * @param int $newState
     */
    private function push(int $newState)
    {
        array_push($this->stateStack, $this->state);
        $this->state = $newState;

        if ($this->state !== self::STATE_ROOT) {
            array_push($this->valueStack, $this->value);
        }
        $this->value = [];
    }

    /**
     * Send parsed value to the current container
     * @param mixed $value
     */
    private function finalizeScalar($value)
    {
        if ($this->state !== self::STATE_ROOT) {
            $this->value[] = $value;
        } else {
            // we have final result
            $this->decoded = $value;
        }
    }

    /**
     * Pop previous layer from the stack and give it a parsed value
     * @param mixed $valueToPrevLevel
     */
    private function pop($valueToPrevLevel)
    {
        $this->state = array_pop($this->stateStack);

        if ($this->state !== self::STATE_ROOT) {
            $this->value = array_pop($this->valueStack);
            $this->value[] = $valueToPrevLevel;
        } else {
            // we have final result
            $this->decoded = $valueToPrevLevel;
        }
    }

    private function convertArrayToType(array $array, string $typeOption)
    {
        $type = $this->options[$typeOption];

        if ($type === 'array') {
            return $array;
        }

        if ($type === 'object') {
            return (object)$array;
        }

        if (is_callable($type)) {
            return call_user_func($type, $array);
        }

        if (class_exists($type)) {
            return new $type($array);
        }

        throw new InvalidArgumentException(
            "Invalid type option for '{$typeOption}'. Type should be 'array', 'object', class name, or callback"
        );
    }
}
