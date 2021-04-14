<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\REST\TinyTinyRSS;

use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Misc\Date;

class Search {
    protected const STATE_BEFORE_TOKEN = 0;
    protected const STATE_BEFORE_TOKEN_QUOTED = 1;
    protected const STATE_IN_DATE = 2;
    protected const STATE_IN_DATE_QUOTED = 3;
    protected const STATE_IN_TOKEN_OR_TAG = 4;
    protected const STATE_IN_TOKEN_OR_TAG_QUOTED = 5;
    protected const STATE_IN_TOKEN = 6;
    protected const STATE_IN_TOKEN_QUOTED = 7;

    protected const FIELDS_BOOLEAN = [
        "unread" => "unread",
        "star"   => "starred",
        "note"   => "annotated",
        "pub"    => "published", // TODO: not implemented
    ];
    protected const FIELDS_TEXT = [
        "title"  => "titleTerms",
        "author" => "authorTerms",
        "note"   => "annotationTerms",
        ""       => "searchTerms",
    ];

    public static function parse(string $search, Context $context = null): ?Context {
        // normalize the input
        $search = strtolower(trim(preg_replace("<\s+>", " ", $search)));
        // set initial state
        $pos = -1;
        $stop = strlen($search);
        $state = self::STATE_BEFORE_TOKEN;
        $buffer = "";
        $tag = "";
        $flag_negative = false;
        $context = $context ?? new Context;
        // process
        try {
            while (++$pos <= $stop) {
                $char = @$search[$pos];
                switch ($state) {
                    case self::STATE_BEFORE_TOKEN:
                        switch ($char) {
                            case "":
                                continue 3;
                            case " ":
                                continue 3;
                            case '"':
                                if ($flag_negative) {
                                    $buffer .= $char;
                                    $state = self::STATE_IN_TOKEN_OR_TAG;
                                } else {
                                    $state = self::STATE_BEFORE_TOKEN_QUOTED;
                                }
                                continue 3;
                            case "-":
                                if (!$flag_negative) {
                                    $flag_negative = true;
                                } else {
                                    $buffer .= $char;
                                    $state = self::STATE_IN_TOKEN_OR_TAG;
                                }
                                continue 3;
                            case "@":
                                $state = self::STATE_IN_DATE;
                                continue 3;
                            case ":":
                                $state = self::STATE_IN_TOKEN;
                                continue 3;
                            default:
                                $buffer .= $char;
                                $state = self::STATE_IN_TOKEN_OR_TAG;
                                continue 3;
                        }
                        // no break
                    case self::STATE_BEFORE_TOKEN_QUOTED:
                        switch ($char) {
                            case "":
                                continue 3;
                            case '"':
                                if (($pos + 1 == $stop) || $search[$pos + 1] === " ") {
                                    $context = self::processToken($context, $buffer, $tag, $flag_negative, false);
                                    $state = self::STATE_BEFORE_TOKEN;
                                    $flag_negative = false;
                                    $buffer = $tag = "";
                                } elseif ($search[$pos + 1] === '"') {
                                    $buffer .= '"';
                                    $pos++;
                                    $state = self::STATE_IN_TOKEN_OR_TAG_QUOTED;
                                } else {
                                    $state = self::STATE_IN_TOKEN_OR_TAG;
                                }
                                continue 3;
                            case "\\":
                                if ($pos + 1 == $stop) {
                                    $buffer .= $char;
                                } elseif ($search[$pos + 1] === '"') {
                                    $buffer .= '"';
                                    $pos++;
                                } else {
                                    $buffer .= $char;
                                }
                                $state = self::STATE_IN_TOKEN_OR_TAG_QUOTED;
                                continue 3;
                            case "-":
                                if (!$flag_negative) {
                                    $flag_negative = true;
                                } else {
                                    $buffer .= $char;
                                    $state = self::STATE_IN_TOKEN_OR_TAG_QUOTED;
                                }
                                continue 3;
                            case "@":
                                $state = self::STATE_IN_DATE_QUOTED;
                                continue 3;
                            case ":":
                                $state = self::STATE_IN_TOKEN_QUOTED;
                                continue 3;
                            default:
                                $buffer .= $char;
                                $state = self::STATE_IN_TOKEN_OR_TAG_QUOTED;
                                continue 3;
                        }
                        // no break
                    case self::STATE_IN_DATE:
                        while ($pos < $stop && $search[$pos] !== " ") {
                            $buffer .= $search[$pos++];
                        }
                        $context = self::processToken($context, $buffer, $tag, $flag_negative, true);
                        $state = self::STATE_BEFORE_TOKEN;
                        $flag_negative = false;
                        $buffer = $tag = "";
                        continue 2;
                    case self::STATE_IN_DATE_QUOTED:
                        switch ($char) {
                            case "":
                            case '"':
                                if (($pos + 1 >= $stop) || $search[$pos + 1] === " ") {
                                    $context = self::processToken($context, $buffer, $tag, $flag_negative, true);
                                    $state = self::STATE_BEFORE_TOKEN;
                                    $flag_negative = false;
                                    $buffer = $tag = "";
                                } elseif ($search[$pos + 1] === '"') {
                                    $buffer .= '"';
                                    $pos++;
                                } else {
                                    $state = self::STATE_IN_DATE;
                                }
                                continue 3;
                            case "\\":
                                if ($pos + 1 == $stop) {
                                    $buffer .= $char;
                                } elseif ($search[$pos + 1] === '"') {
                                    $buffer .= '"';
                                    $pos++;
                                } else {
                                    $buffer .= $char;
                                }
                                continue 3;
                            default:
                                $buffer .= $char;
                                continue 3;
                        }
                        // no break
                    case self::STATE_IN_TOKEN:
                        while ($pos < $stop && $search[$pos] !== " ") {
                            $buffer .= $search[$pos++];
                        }
                        if (!strlen($tag)) {
                            $buffer = ":".$buffer;
                        }
                        $context = self::processToken($context, $buffer, $tag, $flag_negative, false);
                        $state = self::STATE_BEFORE_TOKEN;
                        $flag_negative = false;
                        $buffer = $tag = "";
                        continue 2;
                    case self::STATE_IN_TOKEN_QUOTED:
                        switch ($char) {
                            case "":
                            case '"':
                                if (($pos + 1 >= $stop) || $search[$pos + 1] === " ") {
                                    if (!strlen($tag)) {
                                        $buffer = ":".$buffer;
                                    }
                                    $context = self::processToken($context, $buffer, $tag, $flag_negative, false);
                                    $state = self::STATE_BEFORE_TOKEN;
                                    $flag_negative = false;
                                    $buffer = $tag = "";
                                } elseif ($search[$pos + 1] === '"') {
                                    $buffer .= '"';
                                    $pos++;
                                } else {
                                    $state = self::STATE_IN_TOKEN;
                                }
                                continue 3;
                            case "\\":
                                if ($pos + 1 == $stop) {
                                    $buffer .= $char;
                                } elseif ($search[$pos + 1] === '"') {
                                    $buffer .= '"';
                                    $pos++;
                                } else {
                                    $buffer .= $char;
                                }
                                continue 3;
                            default:
                                $buffer .= $char;
                                continue 3;
                        }
                        // no break
                    case self::STATE_IN_TOKEN_OR_TAG:
                        switch ($char) {
                            case "":
                            case " ":
                                $context = self::processToken($context, $buffer, $tag, $flag_negative, false);
                                $state = self::STATE_BEFORE_TOKEN;
                                $flag_negative = false;
                                $buffer = $tag = "";
                                continue 3;
                            case ":":
                                $tag = $buffer;
                                $buffer = "";
                                $state = self::STATE_IN_TOKEN;
                                continue 3;
                            default:
                                $buffer .= $char;
                                continue 3;
                        }
                        // no break
                    case self::STATE_IN_TOKEN_OR_TAG_QUOTED:
                        switch ($char) {
                            case "":
                            case '"':
                                if (($pos + 1 >= $stop) || $search[$pos + 1] === " ") {
                                    $context = self::processToken($context, $buffer, $tag, $flag_negative, false);
                                    $state = self::STATE_BEFORE_TOKEN;
                                    $flag_negative = false;
                                    $buffer = $tag = "";
                                } elseif ($search[$pos + 1] === '"') {
                                    $buffer .= '"';
                                    $pos++;
                                } else {
                                    $state = self::STATE_IN_TOKEN_OR_TAG;
                                }
                                continue 3;
                            case "\\":
                                if ($pos + 1 == $stop) {
                                    $buffer .= $char;
                                } elseif ($search[$pos + 1] === '"') {
                                    $buffer .= '"';
                                    $pos++;
                                } else {
                                    $buffer .= $char;
                                }
                                continue 3;
                            case ":":
                                $tag = $buffer;
                                $buffer = "";
                                $state = self::STATE_IN_TOKEN_QUOTED;
                                continue 3;
                            default:
                                $buffer .= $char;
                                continue 3;
                        }
                        // no break
                    default:
                        throw new \Exception; // @codeCoverageIgnore
                }
            }
        } catch (Exception $e) {
            return null;
        }
        return $context;
    }

