<?php

declare(strict_types=1);

namespace Marcosh\LamPHPda\Validation\Instances\Validation;

use Marcosh\LamPHPda\Either;
use Marcosh\LamPHPda\HK\HK1;
use Marcosh\LamPHPda\Instances\Either\EitherApplicative;
use Marcosh\LamPHPda\Typeclass\Applicative;
use Marcosh\LamPHPda\Typeclass\Semigroup;
use Marcosh\LamPHPda\Validation\Brand\ValidationBrand;
use Marcosh\LamPHPda\Validation\Validation;

/**
 * @template C
 * @template E
 * @implements Applicative<ValidationBrand<C, E>>
 *
 * @psalm-immutable
 */
final class ValidationApplicative implements Applicative
{
    /** @var Semigroup<E> */
    private Semigroup $eSemigroup;

    /**
     * @param Semigroup<E> $eSemigroup
     */
    public function __construct(Semigroup $eSemigroup)
    {
        $this->eSemigroup = $eSemigroup;
    }

    /**
     * @template A
     * @template B
     * @param pure-callable(A): B $f
     * @param HK1<ValidationBrand<C, E>, A> $a
     * @return Validation<C, E, B>
     *
     * @psalm-pure
     *
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    public function map(callable $f, HK1 $a): Validation
    {
        return (new ValidationFunctor())->map($f, $a);
    }

    /**
     * @template A
     * @template B
     * @param HK1<ValidationBrand<C, E>, callable(A): B> $f
     * @param HK1<ValidationBrand<C, E>, A> $a
     * @return Validation<C, E, B>
     *
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    public function apply(HK1 $f, HK1 $a): Validation
    {
        return (new ValidationApply($this->eSemigroup))->apply($f, $a);
    }

    /**
     * @template A
     * @param A $a
     * @return Validation<C, E, A>
     *
     * @psalm-pure
     *
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    public function pure($a): Validation
    {
        return new Validation(
            /**
             * @param C $_
             * @return Either<E, A>
             */
            fn($_) => (new EitherApplicative())->pure($a)
        );
    }
}
