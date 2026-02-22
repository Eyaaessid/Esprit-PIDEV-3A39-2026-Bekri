<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class RealEmail extends Constraint
{
    public string $message = 'This email domain cannot receive emails.';
    public string $disposableMessage = 'Disposable email addresses are not allowed.';
    public string $typoMessage = 'Did you mean {{ suggestion }} instead of {{ value }}?';
    public bool $checkMx = true;
    public bool $checkDisposable = true;
    public bool $checkTypos = true;

    public function validatedBy(): string
    {
        return RealEmailValidator::class;
    }
}
