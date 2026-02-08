<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'app:test-email')]
class TestEmailCommand extends Command
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        parent::__construct();
        $this->mailer = $mailer;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(' Testing email configuration...');
        $output->writeln('');

        try {
            $email = (new Email())
                ->from('no-reply@bekriwellbeing.com')
                ->to('hiba.bhm101@gmail.com')
                ->subject('Test Email from Symfony - ' . date('H:i:s'))
                ->html('<h1> Email Works!</h1><p>This is a test email sent at ' . date('Y-m-d H:i:s') . '</p>');

            $this->mailer->send($email);
            
            $output->writeln(' Email sent successfully!');
            $output->writeln(' Check your inbox: hiba.bhm101@gmail.com');
            $output->writeln(' Also check SPAM folder!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(' Error sending email:');
            $output->writeln('   ' . $e->getMessage());
            $output->writeln('');
            $output->writeln(' Stack trace:');
            $output->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}