    protected static function processToken(Context $c, string $value, string $tag, bool $neg, bool $date): Context {
        if (!strlen($value) && !strlen($tag)) {
            return $c;
        } elseif (!strlen($value)) {
            // if a tag has an empty value, the tag is treated as a search term instead
            $value = "$tag:";
            $tag = "";
        }
        if ($date) {
            return self::setDate($value, $c, $neg);
        } elseif (isset(self::FIELDS_BOOLEAN[$tag])) {
            return self::setBoolean($tag, $value, $c, $neg);
        } else {
            return self::addTerm($tag, $value, $c, $neg);
        }
    }

    protected static function addTerm(string $tag, string $value, Context $c, bool $neg): Context {
        $c = $neg ? $c->not : $c;
        $type = self::FIELDS_TEXT[$tag] ?? "";
        if (!$type) {
            $value = "$tag:$value";
            $type = self::FIELDS_TEXT[""];
        }
        return $c->$type(array_merge($c->$type ?? [], [$value]));
    }

    protected static function setDate(string $value, Context $c, bool $neg): Context {
        $spec = Date::normalize($value);
        // TTRSS treats invalid dates as the start of the Unix epoch; we ignore them instead
        if (!$spec) {
            return $c;
        }
        $day = $spec->format("Y-m-d");
        $start = $day."T00:00:00+00:00";
        $end = $day."T23:59:59+00:00";
        // if a date is already set, the same date is a no-op; anything else is a contradiction
        $cc = $neg ? $c->not : $c;
        if ($cc->modifiedSince() || $cc->notModifiedSince()) {
            if (!$cc->modifiedSince() || !$cc->notModifiedSince() || $cc->modifiedSince->format("c") !== $start || $cc->notModifiedSince->format("c") !== $end) {
                // FIXME: multiple negative dates should be allowed, but the design of the Context class does not support this
                throw new Exception;
            } else {
                return $c;
            }
        }
        $cc->modifiedSince($start);
        $cc->notModifiedSince($end);
        return $c;
    }

    protected static function setBoolean(string $tag, string $value, Context $c, bool $neg): Context {
        $set = ["true" => true, "false" => false][$value] ?? null;
        if (is_null($set)) {
            return self::addTerm($tag, $value, $c, $neg);
        } else {
            // apply negation
            $set = $neg ? !$set : $set;
            if ($tag === "pub") {
                // TODO: this needs to be implemented correctly if the Published feed is implemented
                // currently specifying true will always yield an empty result (nothing is ever published), and specifying false is a no-op (matches everything)
                if ($set) {
                    throw new Exception;
                } else {
                    return $c;
                }
            } else {
                $field = (self::FIELDS_BOOLEAN[$tag] ?? "");
                if (!$c->$field()) {
                    // field has not yet been set; set it
                    return $c->$field($set);
                } elseif ($c->$field == $set) {
                    // field is already set to same value; do nothing
                    return $c;
                } else {
                    // contradiction: query would return no results
                    throw new Exception;
                }
            }
        }
    }
}
