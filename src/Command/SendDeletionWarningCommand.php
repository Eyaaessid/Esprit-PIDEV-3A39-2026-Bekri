<?php

namespace App\Command;

use App\Entity\Utilisateur;
use App\Enum\UtilisateurStatut;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

/**
 * Send "your account will be deleted in 10 days" to users inactive for 355 days.
 * Run daily: php bin/console app:send-deletion-warnings
 */
#[AsCommand(
    name: 'app:send-deletion-warnings',
    description: 'Send warning email to users who will be deleted in 10 days',
)]
class SendDeletionWarningCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $from = new \DateTime('-365 days');
        $to = new \DateTime('-355 days');

        $users = $this->entityManager->getRepository(Utilisateur::class)->createQueryBuilder('u')
            ->where('u.statut = :inactif')->setParameter('inactif', UtilisateurStatut::INACTIF)
            ->andWhere('u.deactivatedAt <= :to')->setParameter('to', $to)
            ->andWhere('u.deactivatedAt > :from')->setParameter('from', $from)
            ->getQuery()->getResult();

        $count = 0;
        foreach ($users as $user) {
            if ($user->getRole()->value === 'admin') {
                continue;
            }
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address('noreply@bekri.com', 'Bekri Wellbeing'))
                    ->to(new Address($user->getEmail(), $user->getPrenom() . ' ' . $user->getNom()))
                    ->subject('Votre compte sera supprimé dans 10 jours')
                    ->htmlTemplate('emails/account_deletion_warning.html.twig')
                    ->context(['user' => $user]);
                $this->mailer->send($email);
                $count++;
            } catch (\Throwable $e) {
                $io->warning("Could not send warning to {$user->getEmail()}: " . $e->getMessage());
            }
        }

        $io->success("Sent {$count} deletion warning(s).");
        return Command::SUCCESS;
    }
}
