<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Test email configuration and sending.
 * Run: php bin/console app:test-email
 */
#[AsCommand(
    name: 'app:test-email',
    description: 'Test email configuration and sending',
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Email Configuration Test');
        
        $dsn = $_ENV['MAILER_DSN'] ?? 'not set';
        $io->section('Configuration');
        $io->text([
            'MAILER_DSN: ' . (str_contains($dsn, 'null') ? 'NULL (testing mode)' : $dsn),
        ]);
        
        $io->section('Sending Test Email');
        $io->note('Attempting to send a test email...');
        
        try {
            $email = (new Email())
                ->from('noreply@bekri.com')
                ->to('test@example.com')
                ->subject('Test Email from Bekri')
                ->text('This is a test email to verify SMTP configuration.')
                ->html('<p>This is a <strong>test email</strong> to verify SMTP configuration.</p>');
            
            $this->mailer->send($email);
            
            $io->success([
                '✅ Email sent successfully!',
                'Check your logs for details:',
                '  - var/log/dev.log',
                '  - var/log/mailer.log',
            ]);
            
            if (str_contains($dsn, 'null')) {
                $io->warning('Note: Using NULL transport - email was not actually sent, but the flow worked correctly.');
            }
            
            return Command::SUCCESS;
            
        } catch (\Throwable $e) {
            $io->error([
                '❌ Failed to send email',
                'Error: ' . $e->getMessage(),
                '',
                'Troubleshooting:',
                '1. Check firewall settings (port 587)',
                '2. Check antivirus blocking SMTP',
                '3. Try null transport: MAILER_DSN=null://null',
                '4. Check logs: var/log/dev.log',
            ]);
            
            $io->text('Full error:');
            $io->text($e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }
}
