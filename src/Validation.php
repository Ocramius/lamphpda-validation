<?php

declare(strict_types=1);

namespace Marcosh\LamPHPda\Validation;

use Marcosh\LamPHPda\Brand\EitherBrand;
use Marcosh\LamPHPda\Brand\ListBrand;
use Marcosh\LamPHPda\Either;
use Marcosh\LamPHPda\HK\HK1;
use Marcosh\LamPHPda\HK\HK2;
use Marcosh\LamPHPda\Instances\Either\EitherFunctor;
use Marcosh\LamPHPda\Instances\Either\ValidationApplicative;
use Marcosh\LamPHPda\Instances\ListL\ListTraversable;
use Marcosh\LamPHPda\ListL;
use Marcosh\LamPHPda\Optics\Lens;
use Marcosh\LamPHPda\Traversable;
use Marcosh\LamPHPda\Typeclass\Applicative;
use Marcosh\LamPHPda\Typeclass\DefaultInstance\DefaultProfunctor;
use Marcosh\LamPHPda\Typeclass\Extra\ExtraProfunctor;
use Marcosh\LamPHPda\Typeclass\Monoid;
use Marcosh\LamPHPda\Typeclass\Semigroup;
use Marcosh\LamPHPda\Validation\Brand\ValidationBrand;
use Marcosh\LamPHPda\Validation\Brand\ValidationBrand2;
use Marcosh\LamPHPda\Validation\Instances\Validation\AllMonoid;
use Marcosh\LamPHPda\Validation\Instances\Validation\AnyMonoid;
use Marcosh\LamPHPda\Validation\Instances\Validation\ValidationProfunctor;

/**
 * a validation is nothing else that a function from A to Either<E, B>
 *
 * @template A raw input to the validation
 * @template E potential validation error
 * @template B parsed validation result
 *
 * @implements DefaultProfunctor<ValidationBrand2<E>, A, B>
 * @implements HK1<ValidationBrand<A, E>, B>
 *
 * @psalm-immutable
 */
