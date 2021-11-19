<?php

declare(strict_types=1);

namespace Marcosh\LamPHPda\Validation;

use Marcosh\LamPHPda\Either;
use Marcosh\LamPHPda\Traversable;
use Marcosh\LamPHPda\Typeclass\Monoid;
use Marcosh\LamPHPda\Validation\Instances\ValidationSemigroup;

/**
 * a validation is nothing else that a function from A to Either<E, B>
 *
 * @template A raw input to the validation
 * @template E potential validation error
 * @template B parsed validation result
 *
 * @psalm-immutable
 */
final class Validation
{
    /** @var callable(A): Either<E, B> */
    private $validation;

    /**
     * @param callable(A): Either<E, B> $validation
     */
    public function __construct(callable $validation)
    {
        $this->validation = $validation;
    }

    /**
     * this is the only thing you can do with a validation: pass to it some data and get back either an error or some
     * valid data
     *
     * @param A $a
     * @return Either<E, B>
     *
     * @psalm-mutation-free
     */
    public function validate($a): Either
    {
        return ($this->validation)($a);
    }

    /**
     * @template C
     * @template F
     * @return Validation<C, F, C>
     *
     * @psalm-pure
     */
    public static function valid(): self
    {
        return new self(
            /**
             * @param C $a
             */
            fn($a) => Either::right($a)
        );
    }

    /**
     * @template C
     * @template F
     * @template D
     * @param F $e
     * @return Validation<C, F, D>
     *
     * @psalm-pure
     */
    public static function invalid($e): self
    {
        return new self(
            /**
             * @param C $_
             */
            fn($_) => Either::left($e)
        );
    }

    // BASIC VALIDATORS

    /**
     * @template C
     * @template F
     * @param callable(C): bool $f
     * @param F $e
     * @return Validation<C, F, C>
     */
    public static function satisfies(callable $f, $e): self
    {
        return new self(
            /**
             * @param C $a
             */
            static function ($a) use ($e, $f) {
                if (!$f($a)) {
                    /** @var Either<F, C> */
                    return Either::left($e);
                }

                /** @var Either<F, C> */
                return Either::right($a);
            }
        );
    }

    /**
     * @template C
     * @template F
     * @param F $e
     * @return (C is array ? Validation<C, F, C> : Validation<C, F, array>)
     */
    public static function isArray($e): self
    {
        /** @var (C is array ? Validation<C, F, C> : Validation<C, F, array>) */
        return self::satisfies('is_array', $e);
    }

    /**
     * @template C
     * @template F
     * @param F $e
     * @return (C is string ? Validation<C, F, C> : Validation<C, F, string>)
     */
    public static function isString($e): self
    {
        /** @var (C is string ? Validation<C, F, C> : Validation<C, F, string>) */
        return self::satisfies('is_string', $e);
    }

    // COMBINATORS

    /**
     * @template C
     * @template F
     * @template D
     * @param Monoid<Validation<C, F, D>> $validationMonoid
     * @param Validation<C, F, D>[] $validations
     * @return Validation<C, F, D>
     */
    public static function fold(Monoid $validationMonoid, array $validations): self
    {
        /** @var Validation<C, F, D> */
        return Traversable::fromArray($validations)->foldr(
            [$validationMonoid, 'append'],
            $validationMonoid->mempty()
        );
    }
}