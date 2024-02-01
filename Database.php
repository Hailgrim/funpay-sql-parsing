<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;
    private string $skip_token = '#SKIP_TOKEN#';

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    private function parseNumeric(mixed $arg): string | null
    {
        if ($arg === $this->skip_token || is_null($arg)) {
            return 'NULL';
        } elseif (is_numeric($arg)) {
            return strval($arg);
        } elseif (is_bool($arg)) {
            return $arg ? '1' : '0';
        }

        return null;
    }

    private function parseFloat(mixed $arg): string | null
    {
        if ($arg === $this->skip_token || is_null($arg)) {
            return 'NULL';
        } elseif (is_float($arg)) {
            return strval($arg);
        }

        return null;
    }

    private function parseArray(mixed $arg): string | null
    {
        $result = [];
        $i = 0;

        if ($arg === $this->skip_token) {
            return null;
        } elseif (is_array($arg)) {
            foreach ($arg as $key => $value) {
                if ($key === $i) {
                    $result[] = strval($value);
                } else {
                    $parsed_value = $this->parseMixed($value);
                    if (is_string($parsed_value)) {
                        $result[] = '`' . $key . '` = ' . $parsed_value;
                    } else {
                        return null;
                    }
                }
                $i++;
            }
        }

        return count($result) > 0 ? implode(', ', $result) : null;
    }

    private function parseKeys(mixed $arg): string | null
    {
        $result = [];

        if ($arg === $this->skip_token) {
            return '*';
        } elseif (is_string($arg)) {
            $result[] = '`' . $arg . '`';
        } elseif (is_array($arg)) {
            foreach ($arg as $element) {
                if (is_string($element)) {
                    $result[] = '`' . $element . '`';
                }
            }
        }

        return count($result) > 0 ? implode(', ', $result) : null;
    }

    private function parseMixed(mixed $arg): string | null
    {
        if ($arg === $this->skip_token || is_null($arg)) {
            return 'NULL';
        } elseif (is_bool($arg)) {
            return $arg ? '1' : '0';
        } elseif (is_int($arg) || is_float($arg)) {
            return strval($arg);
        } elseif (is_string($arg)) {
            return '\'' . $arg . '\'';
        }

        return null;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $result = '';
        $i = 0;
        $arg_i = 0;
        $condition_from = -1;
        $condition_state = true;

        while ($i < strlen($query)) {
            // handle conditional blocks
            if ($query[$i] === '{') {
                $condition_from = strlen($result);
                $i++;
                if ($i >= strlen($query)) {
                    throw new Exception('Invalid conditional blocks.');
                }
            } elseif ($query[$i] === '}') {
                if (!$condition_state) {
                    $result = substr($result, 0, $condition_from);
                    $condition_state = true;
                }
                $condition_from = -1;
                $i++;
                if ($i >= strlen($query)) {
                    break;
                }
            }

            // handle specifiers
            if ($query[$i] === '?') {
                if ($condition_from > -1 && $args[$arg_i] === $this->skip_token) {
                    $condition_state = false;
                }

                switch ($query[$i + 1]) {
                    case 'd':
                        // case for '?d'
                        $result .= $this->parseNumeric($args[$arg_i]) ?? throw new Exception('Wrong numeric type.');
                        $i++;
                        break;

                    case 'f':
                        // case for '?f'
                        $result .= $this->parseFloat($args[$arg_i]) ?? throw new Exception('Wrong float type.');
                        $i++;
                        break;

                    case 'a':
                        // case for '?a'
                        $result .= $this->parseArray($args[$arg_i]) ?? throw new Exception('Wrong array type.');
                        $i++;
                        break;

                    case '#':
                        // case for '?#'
                        $result .= $this->parseKeys($args[$arg_i]) ?? throw new Exception('Wrong keys type.');
                        $i++;
                        break;

                    default:
                        // case for '?'
                        $result .= $this->parseMixed($args[$arg_i]) ?? throw new Exception('Wrong mixed type.');
                        break;
                }

                $arg_i++;
            } else {
                $result .= $query[$i];
            }

            $i++;
        }

        // additional check of conditional blocks after the while loop
        if ($condition_from > -1) {
            throw new Exception('Conditional blocks are not closed.');
        }

        return $result;
    }

    public function skip()
    {
        return $this->skip_token;
    }
}
