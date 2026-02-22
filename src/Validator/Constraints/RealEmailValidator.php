<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class RealEmailValidator extends ConstraintValidator
{
    private array $disposableDomains = [
        'tempmail.com', 'guerrillamail.com', '10minutemail.com', 'mailinator.com',
        'yopmail.com', 'throwaway.email', 'getnada.com', 'temp-mail.org'
    ];

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof RealEmail) {
            throw new UnexpectedTypeException($constraint, RealEmail::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $domain = substr(strrchr($value, "@"), 1);

        // Check disposable domains
        if ($constraint->checkDisposable && in_array(strtolower($domain), $this->disposableDomains)) {
            $this->context->buildViolation($constraint->disposableMessage)
                ->addViolation();
            return;
        }

        // Check MX records
        if ($constraint->checkMx && !checkdnsrr($domain, 'MX')) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}