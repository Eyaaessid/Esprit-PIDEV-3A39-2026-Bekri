<?php

namespace App\Validator;

use App\Validator\Constraints\RealEmail;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class RealEmailValidator extends ConstraintValidator
{
    /**
     * Common email domain typos and their corrections
     */
    private const TYPO_MAP = [
        'gmial.com' => 'gmail.com',
        'gmail.co' => 'gmail.com',
        'gmail.cm' => 'gmail.com',
        'gmail.co.uk' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'gmal.com' => 'gmail.com',
        'yaho.com' => 'yahoo.com',
        'yahoo.co' => 'yahoo.com',
        'yahoo.cm' => 'yahoo.com',
        'yhoo.com' => 'yahoo.com',
        'outlok.com' => 'outlook.com',
        'outlook.co' => 'outlook.com',
        'outlok.co' => 'outlook.com',
        'hotmai.com' => 'hotmail.com',
        'hotmail.co' => 'hotmail.com',
        'hotmial.com' => 'hotmail.com',
    ];

    /**
     * List of known disposable email domains
     */
    private const DISPOSABLE_DOMAINS = [
        'tempmail.com',
        'guerrillamail.com',
        'mailinator.com',
        '10minutemail.com',
        'throwaway.email',
        'temp-mail.org',
        'getnada.com',
        'mohmal.com',
        'yopmail.com',
        'sharklasers.com',
        'grr.la',
        'guerrillamailblock.com',
        'pokemail.net',
        'spam4.me',
        'bccto.me',
        'chitthi.in',
        'dispostable.com',
        'fakeinbox.com',
        'mintemail.com',
        'mytrashmail.com',
        'tempail.com',
        'tempe-mail.com',
        'trashmail.com',
        'trashmailer.com',
        'getairmail.com',
        'maildrop.cc',
        'meltmail.com',
        'mox.do',
        'spamgourmet.com',
        'spamhole.com',
        'spamtraps.com',
        'throwawaymail.com',
        'tmpmail.org',
        '33mail.com',
        'emailondeck.com',
        'fakemailgenerator.com',
        'mailcatch.com',
        'mailmoat.com',
        'mailnesia.com',
        'mailsac.com',
        'mailtemp.info',
        'melt.li',
        'mintemail.com',
        'mohmal.com',
        'mytrashmail.com',
        'nada.email',
        'nada.ltd',
        'putthisinyourspamdatabase.com',
        'sharklasers.com',
        'spamgourmet.com',
        'temp-mail.io',
        'tempail.com',
        'tempe-mail.com',
        'tempmail.de',
        'tempmail.eu',
        'tempmail.net',
        'tempmailo.com',
        'tempmailr.com',
        'tempmailz.com',
        'tempomail.fr',
        'tempthe.net',
        'throwaway.email',
        'throwawaymail.com',
        'trashmail.com',
        'trashmailer.com',
        'trashymail.com',
        'yopmail.com',
        'zippymail.info',
    ];

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof RealEmail) {
            throw new UnexpectedTypeException($constraint, RealEmail::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        // Extract domain from email
        $parts = explode('@', $value);
        if (count($parts) !== 2) {
            return; // Let Email constraint handle format validation
        }

        $domain = strtolower(trim($parts[1]));

        // Check for typos
        if ($constraint->checkTypos && isset(self::TYPO_MAP[$domain])) {
            $suggestion = $parts[0] . '@' . self::TYPO_MAP[$domain];
            $this->context->buildViolation($constraint->typoMessage)
                ->setParameter('{{ value }}', $value)
                ->setParameter('{{ suggestion }}', $suggestion)
                ->addViolation();
            return;
        }

        // Check for disposable email domains
        if ($constraint->checkDisposable && $this->isDisposableDomain($domain)) {
            $this->context->buildViolation($constraint->disposableMessage)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
            return;
        }

        // Check MX records
        if ($constraint->checkMx && !$this->hasMxRecords($domain)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }

    /**
     * Check if domain has valid MX records
     */
    private function hasMxRecords(string $domain): bool
    {
        // Check MX records
        if (checkdnsrr($domain, 'MX')) {
            return true;
        }

        // Some domains use A records for mail (like localhost in dev)
        // Check A record as fallback
        if (checkdnsrr($domain, 'A')) {
            return true;
        }

        return false;
    }

    /**
     * Check if domain is a disposable email service
     */
    private function isDisposableDomain(string $domain): bool
    {
        // Check exact match
        if (in_array($domain, self::DISPOSABLE_DOMAINS, true)) {
            return true;
        }

        // Check subdomains (e.g., anything.tempmail.com)
        foreach (self::DISPOSABLE_DOMAINS as $disposableDomain) {
            if (str_ends_with($domain, '.' . $disposableDomain)) {
                return true;
            }
        }

        return false;
    }
}
