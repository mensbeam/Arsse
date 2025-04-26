<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Context;

/**
 * @method self folder(int $spec)
 * @method self folders(array $spec)
 * @method self folderShallow(int $spec)
 * @method self foldersShallow(array $spec)
 * @method self tag(int $spec)
 * @method self tags(array $spec)
 * @method self tagName(string $spec)
 * @method self tagNames(array $spec)
 * @method self subscription(int $spec)
 * @method self subscriptions(array $spec)
 * @method self edition(int $spec)
 * @method self article(int $spec)
 * @method self editions(array $spec)
 * @method self articles(array $spec)
 * @method self label(int $spec)
 * @method self labels(array $spec)
 * @method self labelName(string $spec)
 * @method self labelNames(array $spec)
 * @method self annotationTerms(array $spec)
 * @method self searchTerms(array $spec)
 * @method self titleTerms(array $spec)
 * @method self authorTerms(array $spec)
 * @method self articleRange(int $start, int $end)
 * @method self editionRange(int $start, int $end)
 * @method self modifiedRange($start, $end)
 * @method self modifiedRanges(array $spec)
 * @method self markedRange($start, $end)
 * @method self markedRanges(array $spec)
 * @method self addedRange($start, $end)
 * @method self addedRanges(array $spec)
 * @method self publishedRange($start, $end)
 * @method self publishedRanges(array $spec)
 */
class ExclusionUnionContext {
    protected $parent;
    protected $contexts;

    public function __construct(UnionContext $parent, &$contexts) {
        $this->parent = $parent;
        $this->contexts = $contexts;
    }

    /** @codeCoverageIgnore */
    public function __destruct() {
        unset($this->contexts);
        unset($this->parent);
    }

    #[\ReturnTypeWillChange]
    public function __call($name, array $arguments) {
        foreach ($this->contexts as $c) {
            $c->not->$name(...$arguments);
        }
        return $this->parent;
    }

    public function __clone() {
        throw new \Exception("Union contexts cannot be cloned.");
    }
}