final class Validation implements DefaultProfunctor, HK1
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
     * @template C
     * @template D
     * @template F
     * @param HK1<ValidationBrand<C, F>, D> $hk
     * @return Validation<C, F, D>
     *
     * @psalm-pure
     */
    public static function fromBrand(HK1 $hk): self
    {
        /** @var Validation<C, F, D> */
        return $hk;
    }

    /**
     * @template C
     * @template D
     * @template F
     * @param HK2<ValidationBrand2<F>, C, D> $hk
     * @return Validation<C, F, D>
     *
     * @psalm-pure
     */
    public static function fromBrand2(HK2 $hk): self
    {
        /** @var Validation<C, F, D> */
        return $hk;
    }

    // PROFUNCTOR INSTANCE

    /**
     * @template C
     * @template D
     * @param callable(C): A $f
     * @param callable(B): D $g
     * @return Validation<C, E, D>
     */
    public function diMap(callable $f, callable $g): self
    {
        return (new ValidationProfunctor())->diMap($f, $g, $this);
    }

    /**
     * @template C
     * @param callable(C): A $f
     * @return Validation<C, E, B>
     */
    public function lmap(callable $f): self
    {
        return self::fromBrand2((new ExtraProfunctor(new ValidationProfunctor()))->lmap($f, $this));
    }

    /**
     * @template D
     * @param callable(B): D $g
     * @return Validation<A, E, D>
     */
    public function rmap(callable $g): self
    {
        return self::fromBrand2((new ExtraProfunctor(new ValidationProfunctor()))->rmap($g, $this));
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
     * Kleisli composition of validations
     *
     * @template C
     * @param Validation<B, E, C> $that
     * @return Validation<A, E, C>
     */
    public function then(self $that): self
    {
        return new self(
            /**
             * @param A $a
             * @return Either<E, C>
             *
             * @psalm-suppress ArgumentTypeCoercion
             */
            fn($a) => $this->validate($a)->bind($that->validation)
        );
    }

    // TRIVIAL COMBINATORS

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
     * @template C of array
     * @template F
     * @param array-key $key
     * @param F $e
     * @return Validation<C, F, C>
     */
    public static function hasKey($key, $e): self
    {
        return self::satisfies(
            /**
             * @param C $a
             */
            fn($a) => array_key_exists($key, $a),
            $e
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
     * @return (C is float ? Validation<C, F, C> : Validation<C, F, float>)
     */
    public static function isFloat($e): self
    {
        /** @var (C is float ? Validation<C, F, C> : Validation<C, F, float>) */
        return self::satisfies('is_float', $e);
    }

    /**
     * @template C
     * @template F
     * @param F $e
     * @return (C is int ? Validation<C, F, C> : Validation<C, F, int>)
     */
    public static function isInteger($e): self
    {
        /** @var (C is int ? Validation<C, F, C> : Validation<C, F, int>) */
        return self::satisfies('is_int', $e);
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

    /**
     * @template F
     * @param F $e
     * @return Validation<array, F, non-empty-array>
     */
    public static function nonEmptyArray($e): self
    {
        /** @var Validation<array, F, non-empty-array> */
        return self::satisfies(
            fn (array $a) => $a !== [],
            $e
        );
    }

    /**
     * @template F
     * @param F $e
     * @return Validation<string, F, non-empty-string>
     */
    public static function nonEmptyString($e): self
    {
        /** @var Validation<string, F, non-empty-string> */
        return self::satisfies(
            fn (string $a) => $a !== '',
            $e
        );
    }

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

    // COMBINATORS

    /**
     * @template C
     * @template F
     * @template D
     * @param Semigroup<F> $eSemigroup
     * @param list<Validation<C, F, D>> $validations
     * @return Validation<C, F, D>
     */
    public static function all(Semigroup $eSemigroup, array $validations): self
    {
        /** @var AllMonoid<C, F, D> $allMonoid */
        $allMonoid = new AllMonoid($eSemigroup);

        return self::fold($allMonoid, $validations);
    }

    /**
     * @template C
     * @template F
     * @template D
     * @param Monoid<F> $eMonoid
     * @param list<Validation<C, F, D>> $validations
     * @return Validation<C, F, D>
     */
    public static function any(Monoid $eMonoid, array $validations): self
    {
        /** @var AnyMonoid<C, F, D> $anyMonoid */
        $anyMonoid = new AnyMonoid($eMonoid);

        return self::fold($anyMonoid, $validations);
    }

    /**
     * @template C
     * @template F
     * @param Validation<C, F, C> $elementValidation
     * @param F $e
     * @return Validation<list<C>, F, list<C>>
     */
    public static function anyElement(Validation $elementValidation, $e): self
    {
        /** @var Validation<list<C>, F, list<C>> */
        return new self(
            /**
             * @param list<C> $cs
             * @return Either<F, list<C>>
             */
            function (array $cs) use ($elementValidation, $e) {
                foreach ($cs as $c) {
                    /** @psalm-suppress InvalidArgument */
                    $succeeds = $elementValidation->validate($c)->eval(
                        /**
                         * @param F $_
                         * @return bool
                         */
                        fn($_) => false,
                        /**
                         * @param C $_
                         * @return bool
                         */
                        fn($_) => true
                    );

                    if ($succeeds) {
                        /** @var Either<F, list<C>> */
                        return Either::right($cs);
                    }
                }

                /** @var Either<F, list<C>> */
                return Either::left($e);
            }
        );
    }

    /**
     * @template C
     * @template D
     * @template F
     * @param Semigroup<F> $eSemigroup
     * @param Validation<C, F, D> $elementValidation
     * @return Validation<list<C>, F, list<D>>
     */
    public static function everyElement(Semigroup $eSemigroup, Validation $elementValidation): self
    {
        /** @var Validation<list<C>, F, list<D>> */
        return new self(
            /**
             * @param list<C> $cs
             * @return Either<F, list<D>>
             */
            function (array $cs) use ($eSemigroup, $elementValidation) {
                /** @var Applicative<EitherBrand<F> > $applicative */
                $applicative = new ValidationApplicative($eSemigroup);

                /**
                 * @psalm-suppress ArgumentTypeCoercion
                 * @psalm-suppress InvalidArgument
                 */
                return (new EitherFunctor())->map(
                    /**
                     * @param HK1<ListBrand, D> $hk
                     * @return list<D>
                     */
                    fn (HK1 $hk) => ListL::fromBrand($hk)->asNativeList(),
                    (new ListTraversable())->traverse(
                        $applicative,
                        $elementValidation->validation,
                        new ListL($cs)
                    )
                );
            }
        );
    }

    /**
     * @template C
     * @template F
     * @template D
     * @template L
     * @template M
     * @param Lens<C, D, L, M> $lens
     * @param Validation<L, F, M> $validation
     * @return Validation<C, F, D>
     */
    public static function focus(Lens $lens, self $validation): self
    {
        return new Validation(
            /**
             * @param C $c
             * @return Either<F, D>
             */
            function ($c) use ($lens, $validation) {
                $l = $lens->get($c);

                /** @psalm-suppress InvalidArgument */
                return $validation->validate($l)->map(
                    /**
                     * @param M $m
                     * @return D
                     */
                    function ($m) use ($c, $lens) {
                        return $lens->set($c, $m);
                    }
                );
            }
        );
    }

    /**
     * @template C
     * @template F
     * @template D
     * @param Monoid<Validation<C, F, D>> $validationMonoid
     * @param list<Validation<C, F, D>> $validations
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
