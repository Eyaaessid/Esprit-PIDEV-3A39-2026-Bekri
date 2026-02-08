<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'app:test-email-v2')]
class TestEmailV2Command extends Command
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        parent::__construct();
        $this->mailer = $mailer;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(' Testing email with Gmail FROM address...');
        $output->writeln('');

        try {
            $email = (new Email())
                ->from('hiba.bhm101@gmail.com')  // Send FROM your Gmail
                ->to('hiba.bhm101@gmail.com')    // Send TO your Gmail
                ->subject('Test Reset Password - ' . date('H:i:s'))
                ->html('<h1> Password Reset Test</h1><p>If you see this, email works!</p><p>Time: ' . date('Y-m-d H:i:s') . '</p>');

            $this->mailer->send($email);
            
            $output->writeln(' Email sent successfully!');
            $output->writeln(' Check your inbox NOW');
            $output->writeln(' Sent at: ' . date('H:i:s'));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(' Error: ' . $e->getMessage());
            $output->writeln('');
            $output->writeln('Full error:');
            $output->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}